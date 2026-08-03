<?php

namespace App\Services\Voice;

use Illuminate\Support\Facades\Cache;

/**
 * Hand-off between AnswerVoiceTurnJob and the hold-loop webhook. Owns the cache key convention so
 * the job and the controller cannot drift apart.
 */
class VoiceTurnStore
{
    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public function key(string $callSid, int $turn): string
    {
        return sprintf('voice:answer:%s:%d', $callSid, $turn);
    }

    public function putReady(string $callSid, int $turn, string $answer, ?int $messageId = null): void
    {
        $this->put($callSid, $turn, [
            'status' => self::STATUS_READY,
            'answer' => $answer,
            'message_id' => $messageId,
        ]);
    }

    public function putFailed(string $callSid, int $turn, string $message): void
    {
        $this->put($callSid, $turn, [
            'status' => self::STATUS_FAILED,
            'answer' => $message,
            'message_id' => null,
        ]);
    }

    /**
     * @return array{status:string, answer:string, message_id:int|null}|null
     */
    public function get(string $callSid, int $turn): ?array
    {
        $payload = Cache::get($this->key($callSid, $turn));

        return is_array($payload) ? $payload : null;
    }

    public function forget(string $callSid, int $turn): void
    {
        Cache::forget($this->key($callSid, $turn));
    }

    /**
     * @param  array{status:string, answer:string, message_id:int|null}  $payload
     */
    private function put(string $callSid, int $turn, array $payload): void
    {
        // Generous margin over the hold-loop budget: the caller may still be mid-redirect when the
        // job lands, and a stale key costs nothing.
        $ttl = (int) config('rag.voice.answer_timeout_seconds') + 60;

        Cache::put($this->key($callSid, $turn), $payload, $ttl);
    }
}
