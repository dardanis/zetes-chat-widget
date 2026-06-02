<?php

namespace App\Jobs;

use App\Models\ProjectConfluenceSpace;
use App\Models\ProjectDocument;
use App\Services\Rag\ConfluenceApiService;
use App\Services\Rag\DocumentChunkingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SyncProjectConfluenceSpaceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $projectConfluenceSpaceId)
    {
        $this->onQueue(config('rag.queue'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(ConfluenceApiService $confluence, DocumentChunkingService $chunker): void
    {
        $selectedSpace = ProjectConfluenceSpace::query()
            ->with(['connection', 'project'])
            ->find($this->projectConfluenceSpaceId);

        if (! $selectedSpace || ! $selectedSpace->is_enabled || ! $selectedSpace->connection || ! $selectedSpace->project) {
            return;
        }

        if (! $selectedSpace->connection->is_active) {
            return;
        }

        $pages = $confluence->listPagesForSpace($selectedSpace->connection, $selectedSpace->space_key);

        foreach ($pages as $pageSummary) {
            $page = $confluence->getPageBody($selectedSpace->connection, $pageSummary['id']);

            if (! is_array($page)) {
                continue;
            }

            $plainText = $confluence->toPlainText((string) $page['body_html']);

            if ($plainText === '') {
                continue;
            }

            $document = ProjectDocument::query()->firstOrNew([
                'tenant_id' => $selectedSpace->tenant_id,
                'project_id' => $selectedSpace->project_id,
                'ingestion_type' => 'confluence',
                'source_url' => $page['url'],
            ]);

            $existingUpdatedAt = (string) data_get($document->metadata, 'external_updated_at', '');
            $incomingUpdatedAt = (string) ($page['updated_at'] ?? '');

            if ($document->exists && $document->status === 'indexed' && $existingUpdatedAt !== '' && $existingUpdatedAt === $incomingUpdatedAt) {
                continue;
            }

            $storagePath = 'rag/confluence/'.Str::uuid()->toString().'.txt';
            Storage::disk('local')->put($storagePath, $plainText);

            $uploadedBy = $selectedSpace->selected_by
                ?? $selectedSpace->connection->created_by
                ?? $selectedSpace->project->owner_id;

            $document->fill([
                'uploaded_by' => $uploadedBy,
                'original_name' => (string) ($page['title'] ?: $selectedSpace->space_name),
                'storage_path' => $storagePath,
                'mime_type' => 'text/html',
                'file_size' => strlen($plainText),
                'status' => 'processing',
                'ingestion_type' => 'confluence',
                'source_url' => $page['url'],
                'metadata' => [
                    'provider' => 'confluence',
                    'space_key' => $selectedSpace->space_key,
                    'space_name' => $selectedSpace->space_name,
                    'external_page_id' => $page['id'],
                    'external_updated_at' => $page['updated_at'],
                ],
            ]);
            $document->save();

            $document->chunks()->delete();

            $chunks = $chunker->chunk([
                ['page' => 1, 'text' => $plainText],
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
                        'provider' => 'confluence',
                        'space_key' => $selectedSpace->space_key,
                        'external_page_id' => $page['id'],
                    ]),
                ]);

                EmbedDocumentChunkJob::dispatch($createdChunk->id);
            }

            $document->update([
                'status' => 'indexed',
                'metadata' => [
                    'provider' => 'confluence',
                    'space_key' => $selectedSpace->space_key,
                    'space_name' => $selectedSpace->space_name,
                    'external_page_id' => $page['id'],
                    'external_updated_at' => $page['updated_at'],
                    'chunks_count' => count($chunks),
                    'pages_count' => 1,
                    'synced_at' => now()->toIso8601String(),
                ],
                'processed_at' => now(),
            ]);
        }

        $selectedSpace->update([
            'last_synced_at' => now(),
            'metadata' => [
                'last_status' => 'ok',
            ],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        ProjectConfluenceSpace::query()
            ->whereKey($this->projectConfluenceSpaceId)
            ->update([
                'metadata' => [
                    'last_status' => 'failed',
                    'error' => $exception->getMessage(),
                ],
            ]);
    }
}

