<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateWidgetRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = array_filter(array_map('trim', config('rag.widget.allowed_origins', [])));

        if ($allowedOrigins === []) {
            return $next($request);
        }

        $origin = (string) ($request->headers->get('origin') ?? '');
        $referer = (string) ($request->headers->get('referer') ?? '');

        $isAllowed = collect($allowedOrigins)->contains(function (string $allowed) use ($origin, $referer): bool {
            if ($allowed === '*') {
                return true;
            }

            return str_starts_with($origin, $allowed) || str_starts_with($referer, $allowed);
        });

        abort_unless($isAllowed, 403, 'Origin is not allowed for widget API access.');

        return $next($request);
    }
}

