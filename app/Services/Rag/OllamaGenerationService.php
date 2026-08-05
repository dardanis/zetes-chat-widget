<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaGenerationService
{
    /**
     * @param  array<string, mixed>  $modelOptions  Ollama `options` payload (e.g. num_predict).
     */
    public function generate(
        string $prompt,
        ?string $model = null,
        ?int $timeout = null,
        array $modelOptions = [],
        int|string|null $keepAlive = null,
    ): string {
        $payload = [
            'model' => $model ?? config('rag.ollama.generation_model'),
            'prompt' => $prompt,
            'stream' => false,
        ];

        if ($modelOptions !== []) {
            $payload['options'] = $modelOptions;
        }

        // Ollama evicts an idle model after ~5 minutes by default. For voice that means the first
        // caller after a quiet spell waits out a cold model load and hears the fallback, so the
        // voice path pins the model resident instead. Must be a number or a Go duration string.
        if ($keepAlive !== null && $keepAlive !== '') {
            $payload['keep_alive'] = is_numeric($keepAlive) ? (int) $keepAlive : $keepAlive;
        }

        $response = Http::timeout($timeout ?? (int) config('rag.ollama.timeout'))
            ->post(rtrim(config('rag.ollama.base_url'), '/').'/api/generate', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Generation request to Ollama failed.');
        }

        $content = trim((string) $response->json('response', ''));

        if ($content === '') {
            throw new RuntimeException('Generation response from Ollama is empty.');
        }

        return $content;
    }
}
