<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\FactoryContext;

/**
 * Resolves which factory(ies) the current request is scoped to, from the
 * X-Factory-Ids header the frontend sends once the user has picked their
 * active factory/factories post-login. Must run after JwtAuthenticate so
 * Auth::user() is already populated.
 *
 * The header is a hint, not a grant: every id in it is intersected against
 * the user's real factory_user assignments, re-checked live on every
 * request — so revoking a user's factory access takes effect immediately,
 * without waiting for their JWT to expire or forcing a re-login.
 */
class ResolveFactoryScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            $allowedIds = $user->factories()->pluck('factories.id')->all();

            // sysadmin bypasses screen/permission checks (a separate mechanism —
            // see PermissionRepository::isAuthorized), but data scoping is not
            // that: sysadmin is still expected to pick a factory and see only
            // that factory's data, like anyone else. The only special case is a
            // fresh install where sysadmin has no factory_user rows yet — fall
            // back to every factory instead of locking its own queries out.
            if (empty($allowedIds) && $user->email === 'sysadmin@gmail.com') {
                $allowedIds = \App\Factory::pluck('id')->all();
            }

            $requestedIds = array_values(array_filter(array_map(
                'intval',
                explode(',', (string) $request->header('X-Factory-Ids', ''))
            )));

            $resolvedIds = $requestedIds
                ? array_values(array_intersect($requestedIds, $allowedIds))
                : [];

            // An empty/invalid header, or one naming only factories the user no
            // longer has, falls back to their full allowed set rather than an
            // unscoped or empty (locked-out) query.
            FactoryContext::set($resolvedIds ?: $allowedIds);
        }

        return $next($request);
    }
}
