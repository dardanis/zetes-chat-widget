<?php

namespace App\Jobs;

use App\Models\DocumentChunk;
use App\Services\Rag\OllamaEmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class EmbedDocumentChunkJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $chunkId)
    {
        $this->onQueue(config('rag.queue'));
    }

    public function handle(OllamaEmbeddingService $embeddingService): void
    {
        $chunk = DocumentChunk::query()->find($this->chunkId);

        if (! $chunk) {
            return;
        }

        $embedding = $embeddingService->embed($chunk->content);

        $chunk->update([
            'embedding' => $embedding,
        ]);

        if (DB::getDriverName() === 'pgsql') {
            $vector = '['.implode(',', array_map(static fn (float $value): string => (string) $value, $embedding)).']';

            DB::table('document_chunks')
                ->where('id', $chunk->id)
                ->update([
                    'embedding_vector' => DB::raw("'{$vector}'::vector"),
                ]);
        }
    }
}

