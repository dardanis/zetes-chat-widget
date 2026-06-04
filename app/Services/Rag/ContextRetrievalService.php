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
    public function retrieve(Project $project, string $question, array $queryEmbedding): array
    {
        $topK = max((int) config('rag.retrieval.top_k'), 1);
        $needle = $this->buildNeedle($question);
        $terms = $this->extractTerms($question);
        $preferConfluence = $this->shouldPreferConfluence($question);

        $query = DocumentChunk::query()
            ->with('projectDocument:id,original_name,ingestion_type')
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->when($preferConfluence, static function ($query): void {
                $query->whereHas('projectDocument', static function ($subQuery): void {
                    $subQuery->where('ingestion_type', 'confluence');
                });
            });

        if (DB::getDriverName() === 'pgsql' && $queryEmbedding !== []) {
            $vector = '['.implode(',', array_map(static fn (float $value): string => (string) $value, $queryEmbedding)).']';

            $candidates = $query
                ->select('document_chunks.*')
                ->selectRaw('embedding_vector <=> ?::vector as similarity_distance', [$vector])
                ->whereNotNull('embedding_vector')
                ->orderBy('similarity_distance')
                ->limit(max($topK * 8, 24))
                ->get();

            return $this->rankCandidates($candidates, $queryEmbedding, $needle, $terms, $topK, true, $preferConfluence);
        }

        $candidates = $query
            ->whereNotNull('embedding')
            ->orderByDesc('id')
            ->limit(max($topK * 20, 40))
            ->get();

        return $this->rankCandidates($candidates, $queryEmbedding, $needle, $terms, $topK, false, $preferConfluence);
    }

    private function shouldPreferConfluence(string $question): bool
    {
        $text = mb_strtolower($question);

        return str_contains($text, 'confluence')
            || str_contains($text, 'atlassian')
            || str_contains($text, 'wiki')
            || str_contains($text, 'knowledge base');
    }

    private function buildNeedle(string $question): string
    {
        $needle = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $question) ?? ''));

        return mb_substr($needle, 0, 120);
    }

    /**
     * @return array<int, string>
     */
    private function extractTerms(string $question): array
    {
        $terms = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($terms, static fn (string $term): bool => mb_strlen($term) >= 3));
    }

    /**
     * @param  iterable<DocumentChunk>  $chunks
     * @param  array<int, float>  $queryEmbedding
     * @param  array<int, string>  $terms
     * @return array<int, array<string, mixed>>
     */
    private function rankCandidates(iterable $chunks, array $queryEmbedding, string $needle, array $terms, int $topK, bool $vectorAlreadySorted, bool $preferConfluence): array
    {
        $results = [];

        foreach ($chunks as $chunk) {
            $content = (string) $chunk->content;
            $contentLower = mb_strtolower($content);
            $semanticScore = $this->semanticScore($chunk->embedding ?? null, $queryEmbedding);
            $vectorScore = $vectorAlreadySorted && isset($chunk->similarity_distance)
                ? max(0.0, 1 - (float) $chunk->similarity_distance)
                : 0.0;
            $keywordScore = $this->keywordScore($contentLower, $needle, $terms);
            $sourceBoost = $preferConfluence && ($chunk->projectDocument?->ingestion_type ?? null) === 'confluence' ? 0.05 : 0.0;

            $score = min(1.0, (max($semanticScore, $vectorScore) * 0.45) + ($keywordScore * 0.55) + $sourceBoost);

            $results[] = [
                'chunk_id' => $chunk->id,
                'document_id' => $chunk->project_document_id,
                'document_name' => $chunk->projectDocument?->original_name,
                'page_from' => $chunk->page_from,
                'page_to' => $chunk->page_to,
                'excerpt' => mb_substr($content, 0, 320),
                'content' => $content,
                'score' => $score,
            ];
        }

        usort($results, static function (array $left, array $right): int {
            return ($right['score'] <=> $left['score']) ?: ($right['chunk_id'] <=> $left['chunk_id']);
        });

        return array_slice($results, 0, $topK);
    }

    private function keywordScore(string $contentLower, string $needle, array $terms): float
    {
        $score = 0.0;

        if ($needle !== '' && str_contains($contentLower, $needle)) {
            $score += 0.65;
        }

        foreach ($terms as $term) {
            if (str_contains($contentLower, $term)) {
                $score += 0.1;
            }
        }

        return min(1.0, $score);
    }

    /**
     * @param  array<int, float>|null  $chunkEmbedding
     */
    private function semanticScore(?array $chunkEmbedding, array $queryEmbedding): float
    {
        if ($chunkEmbedding === null || $chunkEmbedding === [] || $queryEmbedding === []) {
            return 0.0;
        }

        $length = min(count($chunkEmbedding), count($queryEmbedding));
        $dot = 0.0;
        $chunkNorm = 0.0;
        $queryNorm = 0.0;

        for ($index = 0; $index < $length; $index++) {
            $chunkValue = (float) $chunkEmbedding[$index];
            $queryValue = (float) $queryEmbedding[$index];
            $dot += $chunkValue * $queryValue;
            $chunkNorm += $chunkValue * $chunkValue;
            $queryNorm += $queryValue * $queryValue;
        }

        if ($chunkNorm <= 0.0 || $queryNorm <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($chunkNorm) * sqrt($queryNorm))));
    }
}
