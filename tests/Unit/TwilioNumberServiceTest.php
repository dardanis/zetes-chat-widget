<?php

namespace Tests\Unit;

use App\Services\Voice\TwilioNumberService;
use RuntimeException;
use Tests\TestCase;
use Twilio\Base\PhoneNumberCapabilities;

class TwilioNumberServiceTest extends TestCase
{
    private TwilioNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TwilioNumberService;
    }

    public function test_it_reads_voice_capability_from_the_sdk_object(): void
    {
        $capable = new PhoneNumberCapabilities(['voice' => true, 'sms' => true]);
        $notCapable = new PhoneNumberCapabilities(['voice' => false, 'sms' => true]);

        $this->assertTrue(TwilioNumberService::isVoiceCapable($capable));
        $this->assertFalse(TwilioNumberService::isVoiceCapable($notCapable));
    }

    /**
     * The SDK defaults a missing flag to the string "false", which would cast to true.
     */
    public function test_it_treats_the_string_false_as_not_capable(): void
    {
        $missing = new PhoneNumberCapabilities(['sms' => true]);

        $this->assertFalse(TwilioNumberService::isVoiceCapable($missing));
        $this->assertFalse(TwilioNumberService::isVoiceCapable(['voice' => 'false']));
    }

    public function test_it_still_accepts_a_plain_array(): void
    {
        $this->assertTrue(TwilioNumberService::isVoiceCapable(['voice' => true]));
        $this->assertTrue(TwilioNumberService::isVoiceCapable(['voice' => 'true']));
        $this->assertFalse(TwilioNumberService::isVoiceCapable(['sms' => true]));
        $this->assertFalse(TwilioNumberService::isVoiceCapable(null));
    }

    public function test_it_normalizes_numbers_to_e164(): void
    {
        $this->assertSame('+38344123456', $this->service->normalize('+383 44 123 456'));
        $this->assertSame('+38344123456', $this->service->normalize('0038344123456'));
        $this->assertSame('+38344123456', $this->service->normalize('+383-44-123-456'));
        $this->assertSame('+38344123456', $this->service->normalize('  +38344123456  '));
    }

    public function test_it_rejects_numbers_that_are_not_valid_e164(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->normalize('12345');
    }

    public function test_it_rejects_an_empty_number(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->normalize('not a number');
    }

    public function test_it_builds_webhook_urls_from_the_configured_base(): void
    {
        config(['services.twilio.webhook_base_url' => 'https://voice.test/']);

        $this->assertSame('https://voice.test/api/twilio/voice/incoming', $this->service->webhookUrl('incoming'));
        $this->assertSame('https://voice.test/api/twilio/voice/status', $this->service->webhookUrl('/status'));
    }
}
