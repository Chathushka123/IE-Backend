<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Per-request holder for the IANA timezone the current request should use for
 * "what does 'today' mean" decisions — day-boundary queries, report/export
 * formatting. Populated by App\Http\Middleware\ResolveTimezoneScope; left
 * unresolved outside HTTP requests (console commands, queued jobs), so those
 * callers must pass a timezone explicitly rather than relying on this.
 */
class RequestTimezone
{
    protected static ?string $timezone = null;

    public static function set(string $timezone): void
    {
        static::$timezone = $timezone;
    }

    public static function reset(): void
    {
        static::$timezone = null;
    }

    /** Falls back to UTC if the middleware hasn't run (e.g. a console context). */
    public static function get(): string
    {
        return static::$timezone ?? 'UTC';
    }

    public static function isResolved(): bool
    {
        return static::$timezone !== null;
    }

    /**
     * UTC start/end instants for "today" in the resolved request timezone —
     * for comparisons against real timestamp columns (created_at,
     * planned_start_date, ...). Do not use for DATE-only columns; see
     * todayDate() for those.
     */
    public static function today(): array
    {
        $now = Carbon::now(static::get());

        return [
            'start' => $now->copy()->startOfDay()->setTimezone('UTC'),
            'end' => $now->copy()->endOfDay()->setTimezone('UTC'),
        ];
    }

    /**
     * Today's calendar date in the resolved request timezone — for
     * comparisons against DATE-only columns (joining_date, study_date, ...).
     * Not a UTC instant: callers should use ->toDateString() /
     * ->month/->year on the returned Carbon, not compare it directly
     * against a timestamp column (use today() for those).
     */
    public static function todayDate(): Carbon
    {
        return Carbon::now(static::get())->startOfDay();
    }
}
