<?php
namespace App\Jobs;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\Rag\DocumentChunkingService;
use App\Services\Rag\WebsiteCrawlerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
class CrawlProjectWebsiteJob implements ShouldQueue
{
    use Queueable;
    public function __construct(
        public int $projectId,
        public int $userId,
        public string $startUrl,
        public int $maxPages = 20,
    ) {
        $this->onQueue(config('rag.queue'));
    }
    public function handle(WebsiteCrawlerService $crawlerService, DocumentChunkingService $chunker): void
    {
        $project = Project::query()->find($this->projectId);
        $user = User::query()->find($this->userId);
        if (! $project || ! $user) {
            return;
        }
        try {
            $pages = $crawlerService->crawlSite(
                $this->startUrl,
                min(max($this->maxPages, 1), (int) config('rag.crawler.max_pages', 40)),
            );
            foreach ($pages as $page) {
                $this->indexCrawledPage($project, $user->id, $page, $chunker);
            }
        } catch (Throwable $exception) {
            ProjectDocument::query()->create([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'uploaded_by' => $user->id,
                'original_name' => parse_url($this->startUrl, PHP_URL_HOST) ?: 'Website crawl failed',
                'storage_path' => 'rag/crawled/failed-'.Str::uuid().'.txt',
                'mime_type' => 'text/html',
                'file_size' => 0,
                'status' => 'failed',
                'ingestion_type' => 'web',
                'source_url' => $this->startUrl,
                'metadata' => [
                    'source_url' => $this->startUrl,
                    'error' => $exception->getMessage(),
                ],
            ]);
        }
    }
    /**
     * @param  array{url:string,title:string,content:string}  $page
     */
    private function indexCrawledPage(Project $project, int $userId, array $page, DocumentChunkingService $chunker): void
    {
        $storagePath = 'rag/crawled/'.Str::uuid().'.txt';
        Storage::disk('local')->put($storagePath, $page['content']);
        $document = ProjectDocument::query()->firstOrNew([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'source_url' => $page['url'],
        ]);
        $document->fill([
            'uploaded_by' => $userId,
            'original_name' => $page['title'] !== '' ? $page['title'] : $page['url'],
            'storage_path' => $storagePath,
            'mime_type' => 'text/html',
            'file_size' => strlen($page['content']),
            'status' => 'processing',
            'ingestion_type' => 'web',
            'source_url' => $page['url'],
            'metadata' => [
                'source_url' => $page['url'],
                'title' => $page['title'],
                'crawl_seed_url' => $this->startUrl,
            ],
        ]);
        $document->save();
        $document->chunks()->delete();
        $chunks = $chunker->chunk([
            ['page' => 1, 'text' => $page['content']],
        ]);
        foreach ($chunks as $chunk) {
            $createdChunk = $document->chunks()->create([
                'tenant_id' => $document->tenant_id,
                'project_id' => $document->project_id,
                'chunk_index' => $chunk['chunk_index'],
                'page_from' => $chunk['page_from'],
                'page_to' => $chunk['page_to'],
                'content' => $chunk['content'],
                'metadata' => array_merge($chunk['metadata'], [
                    'source_url' => $page['url'],
                    'title' => $page['title'],
                ]),
            ]);
            EmbedDocumentChunkJob::dispatch($createdChunk->id);
        }
        $document->update([
            'status' => 'indexed',
            'metadata' => [
                'source_url' => $page['url'],
                'title' => $page['title'],
                'crawl_seed_url' => $this->startUrl,
                'chunks_count' => count($chunks),
                'pages_count' => 1,
                'crawled_at' => now()->toIso8601String(),
            ],
            'processed_at' => now(),
        ]);
    }
}
