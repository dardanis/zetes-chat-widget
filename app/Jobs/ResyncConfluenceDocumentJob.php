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

class ResyncConfluenceDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $projectDocumentId)
    {
        $this->onQueue(config('rag.queue'));
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(ConfluenceApiService $confluence, DocumentChunkingService $chunker): void
    {
        $document = ProjectDocument::query()->find($this->projectDocumentId);

        if (! $document || $document->ingestion_type !== 'confluence') {
            return;
        }

        $metadata = is_array($document->metadata) ? $document->metadata : [];
        $externalPageId = (string) data_get($metadata, 'external_page_id', '');
        $spaceKey = (string) data_get($metadata, 'space_key', '');

        if ($externalPageId === '' || $spaceKey === '') {
            $this->markAsFailed($document, 'Missing Confluence page metadata for resync.');

            return;
        }

        $selectedSpace = ProjectConfluenceSpace::query()
            ->with(['connection', 'project'])
            ->where('tenant_id', $document->tenant_id)
            ->where('project_id', $document->project_id)
            ->where('space_key', $spaceKey)
            ->where('is_enabled', true)
            ->latest('id')
            ->first();

        if (! $selectedSpace || ! $selectedSpace->connection || ! $selectedSpace->project || ! $selectedSpace->connection->is_active) {
            $this->markAsFailed($document, 'Confluence connection for this page is not available.');

            return;
        }

        $page = $confluence->getPageBody($selectedSpace->connection, $externalPageId);

        if (! is_array($page)) {
            $this->markAsFailed($document, 'Unable to fetch Confluence page body during resync.');

            return;
        }

        $plainText = $confluence->toPlainText((string) $page['body_html']);

        if ($plainText === '') {
            $this->markAsFailed($document, 'Confluence page body is empty during resync.');

            return;
        }

        $embeddingText = $confluence->buildEmbeddingText(
            $plainText,
            (string) ($page['title'] ?? ''),
            (string) $selectedSpace->space_key,
            (string) $selectedSpace->space_name,
            (string) ($page['url'] ?? $document->source_url ?? ''),
        );

        $storagePath = 'rag/confluence/'.Str::uuid()->toString().'.txt';
        Storage::disk('local')->put($storagePath, $embeddingText);

        $uploadedBy = $selectedSpace->selected_by
            ?? $selectedSpace->connection->created_by
            ?? $selectedSpace->project->owner_id;

        $document->update([
            'uploaded_by' => $uploadedBy,
            'original_name' => (string) ($page['title'] ?: $selectedSpace->space_name),
            'storage_path' => $storagePath,
            'mime_type' => 'text/html',
            'file_size' => strlen($embeddingText),
            'status' => 'processing',
            'source_url' => (string) ($page['url'] ?: $document->source_url),
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

        $document->chunks()->delete();

        $chunks = $chunker->chunk([
            ['page' => 1, 'text' => $embeddingText],
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

        $selectedSpace->update([
            'last_synced_at' => now(),
            'metadata' => [
                'last_status' => 'ok',
            ],
        ]);
    }

    private function markAsFailed(ProjectDocument $document, string $error): void
    {
        $metadata = is_array($document->metadata) ? $document->metadata : [];

        $document->update([
            'status' => 'failed',
            'metadata' => array_filter([
                'provider' => 'confluence',
                'space_key' => data_get($metadata, 'space_key'),
                'space_name' => data_get($metadata, 'space_name'),
                'external_page_id' => data_get($metadata, 'external_page_id'),
                'external_updated_at' => data_get($metadata, 'external_updated_at'),
                'synced_external_updated_at' => data_get($metadata, 'synced_external_updated_at', data_get($metadata, 'external_updated_at')),
                'latest_external_updated_at' => data_get($metadata, 'latest_external_updated_at', data_get($metadata, 'external_updated_at')),
                'chunks_count' => (int) data_get($metadata, 'chunks_count', 0),
                'pages_count' => (int) data_get($metadata, 'pages_count', 1),
                'synced_at' => data_get($metadata, 'synced_at'),
                'last_status' => 'failed',
                'last_error' => $error,
                'failed_at' => now()->toIso8601String(),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);
    }
}

