<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class JwtAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if ($user) {
                // Makes the resolved user available via the standard 'api' guard
                // (auth('api')->user()) for anything later in the pipeline —
                // re-parsing the token a second time is unreliable this deep in
                // a request's lifecycle.
                auth('api')->setUser($user);
            }
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'TOKEN_EXPIRED',
                'message' => 'Access token has expired',
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'TOKEN_INVALID',
                'message' => 'Access token is invalid',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'TOKEN_ABSENT',
                'message' => 'Authorization token not found',
            ], 401);
        }

        return $next($request);
    }
}
