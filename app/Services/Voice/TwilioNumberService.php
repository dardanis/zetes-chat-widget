<?php

namespace App\Services\Voice;

use ReflectionProperty;
use RuntimeException;
use Throwable;
use Twilio\Base\PhoneNumberCapabilities;
use Twilio\Rest\Client;

class TwilioNumberService
{
    private ?Client $client = null;

    public function isConfigured(): bool
    {
        return (string) config('services.twilio.account_sid') !== ''
            && (string) config('services.twilio.auth_token') !== '';
    }

    /**
     * Strip formatting and enforce E.164. Deliberately strict: a number stored in any other shape
     * would silently never match an inbound call's `To` value.
     */
    public function normalize(string $raw): string
    {
        $trimmed = trim($raw);
        $hadPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            throw new RuntimeException('Phone number is empty.');
        }

        // Accept the common 00-prefixed international form as well as a bare +.
        if (! $hadPlus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new RuntimeException('Phone number must be a valid E.164 number with 8 to 15 digits.');
        }

        return '+'.$digits;
    }

    /**
     * Confirm the number exists in *our* Twilio account before it can be bound to a project.
     *
     * @return array{sid:string, phone_number:string, friendly_name:string|null, voice_capable:bool}
     */
    public function findOwnedNumber(string $e164): array
    {
        $numbers = $this->client()->incomingPhoneNumbers->read(['phoneNumber' => $e164], 1);

        if ($numbers === []) {
            throw new RuntimeException('That number was not found in your Twilio account.');
        }

        $number = $numbers[0];

        if (! self::isVoiceCapable($number->capabilities)) {
            throw new RuntimeException('That Twilio number is not voice capable.');
        }

        return [
            'sid' => (string) $number->sid,
            'phone_number' => (string) $number->phoneNumber,
            'friendly_name' => $number->friendlyName !== null ? (string) $number->friendlyName : null,
            'voice_capable' => true,
        ];
    }

    /**
     * The SDK exposes capabilities as a PhoneNumberCapabilities object, not an array, and its
     * constructor defaults a missing flag to the *string* "false" — which is truthy. Since
     * getVoice() is typed `: bool`, PHP coerces that string to true before we could inspect it,
     * so read the raw property and normalise it ourselves. A plain array is still accepted in
     * case the shape changes again.
     */
    public static function isVoiceCapable(mixed $capabilities): bool
    {
        $voice = match (true) {
            $capabilities instanceof PhoneNumberCapabilities => self::rawCapability($capabilities, 'voice'),
            is_array($capabilities) => $capabilities['voice'] ?? false,
            default => false,
        };

        return filter_var($voice, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    private static function rawCapability(PhoneNumberCapabilities $capabilities, string $name): mixed
    {
        try {
            $property = new ReflectionProperty($capabilities, $name);

            return $property->getValue($capabilities);
        } catch (Throwable) {
            // Property renamed upstream: fall back to the accessor and accept its coercion.
            return $capabilities->getVoice();
        }
    }

    /**
     * Point the number's voice, status, and fallback webhooks at this application.
     */
    public function configureWebhooks(string $sid): void
    {
        $this->client()->incomingPhoneNumbers($sid)->update([
            'voiceUrl' => $this->webhookUrl('incoming'),
            'voiceMethod' => 'POST',
            'voiceFallbackUrl' => $this->webhookUrl('fallback'),
            'voiceFallbackMethod' => 'POST',
            'statusCallback' => $this->webhookUrl('status'),
            'statusCallbackMethod' => 'POST',
        ]);
    }

    /**
     * Detach our webhooks. Note this does not release the number at Twilio — dropping a number you
     * pay for should never be a side effect of a UI click.
     */
    public function clearWebhooks(string $sid): void
    {
        try {
            $this->client()->incomingPhoneNumbers($sid)->update([
                'voiceUrl' => '',
                'voiceFallbackUrl' => '',
                'statusCallback' => '',
            ]);
        } catch (Throwable) {
            // The number may already be gone from the account; unbinding locally is what matters.
        }
    }

    public function webhookUrl(string $action): string
    {
        return rtrim((string) config('services.twilio.webhook_base_url'), '/').'/api/twilio/voice/'.ltrim($action, '/');
    }

    private function client(): Client
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        return $this->client ??= new Client(
            (string) config('services.twilio.account_sid'),
            (string) config('services.twilio.auth_token'),
        );
    }
}
