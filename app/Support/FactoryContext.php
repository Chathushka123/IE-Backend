<?php

namespace App\Support;

/**
 * Per-request holder for which factory(ies) the current request is scoped to.
 * Populated by App\Http\Middleware\ResolveFactoryScope once Auth::user() is
 * known; left unresolved outside HTTP requests (console commands, tinker,
 * queued jobs) so those run unscoped by default.
 */
class FactoryContext
{
    protected static ?array $ids = null;
    protected static bool $bypass = false;

    public static function set(array $ids): void
    {
        static::$ids = array_values(array_unique(array_map('intval', $ids)));
        static::$bypass = false;
    }

    public static function setBypass(): void
    {
        static::$bypass = true;
        static::$ids = null;
    }

    public static function reset(): void
    {
        static::$ids = null;
        static::$bypass = false;
    }

    public static function ids(): ?array
    {
        return static::$ids;
    }

    public static function isBypassed(): bool
    {
        return static::$bypass;
    }

    /** True once the middleware has run and either resolved a concrete id list or bypassed. */
    public static function isResolved(): bool
    {
        return static::$bypass || static::$ids !== null;
    }
}
