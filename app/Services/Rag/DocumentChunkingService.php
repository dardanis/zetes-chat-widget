<?php

namespace App\Services\Rag;

class DocumentChunkingService
{
    /**
     * @param  array<int, array{page:int, text:string}>  $pages
     * @return array<int, array{chunk_index:int, content:string, page_from:int, page_to:int, metadata:array<string,mixed>}>
     */
    public function chunk(array $pages): array
    {
        $targetChars = (int) config('rag.chunking.target_chars');
        $overlapChars = (int) config('rag.chunking.overlap_chars');
        $minChars = (int) config('rag.chunking.min_chunk_chars');

        $chunks = [];
        $carry = '';
        $carryPageFrom = null;

        foreach ($pages as $pageData) {
            $pageNumber = $pageData['page'];
            $pageText = trim($pageData['text']);
            $sentences = preg_split('/(?<=[.!?])\s+/u', $pageText) ?: [$pageText];

            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);

                if ($sentence === '') {
                    continue;
                }

                if ($carry === '') {
                    $carry = $sentence;
                    $carryPageFrom = $pageNumber;
                    continue;
                }

                $candidate = $carry.' '.$sentence;

                if (mb_strlen($candidate) <= $targetChars) {
                    $carry = $candidate;
                    continue;
                }

                if (mb_strlen($carry) >= $minChars) {
                    $chunks[] = [
                        'chunk_index' => count($chunks),
                        'content' => $carry,
                        'page_from' => $carryPageFrom ?? $pageNumber,
                        'page_to' => $pageNumber,
                        'metadata' => [
                            'strategy' => 'sentence_window',
                        ],
                    ];
                }

                $overlap = mb_substr($carry, max(0, mb_strlen($carry) - $overlapChars));
                $carry = trim($overlap.' '.$sentence);
                $carryPageFrom = $pageNumber;
            }
        }

        if ($carry !== '') {
            $chunks[] = [
                'chunk_index' => count($chunks),
                'content' => $carry,
                'page_from' => $carryPageFrom ?? 1,
                'page_to' => $pages === [] ? 1 : (int) end($pages)['page'],
                'metadata' => [
                    'strategy' => 'sentence_window',
                ],
            ];
        }

        return $chunks;
    }
}

