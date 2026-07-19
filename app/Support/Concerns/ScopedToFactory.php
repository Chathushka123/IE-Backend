<?php

namespace App\Support\Concerns;

use App\Support\FactoryContext;

/**
 * For models with a single `factory_id` column (Employee, Team).
 * Laravel auto-calls bootScopedToFactory() for any model using this trait —
 * no changes needed to the model's own boot() override.
 */
trait ScopedToFactory
{
    protected static function bootScopedToFactory()
    {
        static::addGlobalScope('factory', function ($query) {
            if (FactoryContext::isBypassed() || !FactoryContext::isResolved()) {
                return;
            }
            $ids = FactoryContext::ids();
            if (empty($ids)) {
                $query->whereRaw('1 = 0');
                return;
            }
            $query->whereIn($query->getModel()->getTable() . '.factory_id', $ids);
        });
    }
}
