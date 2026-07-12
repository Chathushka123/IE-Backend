<?php

namespace App\Support\Concerns;

use App\Support\FactoryContext;

/**
 * For models related to factories many-to-many via a `factories()` relation
 * (Product, User). Laravel auto-calls bootScopedToFactories() for any model
 * using this trait — no changes needed to the model's own boot() override.
 */
trait ScopedToFactories
{
    protected static function bootScopedToFactories()
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
            $query->whereHas('factories', function ($q) use ($ids) {
                $q->whereIn('factories.id', $ids);
            });
        });
    }
}
