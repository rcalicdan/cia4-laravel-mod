<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelMigration;

use CodeIgniter\CLI\CLI;
use Exception;

class MigrationFileHandler
{
    /**
     * Base path where Laravel-style migration files will be stored.
     */
    protected string $migrationPath;

    /**
     * Constructor
     *
     * @param  string  $migrationPath  The base path for migrations
     */
    public function __construct(string $migrationPath)
    {
        $this->migrationPath = $migrationPath;
    }

    /**
     * Generates a timestamped filename for the migration.
     * Example: 2023_10_27_123456_create_users_table.php
     *
     * @param  string  $snakeCaseName  The snake_case name of the migration.
     * @return string The generated filename.
     */
    public function generateFileName(string $snakeCaseName): string
    {
        $timestamp = date('Y_m_d_His');

        return "{$timestamp}_{$snakeCaseName}.php";
    }

    /**
     * Get the full path to the migration file
     *
     * @param  string  $fileName  The filename
     * @return string The full path
     */
    public function getFullPath(string $fileName): string
    {
        return rtrim($this->migrationPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$fileName;
    }

    /**
     * Get the relative path for display purposes
     *
     * @param  string  $fullPath  The full file path
     * @return string The relative path
     */
    public function getRelativePath(string $fullPath): string
    {
        return str_replace(APPPATH, 'app/', $fullPath);
    }

    /**
     * Validates that the specific migration *filename* does not exist.
     * Outputs error if file exists.
     *
     * @param  string  $filePath  The full file path to check.
     * @param  string  $relativeFileName  The relative file name for display purposes.
     * @return bool True if the migration file does not exist, false otherwise.
     */
    public function validateMigrationDoesNotExist(string $filePath, string $relativeFileName): bool
    {
        if (file_exists($filePath)) {
            CLI::error('Migration file already exists: '.CLI::color($relativeFileName, 'light_cyan'));

            return false;
        }

        return true;
    }

    /**
     * Ensures the base migration directory exists, creating it if necessary.
     *
     * @return bool True if directory exists or was created successfully.
     */
    public function ensureMigrationDirectoryExists(): bool
    {
        $path = rtrim($this->migrationPath, DIRECTORY_SEPARATOR);
        if (is_dir($path)) {
            return true; // Already exists
        }

        // Directory doesn't exist, attempt to create it
        CLI::write('Migration directory not found, attempting to create: '.str_replace(APPPATH, 'app/', $path), 'dark_gray');

        try {
            if (! mkdir($path, 0755, true)) { // Use standard permissions, recursive
                CLI::error("Error: Could not create migration directory: {$path}. Check permissions.");

                return false;
            }
            CLI::write('Migration directory created successfully.', 'green');

            return true;
        } catch (Exception $e) {
            CLI::error("Exception creating migration directory: {$path}. Reason: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Writes the generated code content to the migration file.
     * Outputs error if writing fails.
     *
     * @param  string  $filePath  The full file path to write to.
     * @param  string  $relativeFileName  The relative file name for display purposes.
     * @param  string  $code  The PHP code content to write.
     * @return bool True on successful write, false otherwise.
     */
    public function writeMigrationFileContent(string $filePath, string $relativeFileName, string $code): bool
    {
        if (! write_file($filePath, $code)) {
            CLI::error("Error writing migration file: {$relativeFileName}. Check permissions.");

            return false;
        }

        return true;
    }
}
