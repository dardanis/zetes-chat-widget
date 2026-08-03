<?php

namespace Tests\Feature;

use App\Jobs\AnswerVoiceTurnJob;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\PhoneCall;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rag\ChatAnswerService;
use App\Services\Voice\VoiceTurnStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class TwilioVoiceCallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Signature validation is exercised in its own test; the rest post raw payloads.
        config([
            'services.twilio.validate_signature' => false,
            'services.twilio.auth_token' => 'test-auth-token',
            'services.twilio.webhook_base_url' => 'https://voice.test',
        ]);
    }

    public function test_incoming_call_registers_caller_and_opens_voice_session(): void
    {
        $project = $this->createVoiceProject();

        $response = $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA1000',
            'From' => '+38344111222',
            'To' => $project->phone_number,
            'FromCountry' => 'XK',
            'FromCity' => 'Pristina',
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');

        $this->assertDatabaseHas('chat_sessions', [
            'project_id' => $project->id,
            'channel' => 'voice',
            'title' => 'Call from +38344111222',
        ]);

        $this->assertDatabaseHas('phone_calls', [
            'project_id' => $project->id,
            'call_sid' => 'CA1000',
            'from_number' => '+38344111222',
            'from_country' => 'XK',
            'status' => 'in-progress',
        ]);

        // The caller number is also mirrored onto the session so the chat UI needs no join.
        $session = ChatSession::query()->where('project_id', $project->id)->firstOrFail();
        $this->assertSame('+38344111222', $session->metadata['caller']['number']);
        $this->assertSame('CA1000', $session->metadata['call_sid']);
    }

    public function test_greeting_twiml_gathers_speech_with_nested_say_for_barge_in(): void
    {
        $project = $this->createVoiceProject();

        $body = $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA1001',
            'From' => '+38344111222',
            'To' => $project->phone_number,
        ])->assertOk()->getContent();

        $this->assertStringContainsString('<Gather', $body);
        $this->assertStringContainsString('input="speech"', $body);
        $this->assertStringContainsString('actionOnEmptyResult="true"', $body);
        $this->assertStringContainsString('https://voice.test/api/twilio/voice/turn', $body);

        // <Say> must be nested inside <Gather>: that is what allows the caller to barge in.
        $this->assertMatchesRegularExpression('#<Gather[^>]*>\s*<Say[^>]*>#', $body);
        $this->assertStringContainsString('How can I help you today?', $body);
    }

    public function test_retried_incoming_webhook_does_not_open_a_second_session(): void
    {
        $project = $this->createVoiceProject();

        $payload = [
            'CallSid' => 'CA1002',
            'From' => '+38344111222',
            'To' => $project->phone_number,
        ];

        $this->post('/api/twilio/voice/incoming', $payload)->assertOk();
        $this->post('/api/twilio/voice/incoming', $payload)->assertOk();

        $this->assertSame(1, PhoneCall::query()->where('call_sid', 'CA1002')->count());
        $this->assertSame(1, ChatSession::query()->where('project_id', $project->id)->count());
    }

    public function test_turn_stores_user_message_and_queues_the_answer(): void
    {
        Queue::fake();

        $project = $this->createVoiceProject();
        $call = $this->startCall($project, 'CA2000');

        $body = $this->post('/api/twilio/voice/turn?call=CA2000', [
            'CallSid' => 'CA2000',
            'SpeechResult' => 'What are your opening hours?',
            'Confidence' => '0.94',
        ])->assertOk()->getContent();

        Queue::assertPushed(AnswerVoiceTurnJob::class, function (AnswerVoiceTurnJob $job) use ($call): bool {
            return $job->phoneCallId === $call->id
                && $job->turn === 1
                && $job->question === 'What are your opening hours?';
        });

        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $call->chat_session_id,
            'role' => 'user',
            'content' => 'What are your opening hours?',
        ]);

        $this->assertSame(1, $call->fresh()->turn_count);

        // Caller is parked in the hold loop rather than waiting on a slow generation.
        $this->assertStringContainsString('<Redirect', $body);
        $this->assertStringContainsString('turn/wait', $body);
        $this->assertStringContainsString('One moment', $body);
    }

    public function test_wait_speaks_the_answer_once_the_job_has_landed(): void
    {
        $project = $this->createVoiceProject();
        $call = $this->startCall($project, 'CA2001', turnCount: 1);

        app(VoiceTurnStore::class)->putReady('CA2001', 1, 'We are open from nine to five.');

        $body = $this->post('/api/twilio/voice/turn/wait?call=CA2001&turn=1&waited=0', [
            'CallSid' => 'CA2001',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('We are open from nine to five.', $body);
        $this->assertStringContainsString('<Gather', $body);

        // Consumed, so a Twilio retry cannot replay the same answer.
        $this->assertNull(app(VoiceTurnStore::class)->get('CA2001', 1));
    }

    public function test_wait_loops_while_the_answer_is_pending(): void
    {
        $project = $this->createVoiceProject();
        $this->startCall($project, 'CA2002', turnCount: 1);

        $body = $this->post('/api/twilio/voice/turn/wait?call=CA2002&turn=1&waited=0', [
            'CallSid' => 'CA2002',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('<Pause', $body);
        $this->assertStringContainsString('waited=2', $body);
        $this->assertStringNotContainsString('<Gather', $body);
    }

    public function test_wait_gives_up_with_the_fallback_message_after_the_budget(): void
    {
        $project = $this->createVoiceProject();
        $this->startCall($project, 'CA2003', turnCount: 1);

        $body = $this->post('/api/twilio/voice/turn/wait?call=CA2003&turn=1&waited=999', [
            'CallSid' => 'CA2003',
        ])->assertOk()->getContent();

        $this->assertStringContainsString("I'm having trouble right now", $body);
        $this->assertStringContainsString('<Hangup', $body);
    }

    public function test_failed_answer_ends_the_call_gracefully(): void
    {
        $project = $this->createVoiceProject();
        $this->startCall($project, 'CA2004', turnCount: 1);

        app(VoiceTurnStore::class)->putFailed('CA2004', 1, 'Something went wrong.');

        $body = $this->post('/api/twilio/voice/turn/wait?call=CA2004&turn=1&waited=0', [
            'CallSid' => 'CA2004',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('Something went wrong.', $body);
        $this->assertStringContainsString('<Hangup', $body);
    }

    public function test_empty_speech_result_reprompts_the_caller(): void
    {
        Queue::fake();

        $project = $this->createVoiceProject();
        $call = $this->startCall($project, 'CA3000');

        $body = $this->post('/api/twilio/voice/turn?call=CA3000&noinput=0', [
            'CallSid' => 'CA3000',
            'SpeechResult' => '',
        ])->assertOk()->getContent();

        $this->assertStringContainsString("didn't catch that", $body);
        $this->assertStringContainsString('noinput=1', $body);
        $this->assertSame(0, $call->fresh()->turn_count);
        Queue::assertNothingPushed();
    }

    public function test_repeated_no_input_ends_the_call(): void
    {
        $project = $this->createVoiceProject();
        $this->startCall($project, 'CA3001');

        $body = $this->post('/api/twilio/voice/turn?call=CA3001&noinput=2', [
            'CallSid' => 'CA3001',
            'SpeechResult' => '',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('Thanks for calling', $body);
        $this->assertStringContainsString('<Hangup', $body);
    }

    public function test_repeated_no_input_transfers_when_a_transfer_number_is_set(): void
    {
        $project = $this->createVoiceProject(['transfer_number' => '+38344999888']);
        $this->startCall($project, 'CA3002');

        $body = $this->post('/api/twilio/voice/turn?call=CA3002&noinput=5', [
            'CallSid' => 'CA3002',
            'SpeechResult' => '',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('<Dial>+38344999888</Dial>', $body);
    }

    public function test_low_confidence_speech_is_treated_as_no_input(): void
    {
        Queue::fake();

        $project = $this->createVoiceProject();
        $this->startCall($project, 'CA3003');

        $body = $this->post('/api/twilio/voice/turn?call=CA3003&noinput=0', [
            'CallSid' => 'CA3003',
            'SpeechResult' => 'mumble mumble',
            'Confidence' => '0.05',
        ])->assertOk()->getContent();

        $this->assertStringContainsString("didn't catch that", $body);
        Queue::assertNothingPushed();
    }

    public function test_call_hangs_up_once_max_turns_is_reached(): void
    {
        Queue::fake();

        $project = $this->createVoiceProject(['max_turns' => 3]);
        $this->startCall($project, 'CA4000', turnCount: 3);

        $body = $this->post('/api/twilio/voice/turn?call=CA4000', [
            'CallSid' => 'CA4000',
            'SpeechResult' => 'One more question',
            'Confidence' => '0.9',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('Thanks for calling', $body);
        $this->assertStringContainsString('<Hangup', $body);
        Queue::assertNothingPushed();
    }

    public function test_synchronous_mode_speaks_the_answer_immediately(): void
    {
        config(['rag.voice.async_answer' => false]);

        $project = $this->createVoiceProject();
        $call = $this->startCall($project, 'CA5000');

        $this->mock(ChatAnswerService::class, function (Mockery\MockInterface $mock) use ($project, $call): void {
            $message = ChatMessage::query()->forceCreate([
                'tenant_id' => $project->tenant_id,
                'project_id' => $project->id,
                'chat_session_id' => $call->chat_session_id,
                'role' => 'assistant',
                // Markdown and a URL that must never be spoken verbatim.
                'content' => "**We are open** from nine to five.\n- See https://example.com/hours for details.",
            ]);

            $mock->shouldReceive('answer')->once()->andReturn([
                'message' => $message,
                'citations' => [],
            ]);
        });

        $body = $this->post('/api/twilio/voice/turn?call=CA5000', [
            'CallSid' => 'CA5000',
            'SpeechResult' => 'When are you open?',
            'Confidence' => '0.9',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('We are open from nine to five.', $body);
        $this->assertStringNotContainsString('**', $body);
        $this->assertStringNotContainsString('https://example.com', $body);
        $this->assertStringContainsString('the link on our website', $body);
    }

    public function test_status_callback_finalises_the_call_record(): void
    {
        $project = $this->createVoiceProject();
        $this->startCall($project, 'CA6000');

        $this->post('/api/twilio/voice/status', [
            'CallSid' => 'CA6000',
            'CallStatus' => 'completed',
            'CallDuration' => '84',
        ])->assertNoContent();

        $call = PhoneCall::query()->where('call_sid', 'CA6000')->firstOrFail();

        $this->assertSame('completed', $call->status);
        $this->assertSame(84, $call->duration_seconds);
        $this->assertNotNull($call->ended_at);
    }

    public function test_recording_callback_stores_the_recording_url(): void
    {
        $project = $this->createVoiceProject();
        $this->startCall($project, 'CA6001');

        $this->post('/api/twilio/voice/recording', [
            'CallSid' => 'CA6001',
            'RecordingUrl' => 'https://api.twilio.com/recordings/RE1',
            'RecordingSid' => 'RE1',
            'RecordingDuration' => '84',
        ])->assertNoContent();

        $this->assertSame(
            'https://api.twilio.com/recordings/RE1',
            PhoneCall::query()->where('call_sid', 'CA6001')->value('recording_url')
        );
    }

    public function test_unknown_number_is_rejected_without_creating_a_session(): void
    {
        $body = $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA7000',
            'From' => '+38344111222',
            'To' => '+38344000000',
        ])->assertOk()->getContent();

        $this->assertStringContainsString('not available', $body);
        $this->assertStringContainsString('<Hangup', $body);
        $this->assertDatabaseCount('chat_sessions', 0);
        $this->assertDatabaseCount('phone_calls', 0);
    }

    public function test_disabled_voice_channel_is_rejected(): void
    {
        $project = $this->createVoiceProject(['enabled' => false]);

        $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA7001',
            'From' => '+38344111222',
            'To' => $project->phone_number,
        ])->assertOk();

        $this->assertDatabaseCount('phone_calls', 0);
    }

    public function test_inactive_project_is_rejected(): void
    {
        $project = $this->createVoiceProject();
        $project->update(['status' => 'inactive']);

        $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA7002',
            'From' => '+38344111222',
            'To' => $project->phone_number,
        ])->assertOk();

        $this->assertDatabaseCount('phone_calls', 0);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.twilio.validate_signature' => true]);

        $project = $this->createVoiceProject();

        $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA8000',
            'From' => '+38344111222',
            'To' => $project->phone_number,
        ], ['X-Twilio-Signature' => 'clearly-not-valid'])->assertForbidden();

        $this->assertDatabaseCount('phone_calls', 0);
    }

    public function test_missing_signature_is_rejected(): void
    {
        config(['services.twilio.validate_signature' => true]);

        $project = $this->createVoiceProject();

        $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA8001',
            'From' => '+38344111222',
            'To' => $project->phone_number,
        ])->assertForbidden();
    }

    public function test_correct_signature_is_accepted(): void
    {
        config(['services.twilio.validate_signature' => true]);

        $project = $this->createVoiceProject();
        $payload = [
            'CallSid' => 'CA8002',
            'From' => '+38344111222',
            'To' => $project->phone_number,
        ];

        $validator = new RequestValidator('test-auth-token');
        $signature = $validator->computeSignature('https://voice.test/api/twilio/voice/incoming', $payload);

        $this->post('/api/twilio/voice/incoming', $payload, ['X-Twilio-Signature' => $signature])
            ->assertOk();

        $this->assertDatabaseHas('phone_calls', ['call_sid' => 'CA8002']);
    }

    public function test_a_call_cannot_reach_another_projects_number(): void
    {
        $projectA = $this->createVoiceProject();
        $projectB = $this->createVoiceProject(phoneNumber: '+38344555666', name: 'Project B');

        $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA9000',
            'From' => '+38344111222',
            'To' => $projectB->phone_number,
        ])->assertOk();

        $call = PhoneCall::query()->where('call_sid', 'CA9000')->firstOrFail();

        $this->assertSame($projectB->id, $call->project_id);
        $this->assertSame($projectB->tenant_id, $call->tenant_id);
        $this->assertNotSame($projectA->id, $call->project_id);
    }

    public function test_number_is_matched_regardless_of_formatting(): void
    {
        $project = $this->createVoiceProject();

        $this->post('/api/twilio/voice/incoming', [
            'CallSid' => 'CA9001',
            'From' => '+38344111222',
            // Twilio normally sends E.164, but a 00-prefixed or spaced form must still resolve.
            'To' => '0038344 123 456',
        ])->assertOk();

        $this->assertDatabaseHas('phone_calls', [
            'call_sid' => 'CA9001',
            'project_id' => $project->id,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $voiceSettings
     */
    private function createVoiceProject(
        array $voiceSettings = [],
        string $phoneNumber = '+38344123456',
        string $name = 'Voice Project',
    ): Project {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['name' => $name.' Tenant']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        return Project::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'widget_key' => 'widget-key-'.str()->random(25),
            'phone_number' => $phoneNumber,
            'twilio_phone_sid' => 'PN'.str()->random(10),
            'status' => 'active',
            'voice_settings' => array_merge(['enabled' => true], $voiceSettings),
        ]);
    }

    private function startCall(Project $project, string $callSid, int $turnCount = 0): PhoneCall
    {
        $session = ChatSession::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'title' => 'Call from +38344111222',
            'channel' => 'voice',
            'metadata' => ['caller' => ['number' => '+38344111222'], 'call_sid' => $callSid],
        ]);

        return PhoneCall::query()->create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'chat_session_id' => $session->id,
            'call_sid' => $callSid,
            'from_number' => '+38344111222',
            'to_number' => $project->phone_number,
            'status' => 'in-progress',
            'direction' => 'inbound',
            'turn_count' => $turnCount,
            'started_at' => now(),
        ]);
    }
}
