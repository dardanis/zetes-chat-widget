<?php

namespace Tests\Feature;

use App\Jobs\CrawlProjectWebsiteJob;
use App\Jobs\EmbedDocumentChunkJob;
use App\Jobs\ProcessProjectDocumentJob;
use App\Jobs\ResyncConfluenceDocumentJob;
use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rag\DocumentChunkingService;
use App\Services\Rag\OllamaEmbeddingService;
use App\Services\Rag\PdfTextExtractorService;
use App\Services\Rag\WebsiteCrawlerService;
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

    public function test_user_can_queue_single_confluence_document_resync(): void
    {
        Queue::fake();

        [$user, $project] = $this->createProjectContext();

        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'Confluence page',
            'storage_path' => 'rag/confluence/page.txt',
            'mime_type' => 'text/html',
            'file_size' => 120,
            'status' => 'indexed',
            'ingestion_type' => 'confluence',
            'source_url' => 'https://example.atlassian.net/wiki/spaces/ENG/pages/123',
            'metadata' => [
                'provider' => 'confluence',
                'space_key' => 'ENG',
                'external_page_id' => '123',
            ],
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/projects/'.$project->id.'/documents/'.$document->id.'/resync-confluence');

        $response->assertAccepted();
        $response->assertJsonPath('data.document_id', $document->id);

        $this->assertDatabaseHas('project_documents', [
            'id' => $document->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ResyncConfluenceDocumentJob::class, function (ResyncConfluenceDocumentJob $job) use ($document): bool {
            return $job->projectDocumentId === $document->id;
        });
    }

    public function test_non_confluence_document_cannot_be_resynced_as_single_page(): void
    {
        Queue::fake();

        [$user, $project] = $this->createProjectContext();

        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'handbook.pdf',
            'storage_path' => 'rag/documents/handbook.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 120,
            'status' => 'indexed',
            'ingestion_type' => 'pdf',
        ]);

        $this->actingAs($user)
            ->postJson('/api/projects/'.$project->id.'/documents/'.$document->id.'/resync-confluence')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only Confluence documents can be re-synced individually.');

        Queue::assertNotPushed(ResyncConfluenceDocumentJob::class);
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

    public function test_starting_website_crawl_queues_job(): void
    {
        Queue::fake();

        [$user, $project] = $this->createProjectContext();

        $response = $this->actingAs($user)->postJson('/api/projects/'.$project->id.'/crawl', [
            'url' => 'https://example.com',
            'max_pages' => 10,
        ]);

        $response->assertAccepted();

        Queue::assertPushed(CrawlProjectWebsiteJob::class, function (CrawlProjectWebsiteJob $job) use ($project, $user): bool {
            return $job->projectId === $project->id
                && $job->userId === $user->id
                && $job->startUrl === 'https://example.com'
                && $job->maxPages === 10;
        });
    }

    public function test_crawl_job_indexes_crawled_pages_as_project_documents(): void
    {
        Queue::fake();
        Storage::fake('local');

        [$user, $project] = $this->createProjectContext();

        $crawlerService = Mockery::mock(WebsiteCrawlerService::class);
        $crawlerService->shouldReceive('crawlSite')->once()->andReturn([
            [
                'url' => 'https://example.com',
                'title' => 'Home',
                'content' => 'Welcome to the example documentation site.',
            ],
            [
                'url' => 'https://example.com/docs',
                'title' => 'Docs',
                'content' => 'Install the product and configure the environment.',
            ],
        ]);

        $chunker = Mockery::mock(DocumentChunkingService::class);
        $chunker->shouldReceive('chunk')->twice()->andReturn([
            [
                'chunk_index' => 0,
                'content' => 'Crawled page chunk',
                'page_from' => 1,
                'page_to' => 1,
                'metadata' => ['strategy' => 'test'],
            ],
        ]);

        (new CrawlProjectWebsiteJob($project->id, $user->id, 'https://example.com', 10))
            ->handle($crawlerService, $chunker);

        $this->assertDatabaseHas('project_documents', [
            'project_id' => $project->id,
            'ingestion_type' => 'web',
            'source_url' => 'https://example.com',
            'status' => 'indexed',
        ]);

        $this->assertDatabaseHas('project_documents', [
            'project_id' => $project->id,
            'ingestion_type' => 'web',
            'source_url' => 'https://example.com/docs',
            'status' => 'indexed',
        ]);

        Queue::assertPushed(EmbedDocumentChunkJob::class, 2);
    }

    public function test_user_can_list_crawled_urls_for_project(): void
    {
        [$user, $project] = $this->createProjectContext();

        ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'handbook.pdf',
            'storage_path' => 'rag/documents/handbook.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'status' => 'indexed',
            'ingestion_type' => 'pdf',
        ]);

        ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'Example docs',
            'storage_path' => 'rag/crawled/example.txt',
            'mime_type' => 'text/html',
            'file_size' => 64,
            'status' => 'indexed',
            'ingestion_type' => 'web',
            'source_url' => 'https://example.com/docs',
            'metadata' => ['chunks_count' => 3, 'title' => 'Example docs'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/projects/'.$project->id.'/crawled-urls');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.url', 'https://example.com/docs');
        $response->assertJsonPath('data.0.status', 'indexed');
        $response->assertJsonPath('data.0.chunks_count', 3);
    }

    public function test_user_can_view_document_chunk_content_preview(): void
    {
        [$user, $project] = $this->createProjectContext();

        $document = ProjectDocument::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'preview.pdf',
            'storage_path' => 'rag/documents/preview.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'status' => 'indexed',
            'ingestion_type' => 'pdf',
        ]);

        DocumentChunk::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'project_document_id' => $document->id,
            'chunk_index' => 0,
            'page_from' => 1,
            'page_to' => 1,
            'content' => 'First extracted paragraph.',
            'metadata' => ['strategy' => 'test'],
        ]);

        DocumentChunk::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'project_document_id' => $document->id,
            'chunk_index' => 1,
            'page_from' => 2,
            'page_to' => 2,
            'content' => 'Second extracted paragraph.',
            'metadata' => ['strategy' => 'test'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/projects/'.$project->id.'/documents/'.$document->id.'/content?per_page=1&page=2');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.chunk_index', 1);
        $response->assertJsonPath('data.0.content', 'Second extracted paragraph.');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.last_page', 2);
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

