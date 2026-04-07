<?php

namespace App\Jobs;

use App\Models\ProjectDocument;
use App\Services\Rag\DocumentChunkingService;
use App\Services\Rag\PdfTextExtractorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessProjectDocumentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $projectDocumentId)
    {
        $this->onQueue(config('rag.queue'));
    }

    public function handle(PdfTextExtractorService $extractor, DocumentChunkingService $chunker): void
    {
        $document = ProjectDocument::query()->find($this->projectDocumentId);

        if (! $document) {
            return;
        }

        $document->update(['status' => 'processing']);

        $absolutePath = Storage::disk('local')->path($document->storage_path);
        $pages = $extractor->extractByPage($absolutePath);
        $chunks = $chunker->chunk($pages);

        foreach ($chunks as $chunk) {
            $createdChunk = $document->chunks()->create([
                'tenant_id' => $document->tenant_id,
                'project_id' => $document->project_id,
                'chunk_index' => $chunk['chunk_index'],
                'page_from' => $chunk['page_from'],
                'page_to' => $chunk['page_to'],
                'content' => $chunk['content'],
                'metadata' => $chunk['metadata'],
            ]);

            EmbedDocumentChunkJob::dispatch($createdChunk->id);
        }

        $document->update([
            'status' => 'indexed',
            'metadata' => [
                'pages_count' => count($pages),
                'chunks_count' => count($chunks),
            ],
            'processed_at' => now(),
        ]);
    }
}

