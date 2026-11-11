<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->refuseToRunAgainstANonTestDatabase($app);

        return $app;
    }

    /**
     * RefreshDatabase drops every table before it migrates. If an environment override is ignored
     * (a cached config does that: bootstrap/cache/config.php pins DB_DATABASE and phpunit.xml's value
     * is never read), the suite wipes the development database. That happened once; stop it here,
     * before any test can touch a connection.
     */
    private function refuseToRunAgainstANonTestDatabase($app): void
    {
        $config = $app->make('config');
        $connection = $config->get('database.default');
        $database = (string) $config->get("database.connections.$connection.database");

        if (!str_ends_with($database, '_test')) {
            throw new \RuntimeException(
                "Refusing to run the tests: they would use the database '$database', which is not a test database "
                . "(its name must end in '_test'). If bootstrap/cache/config.php exists, run `php artisan config:clear` and try again."
            );
        }
    }
}
