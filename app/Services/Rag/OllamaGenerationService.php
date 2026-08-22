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

            // Reasoning models put their chain of thought in a separate `thinking` field and leave
            // `response` empty until they are done. Nothing here ever reads `thinking`, so with a
            // tight num_predict (voice uses 80) the budget is spent entirely on hidden reasoning
            // and Ollama returns 200 with an empty `response` and done_reason=length -- which
            // surfaces as the useless "response is empty" error below. Models without the thinking
            // capability accept and ignore the flag, so it is safe to send unconditionally.
            'think' => false,
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
            // Carry the model and Ollama's own stop reason: an empty answer is otherwise
            // indistinguishable between a bad model choice, an exhausted token budget, and a
            // refusal, and none of that is recoverable from the message alone.
            throw new RuntimeException(sprintf(
                'Generation response from Ollama is empty (model=%s, done_reason=%s, eval_count=%s, thinking=%d chars).',
                $payload['model'],
                (string) $response->json('done_reason', 'unknown'),
                (string) $response->json('eval_count', '?'),
                mb_strlen((string) $response->json('thinking', '')),
            ));
        }

        return $content;
    }
}
