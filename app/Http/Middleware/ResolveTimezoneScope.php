<?php

namespace App\Http\Middleware;

use Closure;
use DateTimeZone;
use Illuminate\Http\Request;
use App\Factory;
use App\Support\FactoryContext;
use App\Support\RequestTimezone;

/**
 * Resolves the IANA timezone the current request should use for day-boundary
 * queries and report/export formatting. Must run after ResolveFactoryScope so
 * FactoryContext is already populated for the fallback below.
 *
 * The frontend already knows the effective_timezone of the user's active
 * factory/factories (and lets the user override when more than one is
 * selected, since they can differ), so it is the source of truth via the
 * X-Timezone header — the backend re-deriving it independently from
 * FactoryContext's id order could disagree with what the frontend just
 * displayed. That header is only trusted after IANA validation; an
 * empty/invalid header falls back to the scoped factory's own timezone, then
 * UTC, so RequestTimezone::get() is never left unresolved.
 */
class ResolveTimezoneScope
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('X-Timezone');

        if ($header && in_array($header, DateTimeZone::listIdentifiers(), true)) {
            RequestTimezone::set($header);

            return $next($request);
        }

        $factoryIds = FactoryContext::ids();

        if (!empty($factoryIds)) {
            $factory = Factory::with(['region', 'country'])->find($factoryIds[0]);

            if ($factory && $factory->effective_timezone) {
                RequestTimezone::set($factory->effective_timezone);

                return $next($request);
            }
        }

        RequestTimezone::set(config('app.timezone', 'UTC'));

        return $next($request);
    }
}
