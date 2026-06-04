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
            $document = ProjectDocument::query()->firstOrNew([
                'tenant_id' => $selectedSpace->tenant_id,
                'project_id' => $selectedSpace->project_id,
                'ingestion_type' => 'confluence',
                'source_url' => (string) ($pageSummary['url'] ?? ''),
            ]);

            $existingUpdatedAt = (string) data_get($document->metadata, 'synced_external_updated_at', data_get($document->metadata, 'external_updated_at', ''));
            $incomingUpdatedAt = (string) ($pageSummary['updated_at'] ?? '');

            if ($document->exists && $document->status === 'indexed' && $existingUpdatedAt !== '' && $existingUpdatedAt === $incomingUpdatedAt) {
                continue;
            }

            try {
                $page = $confluence->getPageBody($selectedSpace->connection, (string) ($pageSummary['id'] ?? ''));

                if (! is_array($page)) {
                    $this->markDocumentAsFailed(
                        $document,
                        $selectedSpace,
                        $pageSummary,
                        'Unable to fetch Confluence page body.'
                    );

                    continue;
                }

                $plainText = $confluence->toPlainText((string) $page['body_html']);

                if ($plainText === '') {
                    $this->markDocumentAsFailed(
                        $document,
                        $selectedSpace,
                        $pageSummary,
                        'Confluence page body is empty.'
                    );

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
                    'source_url' => (string) ($page['url'] ?: data_get($pageSummary, 'url', '')),
                    'metadata' => [
                        'provider' => 'confluence',
                        'space_key' => $selectedSpace->space_key,
                        'space_name' => $selectedSpace->space_name,
                        'external_page_id' => $page['id'],
                        'external_updated_at' => $page['updated_at'],
                        'synced_external_updated_at' => $page['updated_at'],
                        'latest_external_updated_at' => $page['updated_at'],
                        'last_status' => 'processing',
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
                        'synced_external_updated_at' => $page['updated_at'],
                        'latest_external_updated_at' => $page['updated_at'],
                        'chunks_count' => count($chunks),
                        'pages_count' => 1,
                        'synced_at' => now()->toIso8601String(),
                        'last_status' => 'ok',
                    ],
                    'processed_at' => now(),
                ]);
            } catch (Throwable $exception) {
                $this->markDocumentAsFailed(
                    $document,
                    $selectedSpace,
                    $pageSummary,
                    $exception->getMessage()
                );
            }
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

    /**
     * @param  array{id:string,title:string,url:string,updated_at:string|null}  $pageSummary
     */
    private function markDocumentAsFailed(
        ProjectDocument $document,
        ProjectConfluenceSpace $selectedSpace,
        array $pageSummary,
        string $errorMessage
    ): void {
        $existingMetadata = is_array($document->metadata) ? $document->metadata : [];
        $latestUpdatedAt = (string) ($pageSummary['updated_at'] ?? '');

        $document->fill([
            'original_name' => (string) ($pageSummary['title'] ?: $selectedSpace->space_name),
            'status' => 'failed',
            'ingestion_type' => 'confluence',
            'source_url' => (string) ($pageSummary['url'] ?? $document->source_url ?? ''),
            'metadata' => array_filter([
                'provider' => 'confluence',
                'space_key' => $selectedSpace->space_key,
                'space_name' => $selectedSpace->space_name,
                'external_page_id' => (string) ($pageSummary['id'] ?? data_get($existingMetadata, 'external_page_id', '')),
                // Keep last successful external version and track latest discovered version separately.
                'external_updated_at' => data_get($existingMetadata, 'external_updated_at'),
                'synced_external_updated_at' => data_get($existingMetadata, 'synced_external_updated_at', data_get($existingMetadata, 'external_updated_at')),
                'latest_external_updated_at' => $latestUpdatedAt !== '' ? $latestUpdatedAt : data_get($existingMetadata, 'latest_external_updated_at'),
                'chunks_count' => (int) data_get($existingMetadata, 'chunks_count', 0),
                'pages_count' => (int) data_get($existingMetadata, 'pages_count', 1),
                'synced_at' => data_get($existingMetadata, 'synced_at'),
                'last_status' => 'failed',
                'last_error' => $errorMessage,
                'failed_at' => now()->toIso8601String(),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);

        $document->save();
    }
}
