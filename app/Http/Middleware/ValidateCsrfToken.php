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
     * @var list<string>
     */
    protected $except = [
        'api/widget/*',
    ];
}

