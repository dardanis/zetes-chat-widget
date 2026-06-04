<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WidgetPublicEndpointSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_requests_are_blocked_for_disallowed_origin(): void
    {
        $project = $this->createProjectWithSecret();

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


