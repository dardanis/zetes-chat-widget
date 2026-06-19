<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatMessageFeedback;
use App\Models\ChatSession;
use App\Models\DocumentChunk;
use App\Models\MessageCitation;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_project_in_their_tenant(): void
    {
        [$user, $tenant] = $this->createUserWithTenant();

        $response = $this->actingAs($user)
            ->postJson('/api/projects', [
                'tenant_id' => $tenant->id,
                'country_code' => 'DE',
                'name' => 'Knowledge Base',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Knowledge Base')
            ->assertJsonStructure([
                'data' => ['id', 'tenant_id', 'owner_id', 'name', 'slug', 'widget_key', 'widget_secret'],
            ]);

        $this->assertDatabaseHas('projects', [
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'name' => 'Knowledge Base',
        ]);
    }

    public function test_project_creation_returns_widget_secret_once(): void
    {
        [$user, $tenant] = $this->createUserWithTenant();

        $response = $this->actingAs($user)
            ->postJson('/api/projects', [
                'tenant_id' => $tenant->id,
                'country_code' => 'DE',
                'name' => 'Secret Project',
            ]);

        $response->assertCreated();

        $secret = $response->json('data.widget_secret');
        $this->assertNotEmpty($secret);
        $this->assertGreaterThanOrEqual(40, strlen($secret));
    }

    public function test_user_can_list_projects_across_their_tenants(): void
    {
        [$user, $tenant] = $this->createUserWithTenant();

        Project::query()->create([
            'tenant_id' => $tenant->id,
            'country_code' => 'DE',
            'owner_id' => $user->id,
            'name' => 'Project Alpha',
            'slug' => 'project-alpha',
            'widget_key' => 'wk-'.str_repeat('a', 30),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Project Alpha');
    }

    public function test_user_cannot_see_projects_from_other_tenants(): void
    {
        $userA = User::factory()->create(['role' => 'manager']);
        $userB = User::factory()->create(['role' => 'manager']);
        $userA->countries()->sync(['DE']);
        $userB->countries()->sync(['FR']);

        $tenantA = Tenant::query()->create(['name' => 'Tenant A', 'country_code' => 'DE']);
        $tenantB = Tenant::query()->create(['name' => 'Tenant B', 'country_code' => 'FR']);

        $tenantA->users()->attach($userA->id, ['role' => 'owner']);
        $tenantB->users()->attach($userB->id, ['role' => 'owner']);

        Project::query()->create([
            'tenant_id' => $tenantA->id,
            'country_code' => 'DE',
            'owner_id' => $userA->id,
            'name' => 'Private Project',
            'slug' => 'private-project',
            'widget_key' => 'wk-'.str_repeat('b', 30),
        ]);

        $this->actingAs($userB)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_create_project_in_foreign_tenant(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $user->countries()->sync(['DE']);
        $foreignTenant = Tenant::query()->create(['name' => 'Foreign', 'country_code' => 'FR']);

        $this->actingAs($user)
            ->postJson('/api/projects', [
                'tenant_id' => $foreignTenant->id,
                'country_code' => 'FR',
                'name' => 'Intruder Project',
            ])
            ->assertForbidden();
    }

    public function test_create_project_requires_name_and_tenant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/projects', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_id', 'name']);
    }

    public function test_unauthenticated_user_cannot_list_projects(): void
    {
        $this->getJson('/api/projects')
            ->assertUnauthorized();
    }

    public function test_user_can_update_project_name(): void
    {
        [$user, $tenant] = $this->createUserWithTenant();

        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'country_code' => 'DE',
            'owner_id' => $user->id,
            'name' => 'Old Name',
            'slug' => 'old-name',
            'widget_key' => 'wk-'.str_repeat('u', 30),
        ]);

        $this->actingAs($user)
            ->putJson("/api/projects/{$project->id}", ['name' => 'New Name', 'country_code' => 'DE'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'New Name']);
    }

    public function test_user_cannot_update_foreign_project(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $user->countries()->sync(['DE']);
        $foreignTenant = Tenant::query()->create(['name' => 'Foreign', 'country_code' => 'FR']);
        $foreignUser = User::factory()->create();
        $foreignTenant->users()->attach($foreignUser->id, ['role' => 'owner']);

        $project = Project::query()->create([
            'tenant_id' => $foreignTenant->id,
            'country_code' => 'FR',
            'owner_id' => $foreignUser->id,
            'name' => 'Their Project',
            'slug' => 'their-project',
            'widget_key' => 'wk-'.str_repeat('f', 30),
        ]);

        $this->actingAs($user)
            ->putJson("/api/projects/{$project->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_user_can_delete_project(): void
    {
        [$user, $tenant] = $this->createUserWithTenant();

        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'country_code' => 'DE',
            'owner_id' => $user->id,
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'widget_key' => 'wk-'.str_repeat('d', 30),
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_user_can_update_project_widget_settings(): void
    {
        [$user, $tenant] = $this->createUserWithTenant();

        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'country_code' => 'DE',
            'owner_id' => $user->id,
            'name' => 'Widget Config',
            'slug' => 'widget-config',
            'widget_key' => 'wk-'.str_repeat('w', 30),
        ]);

        $this->actingAs($user)
            ->putJson("/api/projects/{$project->id}/widget-settings", [
                'title' => 'Support Chat',
                'welcome_message' => 'How can we help?',
                'input_placeholder' => 'Ask support...',
                'primary_color' => '#123abc',
                'position' => 'bottom-left',
                'theme' => 'light',
                'language' => 'en',
                'show_citations' => false,
                'allowed_domains' => ['example.com', 'help.example.com'],
                'suggested_questions' => ['How do I start?'],
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Support Chat')
            ->assertJsonPath('data.position', 'bottom-left')
            ->assertJsonPath('data.allowed_domains.0', 'example.com');

        $this->assertSame('Support Chat', $project->fresh()->widget_settings['title']);
    }

    public function test_user_can_submit_feedback_for_project_assistant_message(): void
    {
        [$user, $tenant] = $this->createUserWithTenant();
        $project = $this->createProject($tenant, $user);
        $session = ChatSession::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'channel' => 'dashboard',
        ]);
        $message = ChatMessage::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Answer',
        ]);

        $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/messages/{$message->id}/feedback", [
                'rating' => 'helpful',
            ])
            ->assertOk()
            ->assertJsonPath('data.rating', 'helpful');

        $this->assertDatabaseHas('chat_message_feedback', [
            'chat_message_id' => $message->id,
            'rating' => 'helpful',
            'channel' => 'dashboard',
        ]);
    }

    public function test_user_can_view_project_analytics(): void
    {
        [$user, $tenant] = $this->createUserWithTenant();
        $project = $this->createProject($tenant, $user);
        $session = ChatSession::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'channel' => 'dashboard',
        ]);
        $question = ChatMessage::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'chat_session_id' => $session->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'What is the return policy?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $answer = ChatMessage::query()->forceCreate([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Context is insufficient to answer.',
            'created_at' => $question->created_at->copy()->addSeconds(3),
            'updated_at' => $question->created_at->copy()->addSeconds(3),
        ]);
        $chunk = $this->createDocumentChunk($tenant, $project, $user);
        MessageCitation::query()->create([
            'chat_message_id' => $answer->id,
            'document_chunk_id' => $chunk->id,
            'score' => 0.8,
            'metadata' => ['document_name' => 'returns.pdf'],
        ]);
        ChatMessageFeedback::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'chat_session_id' => $session->id,
            'chat_message_id' => $answer->id,
            'user_id' => $user->id,
            'rating' => 'helpful',
            'channel' => 'dashboard',
        ]);

        $this->actingAs($user)
            ->getJson("/api/projects/{$project->id}/analytics")
            ->assertOk()
            ->assertJsonPath('data.total_chats', 1)
            ->assertJsonPath('data.most_asked_questions.0.question', 'What is the return policy?')
            ->assertJsonPath('data.failed_no_answer_questions.0.question', 'What is the return policy?')
            ->assertJsonPath('data.average_response_time_seconds', 3)
            ->assertJsonPath('data.top_referenced_documents.0.document_name', 'returns.pdf')
            ->assertJsonPath('data.feedback_score.positive_rate', 100);
    }

    public function test_user_cannot_delete_foreign_project(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $user->countries()->sync(['DE']);
        $foreignTenant = Tenant::query()->create(['name' => 'Other Org', 'country_code' => 'FR']);
        $foreignUser = User::factory()->create();
        $foreignTenant->users()->attach($foreignUser->id, ['role' => 'owner']);

        $project = Project::query()->create([
            'tenant_id' => $foreignTenant->id,
            'country_code' => 'FR',
            'owner_id' => $foreignUser->id,
            'name' => 'Protected',
            'slug' => 'protected',
            'widget_key' => 'wk-'.str_repeat('p', 30),
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createUserWithTenant(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['name' => 'Test Tenant', 'country_code' => 'DE']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $tenant];
    }

    private function createProject(Tenant $tenant, User $user): Project
    {
        return Project::query()->create([
            'tenant_id' => $tenant->id,
            'country_code' => 'DE',
            'owner_id' => $user->id,
            'name' => 'Analytics Project',
            'slug' => 'analytics-project',
            'widget_key' => 'wk-'.strtolower(str()->random(30)),
        ]);
    }

    private function createDocumentChunk(Tenant $tenant, Project $project, User $user): DocumentChunk
    {
        $document = ProjectDocument::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'returns.pdf',
            'storage_path' => 'documents/returns.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'status' => 'processed',
        ]);

        return DocumentChunk::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'project_document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Return policy source text.',
        ]);
    }
}
