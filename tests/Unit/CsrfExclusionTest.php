<?php

namespace Tests\Unit;

use App\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Machine-to-machine webhooks cannot carry a CSRF token.
 *
 * Sanctum's statefulApi() pipeline enforces CSRF whenever the request looks first-party, which it
 * does once the Referer host matches SANCTUM_STATEFUL_DOMAINS. Twilio sends a Referer pointing at
 * the TwiML document that produced the callback, so serving the app from the same host Twilio
 * calls makes every follow-up webhook 419 — and it happens before route middleware, so nothing is
 * logged. These paths must stay excluded.
 */
class CsrfExclusionTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function exceptions(): array
    {
        $property = new ReflectionProperty(ValidateCsrfToken::class, 'except');

        return $property->getValue(new ValidateCsrfToken(app(), app('encrypter')));
    }

    public function test_twilio_webhooks_are_excluded_from_csrf(): void
    {
        $this->assertContains('api/twilio/*', $this->exceptions());
    }

    public function test_widget_endpoints_remain_excluded_from_csrf(): void
    {
        $this->assertContains('api/widget/*', $this->exceptions());
    }

    /**
     * @return list<array{0: string}>
     */
    public static function twilioWebhookPaths(): array
    {
        return [
            ['api/twilio/voice/incoming'],
            ['api/twilio/voice/turn'],
            ['api/twilio/voice/turn/wait'],
            ['api/twilio/voice/status'],
            ['api/twilio/voice/recording'],
            ['api/twilio/voice/fallback'],
        ];
    }

    #[DataProvider('twilioWebhookPaths')]
    public function test_every_voice_webhook_path_matches_an_exclusion(string $path): void
    {
        $request = Request::create('https://calls.example.com/'.$path, 'POST');

        $matched = collect($this->exceptions())
            ->contains(fn (string $pattern): bool => $request->fullUrlIs($pattern) || $request->is($pattern));

        $this->assertTrue($matched, "[{$path}] is not excluded from CSRF and would 419 on a stateful host.");
    }

    public function test_authenticated_api_routes_are_still_csrf_protected(): void
    {
        $request = Request::create('https://calls.example.com/api/projects/1/documents', 'POST');

        $matched = collect($this->exceptions())
            ->contains(fn (string $pattern): bool => $request->fullUrlIs($pattern) || $request->is($pattern));

        $this->assertFalse($matched, 'Dashboard API routes must keep CSRF protection.');
    }
}
