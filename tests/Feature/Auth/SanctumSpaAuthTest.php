<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class SanctumSpaAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_register_returns_created_user(): void
    {
        [$csrfHeaders, $xsrfCookie, $sessionCookie] = $this->issueCsrfCookies();

        $registerResponse = $this->withHeaders($csrfHeaders)
            ->withCookie($xsrfCookie->getName(), (string) $xsrfCookie->getValue())
            ->withCookie($sessionCookie->getName(), (string) $sessionCookie->getValue())
            ->postJson('/api/register', [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('user.email', 'demo@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'demo@example.com',
        ]);
    }

    public function test_guest_cannot_access_user_endpoint(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        [$csrfHeaders, $xsrfCookie, $sessionCookie] = $this->issueCsrfCookies();

        $loginResponse = $this->withHeaders($csrfHeaders)
            ->withCookie($xsrfCookie->getName(), (string) $xsrfCookie->getValue())
            ->withCookie($sessionCookie->getName(), (string) $sessionCookie->getValue())
            ->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
            'remember' => true,
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $sessionCookie = $loginResponse->getCookie(config('session.cookie')) ?? $sessionCookie;

        $this->withHeaders($this->frontendHeaders())
            ->withCookie($sessionCookie->getName(), (string) $sessionCookie->getValue())
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'login@example.com');
    }

    /**
     * @return array{0: array<string, string>, 1: Cookie, 2: Cookie}
     */
    private function issueCsrfCookies(): array
    {
        $csrfResponse = $this->withHeaders($this->frontendHeaders())->get('/sanctum/csrf-cookie');

        $csrfResponse->assertNoContent();

        $xsrfCookie = $csrfResponse->getCookie('XSRF-TOKEN');
        $sessionCookie = $csrfResponse->getCookie(config('session.cookie'));

        $this->assertNotNull($xsrfCookie);
        $this->assertNotNull($sessionCookie);

        return [
            array_merge($this->frontendHeaders(), [
                'X-XSRF-TOKEN' => urldecode((string) $xsrfCookie->getValue()),
            ]),
            $xsrfCookie,
            $sessionCookie,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function frontendHeaders(): array
    {
        return [
            'Origin' => 'http://127.0.0.1:8000',
            'Referer' => 'http://127.0.0.1:8000/ng/',
            'Accept' => 'application/json',
        ];
    }
}

