<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

class ValidateCsrfToken extends Middleware
{
    /**
     * Use a project-specific XSRF cookie to avoid collisions with cookies from other local apps.
     *
     * @var string
     */
    protected $cookie = 'ZETES-XSRF-TOKEN';

    /**
     * URIs that should be excluded from CSRF verification.
     *
     * Widget endpoints are called cross-origin from embedded iframes,
     * so they cannot carry the host app's CSRF token.
     *
     * Twilio voice webhooks are server-to-server callbacks that can never hold a CSRF token. They
     * must be excluded explicitly: Twilio sends a Referer pointing at the TwiML document that
     * produced the callback, so once the app is served from a SANCTUM_STATEFUL_DOMAINS host,
     * Sanctum treats the request as first-party, starts a session, and CSRF rejects it with 419
     * before any route middleware runs — meaning nothing reaches the log. These endpoints are
     * authenticated by the X-Twilio-Signature header instead (see ValidateTwilioRequest).
     *
     * @var list<string>
     */
    protected $except = [
        'api/widget/*',
        'api/twilio/*',
    ];
}
