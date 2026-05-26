<?php

namespace Tests\Feature;

use App\Jobs\EmbedDocumentChunkJob;
use App\Jobs\ProcessProjectDocumentJob;
use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rag\DocumentChunkingService;
use App\Services\Rag\OllamaEmbeddingService;
use App\Services\Rag\PdfTextExtractorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProjectDocumentIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_a_pdf_queues_document_processing(): void
    {
        Queue::fake();
        Storage::fake('local');

        [$user, $project] = $this->createProjectContext();

        $response = $this->actingAs($user)->post('/api/projects/'.$project->id.'/documents', [
            'file' => UploadedFile::fake()->create('handbook.pdf', 64, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertAccepted();

        $documentId = $response->json('data.id');

        $this->assertDatabaseHas('project_documents', [
            'id' => $documentId,
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'handbook.pdf',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessProjectDocumentJob::class, function (ProcessProjectDocumentJob $job) use ($documentId): bool {
            return $job->projectDocumentId === $documentId;
        });
    }

    public function test_processing_job_creates_chunks_and_queues_embeddings(): void
    {
        Queue::fake();
        Storage::fake('local');

        [$user, $project] = $this->createProjectContext();
        Storage::disk('local')->put('rag/documents/sample.pdf', 'fake-pdf-content');

        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'sample.pdf',
            'storage_path' => 'rag/documents/sample.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'status' => 'pending',
        ]);

        $extractor = Mockery::mock(PdfTextExtractorService::class);
        $extractor->shouldReceive('extractByPage')->once()->andReturn([
            ['page' => 1, 'text' => 'Introduction to the platform.'],
            ['page' => 2, 'text' => 'Installation and setup details.'],
        ]);

        $chunker = Mockery::mock(DocumentChunkingService::class);
        $chunker->shouldReceive('chunk')->once()->andReturn([
            [
                'chunk_index' => 0,
                'content' => 'Introduction to the platform.',
                'page_from' => 1,
                'page_to' => 1,
                'metadata' => ['strategy' => 'test'],
            ],
            [
                'chunk_index' => 1,
                'content' => 'Installation and setup details.',
                'page_from' => 2,
                'page_to' => 2,
                'metadata' => ['strategy' => 'test'],
            ],
        ]);

        (new ProcessProjectDocumentJob($document->id))->handle($extractor, $chunker);

        $document->refresh();

        $this->assertSame('indexed', $document->status);
        $this->assertSame(2, $document->chunks()->count());
        $this->assertSame(2, $document->metadata['chunks_count']);
        $this->assertSame(2, $document->metadata['pages_count']);

        $this->assertDatabaseHas('document_chunks', [
            'project_document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Introduction to the platform.',
        ]);

        Queue::assertPushed(EmbedDocumentChunkJob::class, 2);
    }

    public function test_embedding_job_persists_embedding_payload_on_chunk(): void
    {
        [$user, $project] = $this->createProjectContext();

        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'vectorized.pdf',
            'storage_path' => 'rag/documents/vectorized.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 256,
            'status' => 'indexed',
        ]);

        $chunk = DocumentChunk::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'project_document_id' => $document->id,
            'chunk_index' => 0,
            'page_from' => 1,
            'page_to' => 1,
            'content' => 'Embeddings should be stored for retrieval.',
        ]);

        $embeddingService = Mockery::mock(OllamaEmbeddingService::class);
        $embeddingService->shouldReceive('embed')->once()->andReturn([0.11, 0.22, 0.33]);

        (new EmbedDocumentChunkJob($chunk->id))->handle($embeddingService);

        $chunk->refresh();

        $this->assertSame([0.11, 0.22, 0.33], $chunk->embedding);
    }

    public function test_user_can_delete_a_project_document(): void
    {
        Storage::fake('local');

        [$user, $project] = $this->createProjectContext();

        Storage::disk('local')->put('rag/documents/delete-me.pdf', 'fake-pdf-content');

        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'delete-me.pdf',
            'storage_path' => 'rag/documents/delete-me.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'status' => 'indexed',
        ]);

        $chunk = DocumentChunk::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'project_document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Chunk to be removed with the document.',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson('/api/projects/'.$project->id.'/documents/'.$document->id);

        $response->assertNoContent();

        $this->assertDatabaseMissing('project_documents', [
            'id' => $document->id,
        ]);

        $this->assertDatabaseMissing('document_chunks', [
            'id' => $chunk->id,
        ]);

        Storage::disk('local')->assertMissing('rag/documents/delete-me.pdf');
    }

    public function test_user_cannot_delete_document_from_foreign_project(): void
    {
        Storage::fake('local');

        [$owner, $project] = $this->createProjectContext();

        $intruder = User::factory()->create();
        $otherTenant = Tenant::query()->create(['name' => 'Tenant Beta']);
        $otherTenant->users()->attach($intruder->id, ['role' => 'owner']);

        Storage::disk('local')->put('rag/documents/protected.pdf', 'fake-pdf-content');

        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'protected.pdf',
            'storage_path' => 'rag/documents/protected.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'status' => 'indexed',
        ]);

        $this->actingAs($intruder)
            ->deleteJson('/api/projects/'.$project->id.'/documents/'.$document->id)
            ->assertNotFound();

        $this->assertDatabaseHas('project_documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertExists('rag/documents/protected.pdf');
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
        $tenant = Tenant::query()->create(['name' => 'Tenant Alpha']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'name' => 'Docs Project',
            'slug' => 'docs-project',
            'widget_key' => 'widget-key-'.str_repeat('b', 25),
        ]);

        return [$user, $project];
    }
}

