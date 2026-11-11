<?php

namespace App\Console\Commands;

use App\Support\DemoData\DemoDatasetBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills a restored UAT copy with a believable operation bank, styles, templates, studies, SMV
 * sessions and team plans. Deliberately awkward to point at the wrong database: the target must be
 * named, must not be a test database, and must be on this machine.
 */
class SeedDemoDataset extends Command
{
    protected $signature = 'demo:seed {--database= : Name of the database to fill (required)} {--studies=1000 : Roughly how many time studies to record} {--seed=20261025 : Random seed, for a reproducible dataset}';

    protected $description = 'Insert a demo dataset (skills, operations, styles, templates, studies, SMV sessions, team plans) into a restored UAT database';

    public function handle(): int
    {
        $database = (string) $this->option('database');
        if ($database === '') {
            $this->error('Name the database with --database=<name>. Nothing was changed.');

            return 1;
        }
        if (str_ends_with($database, '_test')) {
            $this->error("Refusing to seed '{$database}': test databases are wiped by the test suite. Copy it to another name first.");

            return 1;
        }
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return 1;
        }
        $host = (string) config('database.connections.mysql.host');
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $this->error("Refusing to run against a remote host ({$host}).");

            return 1;
        }

        config(['database.connections.mysql.database' => $database]);
        DB::purge('mysql');
        $connected = DB::connection('mysql')->getDatabaseName();
        if ($connected !== $database) {
            $this->error("Connected to '{$connected}', not '{$database}'. Nothing was changed.");

            return 1;
        }

        $this->info("Filling '{$connected}' on {$host}.");
        $started = microtime(true);
        try {
            $stats = (new DemoDatasetBuilder(fn (string $step) => $this->line("• {$step}"), (int) $this->option('studies'), (int) $this->option('seed')))->run();
        } catch (\Throwable $e) {
            $this->error(get_class($e) . ': ' . $e->getMessage());
            $this->line('The database may be partly filled. Restore a fresh copy before trying again.');

            return 1;
        }

        $this->newLine();
        $this->info(sprintf('Done in %.0fs.', microtime(true) - $started));
        $this->line(json_encode($stats, JSON_PRETTY_PRINT));

        return 0;
    }
}
