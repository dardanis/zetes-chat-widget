<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rag\ChatAnswerService;
use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProjectChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_chat_session(): void
    {
        [$user, $project] = $this->createProjectContext();

        $response = $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/chats", [
                'title' => 'Test Chat',
                'channel' => 'dashboard',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Test Chat')
            ->assertJsonPath('data.channel', 'dashboard')
            ->assertJsonPath('data.project_id', $project->id);

        $this->assertDatabaseHas('chat_sessions', [
            'project_id' => $project->id,
            'title' => 'Test Chat',
        ]);
    }

    public function test_user_can_list_chat_sessions_for_project(): void
    {
        [$user, $project] = $this->createProjectContext();

        ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Session A',
            'channel' => 'dashboard',
        ]);

        ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Session B',
            'channel' => 'dashboard',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/projects/{$project->id}/chats");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_get_chat_history(): void
    {
        [$user, $project] = $this->createProjectContext();

        $session = ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'History Chat',
            'channel' => 'dashboard',
        ]);

        $session->messages()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'What is this about?',
        ]);

        $session->messages()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'role' => 'assistant',
            'content' => 'This is a test response.',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/projects/{$project->id}/chats/{$session->id}/history");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.role', 'user')
            ->assertJsonPath('data.1.role', 'assistant');
    }

    public function test_user_cannot_access_chats_from_foreign_project(): void
    {
        $intruder = User::factory()->create();
        $tenantB = Tenant::query()->create(['name' => 'Other Tenant']);
        $tenantB->users()->attach($intruder->id, ['role' => 'owner']);

        [$owner, $project] = $this->createProjectContext();

        $this->actingAs($intruder)
            ->getJson("/api/projects/{$project->id}/chats")
            ->assertNotFound();
    }

    public function test_user_can_send_message_to_chat_session(): void
    {
        [$user, $project] = $this->createProjectContext();

        $session = ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Message Chat',
            'channel' => 'dashboard',
        ]);

        $mockAnswer = Mockery::mock(ChatAnswerService::class);
        $mockAnswer->shouldReceive('answer')->once()->andReturn([
            'message' => ChatMessage::query()->create([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'chat_session_id' => $session->id,
                'role' => 'assistant',
                'content' => 'Mocked response.',
                'model' => 'llama3',
            ]),
            'citations' => [],
        ]);
        $this->app->instance(ChatAnswerService::class, $mockAnswer);

        $response = $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/chats/message", [
                'chat_session_id' => $session->id,
                'message' => 'Hello, what is in the documents?',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['chat_session_id', 'user_message', 'assistant_message', 'citations'],
            ]);

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => 'Hello, what is in the documents?',
        ]);
    }

    public function test_send_message_requires_session_id_and_message(): void
    {
        [$user, $project] = $this->createProjectContext();

        $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/chats/message", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['chat_session_id', 'message']);
    }

    public function test_unauthenticated_user_cannot_access_chat_endpoints(): void
    {
        $this->getJson('/api/projects/1/chats')
            ->assertUnauthorized();

        $this->postJson('/api/projects/1/chats', ['title' => 'Nope'])
            ->assertUnauthorized();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function createProjectContext(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['name' => 'Chat Tenant']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'name' => 'Chat Project',
            'slug' => 'chat-project',
            'widget_key' => 'widget-key-' . str_repeat('c', 25),
        ]);

        return [$user, $project];
    }
}

