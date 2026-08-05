<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaEmbeddingService
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text, ?int $timeout = null, int|string|null $keepAlive = null): array
    {
        $payload = [
            'model' => config('rag.ollama.embedding_model'),
            'prompt' => $text,
        ];

        // See OllamaGenerationService: keeps the embedding model resident between calls so a live
        // caller does not pay a cold load. Must be a number or a Go duration string.
        if ($keepAlive !== null && $keepAlive !== '') {
            $payload['keep_alive'] = is_numeric($keepAlive) ? (int) $keepAlive : $keepAlive;
        }

        $response = Http::timeout($timeout ?? (int) config('rag.ollama.timeout'))
            ->post(rtrim(config('rag.ollama.base_url'), '/').'/api/embeddings', $payload);

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
