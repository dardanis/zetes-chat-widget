<?php

namespace App\Jobs;

use App\Models\ChatSession;
use App\Models\PhoneCall;
use App\Services\Rag\AnswerOptions;
use App\Services\Rag\ChatAnswerService;
use App\Services\Voice\VoiceResponseFormatter;
use App\Services\Voice\VoiceSettings;
use App\Services\Voice\VoiceTurnStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates the spoken answer for one turn of a live call, off the webhook request cycle so that a
 * slow Ollama generation can never trip Twilio's 15 second webhook timeout.
 */
class AnswerVoiceTurnJob implements ShouldQueue
{
    use Queueable;

    /**
     * One attempt only. A retry costs another full generation timeout, by which point the caller
     * has already heard the fallback and hung up.
     */
    public int $tries = 1;

    public function __construct(
        public int $phoneCallId,
        public int $turn,
        public string $question,
    ) {
        $this->onQueue(config('rag.voice.queue'));
        // Slightly beyond the caller's hold budget so a job that is merely slow still finishes and
        // warms the cache. Note this is advisory on Windows, which has no pcntl to enforce it —
        // rag.voice.ollama_timeout_seconds is the real backstop.
        $this->timeout = (int) config('rag.voice.answer_timeout_seconds') + 15;
    }

    public function handle(
        ChatAnswerService $chatAnswerService,
        VoiceResponseFormatter $formatter,
        VoiceTurnStore $store,
    ): void {
        $call = PhoneCall::query()->with('project')->find($this->phoneCallId);

        if (! $call || ! $call->project || ! $call->chat_session_id) {
            return;
        }

        $session = ChatSession::query()->find($call->chat_session_id);

        if (! $session) {
            return;
        }

        $result = $chatAnswerService->answer(
            $call->project,
            $session,
            $this->question,
            null,
            AnswerOptions::voice(),
        );

        $spoken = $formatter->format($result['message']->content);

        if ($spoken === '') {
            $spoken = VoiceSettings::for($call->project)['fallback_message'];
        }

        $store->putReady($call->call_sid, $this->turn, $spoken, $result['message']->id);
    }

    /**
     * Degrade the call gracefully instead of leaving the caller in the hold loop until it times out.
     */
    public function failed(?Throwable $exception): void
    {
        $call = PhoneCall::query()->with('project')->find($this->phoneCallId);

        Log::error('Voice turn answer failed.', [
            'phone_call_id' => $this->phoneCallId,
            'turn' => $this->turn,
            'exception' => $exception?->getMessage(),
        ]);

        if (! $call) {
            return;
        }

        $message = $call->project
            ? VoiceSettings::for($call->project)['fallback_message']
            : 'Sorry, something went wrong. Please try again later.';

        app(VoiceTurnStore::class)->putFailed($call->call_sid, $this->turn, $message);
    }
}
