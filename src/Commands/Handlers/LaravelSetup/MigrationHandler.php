<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;

class MigrationHandler extends SetupHandler
{
    /**
     * @var string The full path to the destination migrations directory.
     */
    private string $destinationMigrationsDir;

    /**
     * @var string The full path to the source migrations directory.
     */
    private string $sourceMigrationsDir;

    /**
     * Orchestrates the copying of Laravel migration files to the CodeIgniter application.
     */
    public function copyMigrationFiles(): void
    {
        $this->destinationMigrationsDir = $this->distPath.'Database/Eloquent-Migrations';
        $this->sourceMigrationsDir = $this->sourcePath.'Database/Eloquent-Migrations';

        if (! $this->ensureDestinationDirectoryExists()) {
            return;
        }

        if (! $this->validateSourceDirectoryExists()) {
            return;
        }

        if (! $this->confirmProceedWithCopy()) {
            $this->error('  Skipped copying migration files.');

            return;
        }

        $this->processSourceMigrationFiles();

        $this->write(CLI::color('  Migration files processing complete.', 'green'));
    }

    /**
     * Ensures the destination directory for migrations exists, creating it if necessary.
     *
     * @return bool True if the directory exists or was created successfully, false otherwise.
     */
    private function ensureDestinationDirectoryExists(): bool
    {
        if (! is_dir($this->destinationMigrationsDir)) {
            if (mkdir($this->destinationMigrationsDir, 0777, true)) {
                $this->write(CLI::color('  Created: ', 'green').clean_path($this->destinationMigrationsDir));
            } else {
                $this->error('  Failed to create directory: '.clean_path($this->destinationMigrationsDir));

                return false;
            }
        }

        return true;
    }

    /**
     * Validates that the source migration directory exists.
     *
     * @return bool True if the source directory exists, false otherwise.
     */
    private function validateSourceDirectoryExists(): bool
    {
        if (! is_dir($this->sourceMigrationsDir)) {
            $this->error('  Source migration directory not found: '.clean_path($this->sourceMigrationsDir));

            return false;
        }

        return true;
    }

    /**
     * Asks the user for confirmation before starting the copy process, if confirmations are not skipped.
     *
     * @return bool True if the user confirms or confirmations are skipped, false if the user declines.
     */
    private function confirmProceedWithCopy(): bool
    {
        if ($this->skipConfirmations) {
            return true;
        }

        $promptMessage = sprintf(
            '  Ready to copy migration files to %s. Continue?',
            clean_path($this->destinationMigrationsDir)
        );

        return $this->prompt($promptMessage, ['y', 'n']) === 'y';
    }

    /**
     * Iterates through files in the source migration directory and processes each one.
     */
    private function processSourceMigrationFiles(): void
    {
        $files = scandir($this->sourceMigrationsDir);
        if ($files === false) {
            $this->error('  Could not read source migration directory: '.clean_path($this->sourceMigrationsDir));

            return;
        }

        $migrationFilesFound = false;
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $migrationFilesFound = true;
            $sourceFile = $this->sourceMigrationsDir.'/'.$file;
            $destFile = $this->destinationMigrationsDir.'/'.$file;

            $this->copySingleMigrationFile($sourceFile, $destFile);
        }

        if (! $migrationFilesFound) {
            $this->write(CLI::color('  No migration files found in the source directory.', 'yellow'));
        }
    }

    /**
     * Handles the copying of a single migration file, including overwrite confirmation.
     *
     * @param  string  $sourceFile  The full path to the source migration file.
     * @param  string  $destFile  The full path to the destination migration file.
     */
    private function copySingleMigrationFile(string $sourceFile, string $destFile): void
    {
        if (file_exists($destFile) && ! $this->skipConfirmations) {
            $promptMessage = sprintf(
                "  File '%s' already exists. Overwrite?",
                clean_path($destFile)
            );
            if ($this->prompt($promptMessage, ['n', 'y']) === 'n') {
                $this->write(CLI::color('  Skipped: ', 'yellow').clean_path($destFile));

                return;
            }
        }

        if (copy($sourceFile, $destFile)) {
            $this->write(CLI::color('  Copied: ', 'green').clean_path($destFile));
        } else {
            $this->error('  Error copying migration file: '.basename($sourceFile).' to '.clean_path($destFile));
        }
    }
}
