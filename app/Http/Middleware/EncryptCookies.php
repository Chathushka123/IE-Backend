<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        // Left unencrypted so the frontend can read it via JS and echo it
        // back as a header for the double-submit CSRF check; the actual
        // refresh_token cookie stays encrypted + httpOnly.
        'csrf_refresh_token',
    ];
}
