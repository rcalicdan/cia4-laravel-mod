<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;

class MigrationHandler extends SetupHandler
{
    /**
     * Copy Laravel migration files to App/Database/Eloquent-Migrations
     */
    public function copyMigrationFiles(): void
    {
        // Create the Database/Eloquent-Migrations directory if it doesn't exist
        $migrationsDir = $this->distPath.'Database/Eloquent-Migrations';
        if (! is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0777, true);
            $this->write(CLI::color('  Created: ', 'green').clean_path($migrationsDir));
        }

        // Find migration files
        $sourceMigrationDir = $this->sourcePath.'Database/Eloquent-Migrations';
        if (! is_dir($sourceMigrationDir)) {
            $this->error('  Source migration directory not found.');

            return;
        }

        // Copy each migration file
        $files = scandir($sourceMigrationDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourceFile = $sourceMigrationDir.'/'.$file;
            $destFile = $migrationsDir.'/'.$file;

            if (copy($sourceFile, $destFile)) {
                $this->write(CLI::color('  Copied: ', 'green').clean_path($destFile));
            } else {
                $this->error('  Error copying migration file: '.$file);
            }
        }

        $this->write(CLI::color('  Migration files copied successfully!', 'green'));
    }
}
