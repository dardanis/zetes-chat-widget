<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaEmbeddingService
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $response = Http::timeout(config('rag.ollama.timeout'))
            ->post(rtrim(config('rag.ollama.base_url'), '/').'/api/embeddings', [
                'model' => config('rag.ollama.embedding_model'),
                'prompt' => $text,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Embedding request to Ollama failed.');
        }

        $vector = $response->json('embedding');

        if (! is_array($vector) || $vector === []) {
            throw new RuntimeException('Embedding response from Ollama is invalid.');
        }

        return array_map(static fn ($value): float => (float) $value, $vector);
    }
}

