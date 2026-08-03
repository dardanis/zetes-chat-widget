<?php

namespace App\Services\Rag;

/**
 * Per-channel tuning for ChatAnswerService. Defaults reproduce the original two-pass widget
 * behaviour so existing call sites are unaffected.
 */
class AnswerOptions
{
    public function __construct(
        public readonly string $channel = 'widget',
        public readonly bool $singlePass = false,
        public readonly ?int $maxChars = null,
        public readonly ?string $model = null,
        public readonly ?int $topK = null,
        public readonly ?int $maxContextCharsPerChunk = null,
        public readonly ?int $historyTurns = null,
        public readonly ?int $timeout = null,
        public readonly ?int $numPredict = null,
        public readonly int|string|null $keepAlive = null,
    ) {}

    /**
     * Voice trades answer breadth for latency, because a caller is waiting on the line:
     *
     * - single pass: the normalising second generation exists to prettify prose for a chat bubble,
     *   and a voice-tuned prompt already produces speakable output. Halves the round trips.
     * - fewer, shorter chunks: prompt processing dominates total latency, so this is the biggest
     *   lever available.
     * - a smaller model and a short timeout, so a slow answer fails inside the hold budget.
     */
    public static function voice(): self
    {
        return new self(
            channel: 'voice',
            singlePass: true,
            maxChars: (int) config('rag.voice.max_answer_chars'),
            model: (string) config('rag.voice.generation_model'),
            topK: (int) config('rag.voice.retrieval_top_k'),
            maxContextCharsPerChunk: (int) config('rag.voice.max_context_chars_per_chunk'),
            historyTurns: (int) config('rag.voice.history_turns'),
            timeout: (int) config('rag.voice.ollama_timeout_seconds'),
            numPredict: (int) config('rag.voice.num_predict'),
            keepAlive: config('rag.voice.model_keep_alive'),
        );
    }

    public function isVoice(): bool
    {
        return $this->channel === 'voice';
    }
}
