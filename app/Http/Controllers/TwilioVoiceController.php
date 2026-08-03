<?php

namespace App\Http\Controllers;

use App\Jobs\AnswerVoiceTurnJob;
use App\Models\ChatSession;
use App\Models\PhoneCall;
use App\Models\Project;
use App\Services\Rag\AnswerOptions;
use App\Services\Rag\ChatAnswerService;
use App\Services\Voice\TwilioNumberService;
use App\Services\Voice\VoiceResponseFormatter;
use App\Services\Voice\VoiceSettings;
use App\Services\Voice\VoiceTurnStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use Twilio\TwiML\VoiceResponse;

/**
 * Inbound voice webhooks. Each call becomes an ordinary ChatSession with channel='voice', so call
 * transcripts render through the existing chat history endpoint and broadcast over Reverb for free.
 */
class TwilioVoiceController extends Controller
{
    public function __construct(
        private readonly TwilioNumberService $numberService,
        private readonly VoiceResponseFormatter $formatter,
        private readonly VoiceTurnStore $turnStore,
        private readonly ChatAnswerService $chatAnswerService,
    ) {}

    /**
     * Twilio hits this when the call connects: resolve the project by the dialled number, register
     * the caller, and greet them.
     */
    public function incoming(Request $request): Response
    {
        $to = $this->normalizeNumber((string) $request->input('To', ''));
        $from = (string) $request->input('From', '');
        $callSid = (string) $request->input('CallSid', '');

        $project = $to === null ? null : Project::query()
            ->where('phone_number', $to)
            ->where('status', 'active')
            ->first();

        if (! $project || ! (VoiceSettings::for($project)['enabled'] ?? false)) {
            // Never reveal whether a number is assigned; the caller hears the same thing either way.
            $defaults = VoiceSettings::defaults();

            return $this->twiml($this->hangupWith($defaults['unavailable_message'], $defaults));
        }

        $settings = VoiceSettings::for($project);

        if ($callSid === '' || $from === '') {
            return $this->twiml($this->hangupWith($settings['unavailable_message'], $settings));
        }

        $existing = PhoneCall::query()->where('call_sid', $callSid)->first();

        if ($existing) {
            // Twilio retried the initial webhook; resume rather than opening a second session.
            return $this->twiml($this->gatherFor($project, $existing, $settings, $settings['greeting']));
        }

        $session = ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'title' => 'Call from '.$from,
            'channel' => 'voice',
            'metadata' => [
                'caller' => [
                    'number' => $from,
                    'country' => $request->input('FromCountry'),
                    'city' => $request->input('FromCity'),
                ],
                'call_sid' => $callSid,
                'voice_session_started_at' => now()->toIso8601String(),
            ],
        ]);

        $call = PhoneCall::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'chat_session_id' => $session->id,
            'call_sid' => $callSid,
            'from_number' => $from,
            'to_number' => $to,
            'from_country' => $request->input('FromCountry'),
            'from_city' => $request->input('FromCity'),
            'status' => 'in-progress',
            'direction' => 'inbound',
            'started_at' => now(),
            'metadata' => [
                'called_number' => $to,
                'caller_name' => $request->input('CallerName'),
            ],
        ]);

        return $this->twiml($this->gatherFor($project, $call, $settings, $settings['greeting']));
    }

    /**
     * One conversational turn: the caller has spoken and Twilio has transcribed it.
     */
    public function turn(Request $request): Response
    {
        [$call, $project, $settings] = $this->resolveCall($request);

        if (! $call || ! $project) {
            return $this->twiml($this->hangupWith(VoiceSettings::defaults()['unavailable_message'], VoiceSettings::defaults()));
        }

        $speech = trim((string) $request->input('SpeechResult', ''));
        $confidence = (float) $request->input('Confidence', 0);
        $minConfidence = (float) config('rag.voice.min_speech_confidence');
        $noInputStreak = (int) $request->query('noinput', '0');

        if ($speech === '' || ($confidence > 0 && $confidence < $minConfidence)) {
            return $this->twiml($this->handleNoInput($project, $call, $settings, $noInputStreak + 1));
        }

        if ($call->turn_count >= (int) $settings['max_turns']) {
            return $this->twiml($this->hangupWith($settings['goodbye_message'], $settings));
        }

        $turn = $call->turn_count + 1;
        $call->forceFill(['turn_count' => $turn])->save();

        $session = ChatSession::query()->find($call->chat_session_id);

        if (! $session) {
            return $this->twiml($this->hangupWith($settings['fallback_message'], $settings));
        }

        $session->messages()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'role' => 'user',
            'content' => $speech,
            'metadata' => array_filter([
                'channel' => 'voice',
                'call_sid' => $call->call_sid,
                'turn' => $turn,
                'speech_confidence' => $confidence > 0 ? round($confidence, 4) : null,
                'caller_number' => $call->from_number,
            ], static fn (mixed $value): bool => $value !== null),
        ]);

        if (! (bool) config('rag.voice.async_answer')) {
            return $this->twiml($this->answerSynchronously($project, $call, $session, $settings, $speech));
        }

        AnswerVoiceTurnJob::dispatch($call->id, $turn, $speech);

        // Park the caller in a hold loop; each iteration is a fresh sub-15s webhook, so total answer
        // time is unbounded while every individual request stays inside Twilio's limit.
        $response = new VoiceResponse;
        $this->say($response, $settings['thinking_message'], $settings);
        $response->redirect($this->action('turn/wait', ['call' => $call->call_sid, 'turn' => $turn, 'waited' => 0]), ['method' => 'POST']);

        return $this->twiml($response);
    }

    /**
     * Hold loop: redirect to self until the queued answer lands or the budget runs out.
     */
    public function wait(Request $request): Response
    {
        [$call, $project, $settings] = $this->resolveCall($request);

        if (! $call || ! $project) {
            return $this->twiml($this->hangupWith(VoiceSettings::defaults()['unavailable_message'], VoiceSettings::defaults()));
        }

        $turn = (int) $request->query('turn', (string) $call->turn_count);
        $waited = (int) $request->query('waited', '0');
        $poll = max((int) config('rag.voice.hold_poll_seconds'), 1);
        $budget = (int) config('rag.voice.answer_timeout_seconds');

        $payload = $this->turnStore->get($call->call_sid, $turn);

        if ($payload !== null) {
            $this->turnStore->forget($call->call_sid, $turn);

            if ($payload['status'] === VoiceTurnStore::STATUS_FAILED) {
                return $this->twiml($this->hangupWith($payload['answer'], $settings));
            }

            return $this->twiml($this->gatherFor($project, $call, $settings, $payload['answer']));
        }

        if ($waited >= $budget) {
            Log::warning('Voice answer exceeded the hold budget.', [
                'call_sid' => $call->call_sid,
                'turn' => $turn,
                'waited' => $waited,
            ]);

            return $this->twiml($this->hangupWith($settings['fallback_message'], $settings));
        }

        $response = new VoiceResponse;
        $response->pause(['length' => $poll]);
        $response->redirect(
            $this->action('turn/wait', ['call' => $call->call_sid, 'turn' => $turn, 'waited' => $waited + $poll]),
            ['method' => 'POST']
        );

        return $this->twiml($response);
    }

    /**
     * Call status callback: finalise the record once the call is over.
     */
    public function status(Request $request): Response
    {
        $callSid = (string) $request->input('CallSid', '');
        $call = PhoneCall::query()->where('call_sid', $callSid)->first();

        if (! $call) {
            return response()->noContent();
        }

        $status = (string) $request->input('CallStatus', $call->status);
        $duration = $request->input('CallDuration');

        $call->forceFill(array_filter([
            'status' => $status,
            'duration_seconds' => $duration !== null ? (int) $duration : $call->duration_seconds,
            'ended_at' => in_array($status, ['completed', 'failed', 'no-answer', 'busy', 'canceled'], true)
                ? now()
                : $call->ended_at,
        ], static fn (mixed $value): bool => $value !== null))->save();

        return response()->noContent();
    }

    public function recording(Request $request): Response
    {
        $call = PhoneCall::query()->where('call_sid', (string) $request->input('CallSid', ''))->first();

        if ($call) {
            $call->forceFill([
                'recording_url' => (string) $request->input('RecordingUrl', ''),
                'metadata' => array_merge($call->metadata ?? [], [
                    'recording_sid' => $request->input('RecordingSid'),
                    'recording_duration' => $request->input('RecordingDuration'),
                ]),
            ])->save();
        }

        return response()->noContent();
    }

    /**
     * Twilio's safety net when the primary handler errors out.
     */
    public function fallback(Request $request): Response
    {
        Log::error('Twilio voice fallback handler invoked.', [
            'call_sid' => $request->input('CallSid'),
            'error_code' => $request->input('ErrorCode'),
            'error_url' => $request->input('ErrorUrl'),
        ]);

        $call = PhoneCall::query()->where('call_sid', (string) $request->input('CallSid', ''))->first();
        $settings = $call?->project ? VoiceSettings::for($call->project) : VoiceSettings::defaults();

        return $this->twiml($this->hangupWith($settings['fallback_message'], $settings));
    }

    /**
     * Synchronous path, used when VOICE_ASYNC_ANSWER is off. Only safe when generation reliably
     * completes well inside Twilio's 15 second webhook timeout.
     */
    private function answerSynchronously(
        Project $project,
        PhoneCall $call,
        ChatSession $session,
        array $settings,
        string $speech,
    ): VoiceResponse {
        try {
            $result = $this->chatAnswerService->answer($project, $session, $speech, null, AnswerOptions::voice());
            $spoken = $this->formatter->format($result['message']->content);
        } catch (Throwable $exception) {
            Log::error('Synchronous voice answer failed.', [
                'call_sid' => $call->call_sid,
                'exception' => $exception->getMessage(),
            ]);

            return $this->hangupWith($settings['fallback_message'], $settings);
        }

        return $this->gatherFor($project, $call, $settings, $spoken !== '' ? $spoken : $settings['fallback_message']);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function handleNoInput(Project $project, PhoneCall $call, array $settings, int $streak): VoiceResponse
    {
        if ($streak <= (int) config('rag.voice.max_consecutive_no_input')) {
            return $this->gatherFor($project, $call, $settings, $settings['no_input_prompt'], $streak);
        }

        $transferTo = $settings['transfer_number'] ?? null;

        if (is_string($transferTo) && trim($transferTo) !== '') {
            $response = new VoiceResponse;
            $this->say($response, 'Let me pass you to a colleague.', $settings);
            $response->dial(trim($transferTo));

            return $response;
        }

        return $this->hangupWith($settings['goodbye_message'], $settings);
    }

    /**
     * Speak text and listen for the next question.
     *
     * The <Say> is nested INSIDE the <Gather> on purpose: that is what enables barge-in, letting the
     * caller interrupt a long answer with their next question instead of waiting it out.
     *
     * @param  array<string, mixed>  $settings
     */
    private function gatherFor(
        Project $project,
        PhoneCall $call,
        array $settings,
        string $text,
        int $noInputStreak = 0,
    ): VoiceResponse {
        $response = new VoiceResponse;

        $gather = $response->gather([
            'input' => 'speech',
            'action' => $this->action('turn', ['call' => $call->call_sid, 'noinput' => $noInputStreak]),
            'method' => 'POST',
            'speechTimeout' => (string) $settings['speech_timeout'],
            'language' => (string) $settings['language'],
            'actionOnEmptyResult' => true,
            'hints' => implode(', ', VoiceSettings::speechHints($project)),
        ]);

        $gather->say($text, [
            'voice' => (string) $settings['tts_voice'],
            'language' => (string) $settings['language'],
        ]);

        // Safety net if <Gather> falls through without firing its action.
        $response->redirect(
            $this->action('turn', ['call' => $call->call_sid, 'noinput' => $noInputStreak]),
            ['method' => 'POST']
        );

        return $response;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function hangupWith(string $text, array $settings): VoiceResponse
    {
        $response = new VoiceResponse;
        $this->say($response, $text, $settings);
        $response->hangup();

        return $response;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function say(VoiceResponse $response, string $text, array $settings): VoiceResponse
    {
        $response->say($text, [
            'voice' => (string) ($settings['tts_voice'] ?? config('rag.voice.default_tts_voice')),
            'language' => (string) ($settings['language'] ?? config('rag.voice.default_language')),
        ]);

        return $response;
    }

    /**
     * @return array{0: PhoneCall|null, 1: Project|null, 2: array<string, mixed>}
     */
    private function resolveCall(Request $request): array
    {
        $callSid = (string) ($request->input('CallSid') ?? $request->query('call') ?? '');
        $call = $callSid === ''
            ? null
            : PhoneCall::query()->with('project')->where('call_sid', $callSid)->first();

        $project = $call?->project;

        return [$call, $project, $project ? VoiceSettings::for($project) : VoiceSettings::defaults()];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function action(string $action, array $query = []): string
    {
        $url = $this->numberService->webhookUrl($action);

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function twiml(VoiceResponse $response): Response
    {
        return response((string) $response, 200, ['Content-Type' => 'text/xml']);
    }

    private function normalizeNumber(string $raw): ?string
    {
        try {
            return $this->numberService->normalize($raw);
        } catch (Throwable) {
            return null;
        }
    }
}
