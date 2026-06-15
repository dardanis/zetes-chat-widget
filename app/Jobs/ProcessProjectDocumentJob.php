<?php

namespace App\Jobs;

use App\Models\ProjectDocument;
use App\Services\Rag\DocumentChunkingService;
use App\Services\Rag\Parsers\DocumentParserRegistry;
use App\Services\Rag\Parsers\Exceptions\DocumentParseException;
use App\Services\Rag\Parsers\Exceptions\UnsupportedDocumentTypeException;
use App\Services\Rag\Parsers\ParsedDocumentAdapter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessProjectDocumentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $projectDocumentId)
    {
        $this->onQueue(config('rag.queue'));
    }

    public function handle(DocumentParserRegistry $parsers, ParsedDocumentAdapter $adapter, DocumentChunkingService $chunker): void
    {
        $document = ProjectDocument::query()->find($this->projectDocumentId);

        if (! $document) {
            return;
        }

        $document->update(['status' => 'processing']);

        $absolutePath = Storage::disk('local')->path($document->storage_path);

        try {
            $extension = strtolower(pathinfo($document->storage_path, PATHINFO_EXTENSION));
            $parser = $parsers->resolve($extension, (string) $document->mime_type);
            $parsedUnits = $parser->parse($absolutePath, [
                'extension' => $extension,
                'mime_type' => $document->mime_type,
                'original_name' => $document->original_name,
                'project_document_id' => $document->id,
            ]);
        } catch (UnsupportedDocumentTypeException|DocumentParseException $exception) {
            $this->markAsFailed($document, $exception->getMessage(), $exception);

            return;
        } catch (Throwable $exception) {
            $this->markAsFailed($document, 'Unable to process uploaded document.', $exception);

            return;
        }

        if ($parsedUnits === []) {
            $this->markAsFailed($document, 'No readable text could be extracted from file.');

            return;
        }

        $pages = $adapter->toChunkInput($parsedUnits);
        $chunks = $chunker->chunk($pages);

        if ($chunks === []) {
            $this->markAsFailed($document, 'No readable text could be extracted from file.');

            return;
        }

        foreach ($chunks as $chunk) {
            $sourceMetadata = $adapter->metadataForRange($parsedUnits, (int) $chunk['page_from'], (int) $chunk['page_to']);

            $createdChunk = $document->chunks()->create([
                'tenant_id' => $document->tenant_id,
                'project_id' => $document->project_id,
                'chunk_index' => $chunk['chunk_index'],
                'page_from' => $chunk['page_from'],
                'page_to' => $chunk['page_to'],
                'content' => $chunk['content'],
                'metadata' => array_merge($chunk['metadata'], $sourceMetadata, [
                    'chunk_index' => $chunk['chunk_index'],
                ]),
            ]);

            EmbedDocumentChunkJob::dispatch($createdChunk->id);
        }

        $document->update([
            'status' => 'indexed',
            'metadata' => array_merge(is_array($document->metadata) ? $document->metadata : [], [
                'pages_count' => count($pages),
                'source_units_count' => count($parsedUnits),
                'chunks_count' => count($chunks),
                'file_type' => $extension,
                'last_status' => 'ok',
                'last_error' => null,
            ]),
            'processed_at' => now(),
        ]);
    }

    private function markAsFailed(ProjectDocument $document, string $message, ?Throwable $exception = null): void
    {
        if ($exception) {
            Log::warning('Project document processing failed.', [
                'project_document_id' => $document->id,
                'exception' => get_class($exception),
                'error' => $exception->getMessage(),
            ]);
        }

        $document->update([
            'status' => 'failed',
            'metadata' => array_merge(is_array($document->metadata) ? $document->metadata : [], [
                'last_status' => 'failed',
                'last_error' => $message,
            ]),
            'processed_at' => now(),
        ]);
    }
}
