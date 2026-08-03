<?php

namespace Tests\Unit;

use App\Services\Rag\AnswerOptions;
use Tests\TestCase;

class AnswerOptionsTest extends TestCase
{
    public function test_default_options_preserve_the_original_widget_behaviour(): void
    {
        $options = new AnswerOptions;

        $this->assertSame('widget', $options->channel);
        $this->assertFalse($options->singlePass);
        $this->assertFalse($options->isVoice());

        // All overrides null means every existing call site keeps config defaults.
        $this->assertNull($options->model);
        $this->assertNull($options->topK);
        $this->assertNull($options->timeout);
        $this->assertNull($options->numPredict);
        $this->assertNull($options->maxContextCharsPerChunk);
        $this->assertNull($options->historyTurns);
    }

    public function test_voice_options_apply_the_latency_budget(): void
    {
        config([
            'rag.voice.generation_model' => 'gemma:2b',
            'rag.voice.retrieval_top_k' => 3,
            'rag.voice.max_context_chars_per_chunk' => 700,
            'rag.voice.history_turns' => 4,
            'rag.voice.ollama_timeout_seconds' => 30,
            'rag.voice.num_predict' => 120,
        ]);

        $options = AnswerOptions::voice();

        $this->assertTrue($options->isVoice());
        $this->assertTrue($options->singlePass, 'Voice must skip the normalising second generation.');
        $this->assertSame('gemma:2b', $options->model);
        $this->assertSame(3, $options->topK);
        $this->assertSame(700, $options->maxContextCharsPerChunk);
        $this->assertSame(4, $options->historyTurns);
        $this->assertSame(30, $options->timeout);
        $this->assertSame(120, $options->numPredict);
    }

    /**
     * The generation timeout must sit below the caller's hold budget. Inverted, a stalled
     * generation blocks past the point where anyone is still on the line — which is exactly how
     * the first live call failed.
     */
    public function test_generation_timeout_stays_inside_the_hold_budget(): void
    {
        $generation = (int) config('rag.voice.ollama_timeout_seconds');
        $holdBudget = (int) config('rag.voice.answer_timeout_seconds');

        $this->assertGreaterThan(
            0,
            $generation,
            'Voice needs its own Ollama timeout; falling back to the 120s default outlives the call.'
        );

        $this->assertLessThan(
            $holdBudget,
            $generation,
            "Generation timeout ({$generation}s) must be under the hold budget ({$holdBudget}s)."
        );
    }

    public function test_voice_retrieval_budget_is_tighter_than_the_default(): void
    {
        $this->assertLessThanOrEqual(
            (int) config('rag.retrieval.top_k'),
            (int) config('rag.voice.retrieval_top_k'),
            'Voice must not retrieve more context than the widget: prompt size drives call latency.'
        );
    }
}
