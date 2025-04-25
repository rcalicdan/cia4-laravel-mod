<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate;

use CodeIgniter\CLI\CLI;

class OutputHandler
{
    /**
     * Show migration up results
     */
    public function showUpResult(array $migrations): void
    {
        if (empty($migrations)) {
            CLI::write('Nothing to migrate.', 'green');
        } else {
            CLI::write('Laravel migrations ran successfully.', 'green');
            foreach ($migrations as $migration) {
                CLI::write("Migrated: {$migration}");
            }
        }
    }

    /**
     * Show migration down results
     */
    public function showDownResult(array $migrations): void
    {
        if (empty($migrations)) {
            CLI::write('Nothing to rollback.', 'green');
        } else {
            CLI::write('Laravel migrations rolled back successfully.', 'green');
            foreach ($migrations as $migration) {
                CLI::write("Rolled back: {$migration}");
            }
        }
    }

    /**
     * Show migration refresh results
     */
    public function showRefreshResult(): void
    {
        CLI::write('All migrations rolled back and re-run successfully.', 'green');
    }

    /**
     * Show migration status results
     */
    public function showStatusResult(array $status): void
    {
        CLI::write('Laravel Migration Status:', 'yellow');
        CLI::write('-----------------', 'yellow');

        foreach ($status as $name => $state) {
            CLI::write("{$name}: {$state}");
        }
    }

    /**
     * Show usage information
     */
    public function showUsage(): void
    {
        CLI::write('Usage: php spark eloquent:migrate [up|down|refresh|status|fresh]', 'yellow');
        CLI::write('  up     : Run all pending Laravel migrations');
        CLI::write('  down   : Roll back the last batch of Laravel migrations');
        CLI::write('  refresh: Roll back and re-run all Laravel migrations');
        CLI::write('  status : Show the status of Laravel migrations');
        CLI::write('  fresh  : Drop all tables and re-run all Laravel migrations');
    }
}
