<?php

namespace App\Console\Commands;

use App\Services\Rag\OllamaEmbeddingService;
use App\Services\Rag\OllamaGenerationService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Loads the voice models into memory and pins them there.
 *
 * Ollama loads a model on first use, which on modest hardware takes longer than a caller will
 * wait — the first call after a restart would otherwise hear the fallback message. Run this
 * alongside the queue worker before enabling the phone channel.
 */
class WarmVoiceModelsCommand extends Command
{
    protected $signature = 'voice:warm';

    protected $description = 'Preload and pin the Ollama models used by the voice channel';

    public function handle(
        OllamaGenerationService $generation,
        OllamaEmbeddingService $embedding,
    ): int {
        $model = (string) config('rag.voice.generation_model');
        $embeddingModel = (string) config('rag.ollama.embedding_model');
        $keepAlive = config('rag.voice.model_keep_alive');
        // Loading a cold model far outlives a single turn's budget, so allow generous time here.
        $timeout = max((int) config('rag.ollama.timeout'), 120);

        $this->info("Warming voice models (keep_alive: {$this->describeKeepAlive($keepAlive)})");

        $failed = false;

        $failed = ! $this->warm(
            "embedding model [{$embeddingModel}]",
            fn () => $embedding->embed('warm up', $timeout, $keepAlive),
        ) || $failed;

        $failed = ! $this->warm(
            "generation model [{$model}]",
            fn () => $generation->generate('Say OK.', $model, $timeout, ['num_predict' => 5], $keepAlive),
        ) || $failed;

        if ($failed) {
            $this->error('One or more models failed to load. Is Ollama running on '.config('rag.ollama.base_url').'?');

            return self::FAILURE;
        }

        $this->info('Voice models are resident. Calls will not pay a cold start.');

        return self::SUCCESS;
    }

    private function warm(string $label, callable $callback): bool
    {
        $this->line("  loading {$label}...");
        $started = microtime(true);

        try {
            $callback();
        } catch (Throwable $exception) {
            $this->error(sprintf('  failed after %.1fs: %s', microtime(true) - $started, $exception->getMessage()));

            return false;
        }

        $this->line(sprintf('  ready in %.1fs', microtime(true) - $started));

        return true;
    }

    private function describeKeepAlive(int|string|null $keepAlive): string
    {
        return match (true) {
            $keepAlive === null || $keepAlive === '' => 'Ollama default',
            is_int($keepAlive) && $keepAlive < 0 => 'indefinite',
            default => (string) $keepAlive,
        };
    }
}
