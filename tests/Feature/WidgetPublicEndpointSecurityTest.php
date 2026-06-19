<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WidgetPublicEndpointSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_requests_are_blocked_for_project_disallowed_origin(): void
    {
        $project = $this->createProjectWithSecret();
        $project->update([
            'widget_settings' => [
                'allowed_domains' => ['trusted.example'],
            ],
        ]);

        $this->withHeaders([
            'Origin' => 'http://evil.example',
            'X-Widget-Secret' => 'secret-123',
        ])->postJson('/api/widget/'.$project->widget_key.'/chats', [
            'title' => 'Bad origin',
        ])->assertForbidden();
    }

    public function test_widget_message_requires_valid_session_token(): void
    {
        $project = $this->createProjectWithSecret('my-widget-secret');

        $create = $this->withHeaders($this->widgetHeaders('my-widget-secret'))
            ->postJson('/api/widget/'.$project->widget_key.'/chats', [
                'title' => 'Session',
            ])->assertCreated();

        $chatSessionId = $create->json('data.id');

        $this->withHeaders($this->widgetHeaders('my-widget-secret'))
            ->postJson('/api/widget/'.$project->widget_key.'/chats/message', [
                'chat_session_id' => $chatSessionId,
                'message' => 'What is this?',
                'session_token' => str_repeat('x', 40),
            ])
            ->assertForbidden();
    }

    public function test_widget_create_session_is_rate_limited(): void
    {
        $project = $this->createProjectWithSecret('rate-limit-secret');

        for ($i = 0; $i < 30; $i++) {
            $this->withHeaders($this->widgetHeaders('rate-limit-secret'))
                ->postJson('/api/widget/'.$project->widget_key.'/chats', ['title' => 'T'.$i])
                ->assertCreated();
        }

        $this->withHeaders($this->widgetHeaders('rate-limit-secret'))
            ->postJson('/api/widget/'.$project->widget_key.'/chats', ['title' => 'Blocked'])
            ->assertStatus(429);
    }

    public function test_widget_session_stores_email_from_user_token_and_request_time(): void
    {
        $project = $this->createProjectWithSecret('token-secret');
        $jwt = $this->fakeJwt([
            'sub' => 'user-123',
            'email' => 'visitor@example.com',
            'name' => 'Widget Visitor',
        ]);

        $response = $this->withHeaders($this->widgetHeaders('token-secret'))
            ->postJson('/api/widget/'.$project->widget_key.'/chats', [
                'title' => 'Widget chat',
                'user_token' => $jwt,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.metadata.widget_user.email', 'visitor@example.com')
            ->assertJsonPath('data.metadata.widget_user.token_present', true);

        $session = ChatSession::query()->findOrFail($response->json('data.id'));

        $this->assertSame('visitor@example.com', data_get($session->metadata, 'widget_user.email'));
        $this->assertTrue((bool) data_get($session->metadata, 'widget_user.token_present'));
        $this->assertSame('user-123', data_get($session->metadata, 'widget_user.subject'));
        $this->assertNotNull(data_get($session->metadata, 'widget_last_request_at'));
    }

    public function test_widget_settings_are_public_by_widget_key(): void
    {
        $project = $this->createProjectWithSecret('settings-secret');
        $project->update([
            'widget_settings' => [
                'title' => 'Help Center',
                'welcome_message' => 'Welcome to support.',
                'input_placeholder' => 'Ask us...',
                'primary_color' => '#112233',
                'position' => 'bottom-left',
                'theme' => 'dark',
                'language' => 'en',
                'show_citations' => true,
                'allowed_domains' => ['example.com'],
                'suggested_questions' => ['What can you do?'],
            ],
        ]);

        $this->getJson('/api/widget/'.$project->widget_key.'/settings')
            ->assertOk()
            ->assertJsonPath('data.title', 'Help Center')
            ->assertJsonPath('data.position', 'bottom-left')
            ->assertJsonPath('data.suggested_questions.0', 'What can you do?');
    }

    public function test_widget_settings_default_to_hiding_citations(): void
    {
        $project = $this->createProjectWithSecret('settings-secret');

        $this->getJson('/api/widget/'.$project->widget_key.'/settings')
            ->assertOk()
            ->assertJsonPath('data.show_citations', false);
    }

    public function test_project_widget_allowed_domains_are_enforced(): void
    {
        $project = $this->createProjectWithSecret('domain-secret');
        $project->update([
            'widget_settings' => [
                'allowed_domains' => ['trusted.example'],
            ],
        ]);

        $this->withHeaders([
            'Origin' => 'https://evil.example',
            'X-Widget-Secret' => 'domain-secret',
            'Accept' => 'application/json',
        ])->postJson('/api/widget/'.$project->widget_key.'/chats', [
            'title' => 'Blocked origin',
        ])->assertForbidden();

        $this->withHeaders([
            'Origin' => 'https://app.trusted.example',
            'X-Widget-Secret' => 'domain-secret',
            'Accept' => 'application/json',
        ])->postJson('/api/widget/'.$project->widget_key.'/chats', [
            'title' => 'Allowed origin',
        ])->assertCreated();
    }

    public function test_widget_answer_hides_citations_when_project_setting_is_disabled(): void
    {
        config(['rag.retrieval.top_k' => 1]);
        Http::fake([
            '*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2, 0.3]], 200),
            '*/api/generate' => Http::response(['response' => 'Returns are allowed within 30 days.'], 200),
        ]);

        $project = $this->createProjectWithSecret('citation-secret');
        $project->update([
            'widget_settings' => [
                'show_citations' => false,
            ],
        ]);
        $this->createSearchableChunk($project);

        $create = $this->withHeaders($this->widgetHeaders('citation-secret'))
            ->postJson('/api/widget/'.$project->widget_key.'/chats', [
                'title' => 'Session',
            ])->assertCreated();

        $this->withHeaders($this->widgetHeaders('citation-secret'))
            ->postJson('/api/widget/'.$project->widget_key.'/chats/message', [
                'chat_session_id' => $create->json('data.id'),
                'message' => 'What is the return policy?',
                'session_token' => $create->json('session_token'),
            ])
            ->assertOk()
            ->assertJsonPath('data.citations', []);
    }

    public function test_widget_user_can_submit_feedback_for_assistant_message(): void
    {
        $project = $this->createProjectWithSecret('feedback-secret');

        $create = $this->withHeaders($this->widgetHeaders('feedback-secret'))
            ->postJson('/api/widget/'.$project->widget_key.'/chats', [
                'title' => 'Session',
            ])->assertCreated();

        $session = ChatSession::query()->findOrFail($create->json('data.id'));
        $message = ChatMessage::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Widget answer',
        ]);

        $this->withHeaders($this->widgetHeaders('feedback-secret'))
            ->postJson('/api/widget/'.$project->widget_key.'/feedback', [
                'chat_session_id' => $session->id,
                'chat_message_id' => $message->id,
                'session_token' => $create->json('session_token'),
                'rating' => 'unhelpful',
            ])
            ->assertOk()
            ->assertJsonPath('data.rating', 'unhelpful');

        $this->assertDatabaseHas('chat_message_feedback', [
            'chat_message_id' => $message->id,
            'rating' => 'unhelpful',
            'channel' => 'widget',
        ]);
    }

    private function createProjectWithSecret(string $plainSecret = 'secret-123'): Project
    {
        $owner = User::factory()->create();
        $tenant = Tenant::query()->create(['name' => 'Tenant A']);
        $tenant->users()->attach($owner->id, ['role' => 'owner']);

        return Project::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'name' => 'Widget Project',
            'slug' => 'widget-project',
            'widget_key' => 'widget-key-'.strtolower(str()->random(28)),
            'widget_secret_hash' => Hash::make($plainSecret),
        ]);
    }

    private function createSearchableChunk(Project $project): void
    {
        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $project->owner_id,
            'original_name' => 'return-policy.pdf',
            'storage_path' => 'documents/return-policy.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'status' => 'processed',
        ]);

        DocumentChunk::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'project_document_id' => $document->id,
            'chunk_index' => 0,
            'page_from' => 1,
            'page_to' => 2,
            'content' => 'The return policy allows returns within 30 days.',
            'embedding' => [0.1, 0.2, 0.3],
            'metadata' => [
                'document_name' => 'return-policy.pdf',
                'excerpt' => 'Returns are allowed within 30 days.',
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function widgetHeaders(string $secret): array
    {
        return [
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/widget',
            'X-Widget-Secret' => $secret,
            'Accept' => 'application/json',
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function fakeJwt(array $claims): string
    {
        $encode = static function (array $payload): string {
            return rtrim(strtr(base64_encode((string) json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        };

        return $encode(['alg' => 'HS256', 'typ' => 'JWT']).'.'.$encode($claims).'.signature';
    }
}
