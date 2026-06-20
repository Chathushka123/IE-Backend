<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Exceptions\GeneralException;

/**
 * Double-submit CSRF check for the refresh_token cookie flow: the
 * csrf_refresh_token cookie is readable by JS, so a forged cross-site
 * request can't reproduce it in the X-CSRF-TOKEN header even though the
 * browser auto-attaches the cookie itself.
 */
class VerifyCsrfCookie
{
    public function handle(Request $request, Closure $next)
    {
        $cookieValue = $request->cookie(config('refresh_token.csrf_cookie_name'));
        $headerValue = $request->header(config('refresh_token.csrf_header_name'));

        if (
            empty($cookieValue) ||
            empty($headerValue) ||
            !hash_equals($cookieValue, $headerValue)
        ) {
            throw new GeneralException('CSRF token mismatch');
        }

        return $next($request);
    }
}
