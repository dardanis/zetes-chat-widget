<?php

namespace Tests\Feature;

use App\Events\ProjectChatMessageCreated;
use App\Models\ChatSession;
use App\Models\DocumentChunk;
use App\Models\ProjectDocument;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rag\ChatAnswerService;
use App\Models\ChatMessage;
use App\Services\Rag\ContextRetrievalService;
use App\Services\Rag\OllamaEmbeddingService;
use App\Services\Rag\OllamaGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

        $userHistoryMessage = new ChatMessage();
        $userHistoryMessage->tenant_id = $project->tenant_id;
        $userHistoryMessage->project_id = $project->id;
        $userHistoryMessage->chat_session_id = $session->id;
        $userHistoryMessage->user_id = $user->id;
        $userHistoryMessage->role = 'user';
        $userHistoryMessage->content = 'What is this about?';
        $userHistoryMessage->save();

        $assistantHistoryMessage = new ChatMessage();
        $assistantHistoryMessage->tenant_id = $project->tenant_id;
        $assistantHistoryMessage->project_id = $project->id;
        $assistantHistoryMessage->chat_session_id = $session->id;
        $assistantHistoryMessage->role = 'assistant';
        $assistantHistoryMessage->content = 'This is a test response.';
        $assistantHistoryMessage->save();

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

    public function test_send_message_dispatches_realtime_broadcast_event(): void
    {
        Event::fake([ProjectChatMessageCreated::class]);

        [$user, $project] = $this->createProjectContext();

        $session = ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Realtime Chat',
            'channel' => 'dashboard',
        ]);

        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'guide.pdf',
            'storage_path' => 'rag/documents/guide.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'status' => 'indexed',
        ]);

        $chunk = DocumentChunk::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'project_document_id' => $document->id,
            'chunk_index' => 0,
            'page_from' => 2,
            'page_to' => 3,
            'content' => 'The platform supports realtime warehouse updates and audit trails.',
        ]);

        $embeddingService = Mockery::mock(OllamaEmbeddingService::class);
        $embeddingService->shouldReceive('embed')
            ->once()
            ->with('Do we support realtime updates?')
            ->andReturn([0.15, 0.42, 0.73]);

        $retrievalService = Mockery::mock(ContextRetrievalService::class);
        $retrievalService->shouldReceive('retrieve')
            ->once()
            ->withArgs(fn (Project $resolvedProject, string $question, array $embedding): bool => $resolvedProject->is($project) && $question === 'Do we support realtime updates?' && $embedding === [0.15, 0.42, 0.73])
            ->andReturn([
                [
                    'chunk_id' => $chunk->id,
                    'document_id' => $document->id,
                    'document_name' => $document->original_name,
                    'page_from' => 2,
                    'page_to' => 3,
                    'excerpt' => 'The platform supports realtime warehouse updates and audit trails.',
                    'content' => 'The platform supports realtime warehouse updates and audit trails.',
                    'score' => 0.98,
                ],
            ]);

        $generationService = Mockery::mock(OllamaGenerationService::class);
        $generationService->shouldReceive('generate')
            ->once()
            ->andReturn('Draft answer based on retrieved evidence.');
        $generationService->shouldReceive('generate')
            ->once()
            ->andReturn('Yes. The platform supports realtime updates and audit trails according to the uploaded guide.');

        $this->app->instance(OllamaEmbeddingService::class, $embeddingService);
        $this->app->instance(ContextRetrievalService::class, $retrievalService);
        $this->app->instance(OllamaGenerationService::class, $generationService);

        $response = $this->actingAs($user)
            ->postJson("/api/projects/{$project->id}/chats/message", [
                'chat_session_id' => $session->id,
                'message' => 'Do we support realtime updates?',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.assistant_message.content', 'Yes. The platform supports realtime updates and audit trails according to the uploaded guide.')
            ->assertJsonPath('data.citations.0.document_name', 'guide.pdf')
            ->assertJsonPath('data.citations.0.chunk_id', $chunk->id);

        $assistantMessageId = (int) $response->json('data.assistant_message.id');

        $this->assertDatabaseHas('message_citations', [
            'chat_message_id' => $assistantMessageId,
            'document_chunk_id' => $chunk->id,
        ]);

        Event::assertDispatched(ProjectChatMessageCreated::class, function (ProjectChatMessageCreated $event) use ($project, $session, $assistantMessageId, $chunk): bool {
            return $event->projectId === $project->id
                && $event->chatSessionId === $session->id
                && ($event->payload['assistant_message']['id'] ?? null) === $assistantMessageId
                && ($event->payload['citations'][0]['chunk_id'] ?? null) === $chunk->id
                && ($event->payload['citations'][0]['document_name'] ?? null) === 'guide.pdf';
        });
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

