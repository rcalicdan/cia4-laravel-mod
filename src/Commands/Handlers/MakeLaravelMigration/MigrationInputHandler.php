<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelMigration;

use CodeIgniter\CLI\CLI;

class MigrationInputHandler
{
    /**
     * Gets the migration name from parameters or prompts the user.
     *
     * @param array $params Command parameters.
     * @return string|null The migration name, or null if empty after prompt.
     */
    public function getMigrationName(array $params): ?string
    {
        $migrationName = $params[0] ?? null;
        if (empty($migrationName)) {
            $migrationName = CLI::prompt('Migration name (e.g., CreateUsersTable)');
        }

        if (empty($migrationName)) {
            CLI::error('Migration name cannot be empty.');
            return null;
        }
        return $migrationName;
    }

    /**
     * Extracts the value of the --table option directly from command line arguments ($argv).
     * Required workaround if CLI::getOption('--table') isn't reliably parsing '--table=value'.
     *
     * @return string|null The table name if found, null otherwise.
     */
    public function getTableOptionFromArgv(): ?string
    {
        global $argv; // Access raw command line arguments
        $tableName = null;

        if (!isset($argv) || !is_array($argv)) {
            CLI::write('Warning: Could not access global $argv.', 'yellow');
            return null; // Safety check
        }

        foreach ($argv as $arg) {
            // Look for '--table=some_value'
            if (str_starts_with($arg, '--table=')) { // PHP 8+ str_starts_with
                $tableName = substr($arg, 8); // Length of '--table='
                break; // Found it
            }
        }

        // Ensure empty string isn't returned if '--table=' was passed with no value
        return ($tableName === null || $tableName === '') ? null : $tableName;
    }

    /**
     * Checks if force option is enabled
     * 
     * @return bool True if force option is enabled
     */
    public function isForceEnabled(): bool
    {
        return CLI::getOption('force') ?? false;
    }
}