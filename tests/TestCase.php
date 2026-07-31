<?php

namespace Tests;

use App\Support\FactoryContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * FactoryContext holds its resolved state in static properties (set by
     * ResolveFactoryScope on every authenticated request), so it survives
     * past the end of whichever test made that request and leaks into every
     * later test in the same process, scoping their queries against a stale
     * factory list.
     */
    protected function tearDown(): void
    {
        FactoryContext::reset();

        parent::tearDown();
    }
}
