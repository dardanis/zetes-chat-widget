<?php

namespace Tests\Unit;

use App\Services\Rag\DocumentChunkingService;
use Tests\TestCase;

class DocumentChunkingServiceTest extends TestCase
{
    public function test_it_chunks_text_into_multiple_overlapping_segments(): void
    {
        config()->set('rag.chunking.target_chars', 80);
        config()->set('rag.chunking.overlap_chars', 20);
        config()->set('rag.chunking.min_chunk_chars', 20);

        $pages = [[
            'page' => 1,
            'text' => 'Alpha sentence one. Beta sentence two. Gamma sentence three. Delta sentence four. Epsilon sentence five.',
        ]];

        $chunks = app(DocumentChunkingService::class)->chunk($pages);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertArrayHasKey('content', $chunks[0]);
        $this->assertArrayHasKey('page_from', $chunks[0]);
        $this->assertArrayHasKey('page_to', $chunks[0]);
    }
}

