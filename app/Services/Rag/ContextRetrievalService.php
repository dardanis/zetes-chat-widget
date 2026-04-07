<?php

namespace App\Services\Rag;

use App\Models\DocumentChunk;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class ContextRetrievalService
{
    /**
     * @param  array<int, float>  $queryEmbedding
     * @return array<int, array<string, mixed>>
     */
    public function retrieve(Project $project, array $queryEmbedding): array
    {
        $topK = (int) config('rag.retrieval.top_k');
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $vector = '['.implode(',', array_map(static fn (float $value): string => (string) $value, $queryEmbedding)).']';

            return DocumentChunk::query()
                ->select('document_chunks.*')
                ->selectRaw('embedding_vector <=> ?::vector as similarity_distance', [$vector])
                ->with('projectDocument:id,original_name')
                ->where('tenant_id', $project->tenant_id)
                ->where('project_id', $project->id)
                ->whereNotNull('embedding_vector')
                ->orderBy('similarity_distance')
                ->limit($topK)
                ->get()
                ->map(function (DocumentChunk $chunk): array {
                    return [
                        'chunk_id' => $chunk->id,
                        'document_id' => $chunk->project_document_id,
                        'document_name' => $chunk->projectDocument?->original_name,
                        'page_from' => $chunk->page_from,
                        'page_to' => $chunk->page_to,
                        'excerpt' => mb_substr($chunk->content, 0, 320),
                        'content' => $chunk->content,
                        'score' => 1 - (float) ($chunk->similarity_distance ?? 1),
                    ];
                })->all();
        }

        return DocumentChunk::query()
            ->with('projectDocument:id,original_name')
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->limit($topK)
            ->get()
            ->map(function (DocumentChunk $chunk): array {
                return [
                    'chunk_id' => $chunk->id,
                    'document_id' => $chunk->project_document_id,
                    'document_name' => $chunk->projectDocument?->original_name,
                    'page_from' => $chunk->page_from,
                    'page_to' => $chunk->page_to,
                    'excerpt' => mb_substr($chunk->content, 0, 320),
                    'content' => $chunk->content,
                    'score' => null,
                ];
            })->all();
    }
}

