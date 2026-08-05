<?php

namespace Tests\Unit;

use App\Services\Rag\OllamaEmbeddingService;
use App\Services\Rag\OllamaGenerationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ollama parses a string keep_alive as a Go duration, so "-1" fails with
 * `missing unit in duration` and every request 400s. The number -1 is what means "never unload".
 */
class OllamaKeepAliveTest extends TestCase
{
    public function test_numeric_keep_alive_is_sent_as_a_number_not_a_string(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => 'hello'])]);

        (new OllamaGenerationService)->generate('hi', 'gemma:2b', 10, [], '-1');

        Http::assertSent(function ($request): bool {
            $this->assertSame(-1, $request['keep_alive']);
            $this->assertIsNotString($request['keep_alive']);

            return true;
        });
    }

    public function test_duration_keep_alive_is_passed_through_as_a_string(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => 'hello'])]);

        (new OllamaGenerationService)->generate('hi', 'gemma:2b', 10, [], '30m');

        Http::assertSent(fn ($request): bool => $request['keep_alive'] === '30m');
    }

    public function test_embedding_service_normalises_keep_alive_the_same_way(): void
    {
        Http::fake(['*/api/embeddings' => Http::response(['embedding' => [0.1, 0.2]])]);

        (new OllamaEmbeddingService)->embed('hi', 10, '-1');

        Http::assertSent(fn ($request): bool => $request['keep_alive'] === -1);
    }

    public function test_keep_alive_is_omitted_when_not_requested(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => 'hello'])]);

        (new OllamaGenerationService)->generate('hi');

        Http::assertSent(fn ($request): bool => ! isset($request['keep_alive']));
    }

    public function test_voice_model_and_num_predict_reach_the_request(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => 'hello'])]);

        (new OllamaGenerationService)->generate('hi', 'gemma:2b', 10, ['num_predict' => 120]);

        Http::assertSent(function ($request): bool {
            return $request['model'] === 'gemma:2b'
                && $request['options']['num_predict'] === 120
                && $request['stream'] === false;
        });
    }

    public function test_configured_keep_alive_default_is_numeric(): void
    {
        // Guards the config cast: env always yields a string, and "-1" would 400.
        $keepAlive = config('rag.voice.model_keep_alive');

        $this->assertIsNotString(
            $keepAlive,
            'A numeric keep_alive must be cast to int in config, or Ollama rejects every request.'
        );
    }
}
