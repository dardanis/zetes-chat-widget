<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Security\RequestValidator;

class ValidateTwilioRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('services.twilio.validate_signature')) {
            Log::warning('Twilio signature validation is DISABLED. Voice webhooks are unauthenticated.', [
                'path' => $request->path(),
            ]);

            return $next($request);
        }

        $authToken = (string) config('services.twilio.auth_token');
        $signature = (string) $request->header('X-Twilio-Signature', '');

        abort_if($authToken === '', 500, 'Twilio auth token is not configured.');
        abort_if($signature === '', 403, 'Missing Twilio signature.');

        $validator = new RequestValidator($authToken);

        abort_unless(
            $validator->validate($signature, $this->signedUrl($request), $request->post()),
            403,
            'Invalid Twilio signature.'
        );

        return $next($request);
    }

    /**
     * Twilio signs the exact URL it requested. Behind a tunnel or reverse proxy the scheme, host,
     * and port Laravel sees differ from what Twilio dialled, so rebuild the URL from the configured
     * public origin instead of trusting $request->fullUrl(). Getting this wrong is the classic
     * cause of every webhook returning 403.
     */
    private function signedUrl(Request $request): string
    {
        $base = rtrim((string) config('services.twilio.webhook_base_url'), '/');

        if ($base === '') {
            return $request->fullUrl();
        }

        $url = $base.'/'.ltrim($request->path(), '/');
        $query = $request->getQueryString();

        return $query !== null && $query !== '' ? $url.'?'.$query : $url;
    }
}
