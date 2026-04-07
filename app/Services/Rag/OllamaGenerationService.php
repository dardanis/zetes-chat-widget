<?php

namespace App\Services\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaGenerationService
{
    public function generate(string $prompt): string
    {
        $response = Http::timeout(config('rag.ollama.timeout'))
            ->post(rtrim(config('rag.ollama.base_url'), '/').'/api/generate', [
                'model' => config('rag.ollama.generation_model'),
                'prompt' => $prompt,
                'stream' => false,
            ]);

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

