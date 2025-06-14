<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate;

use CodeIgniter\CLI\CLI;

/**
 * Handles SQLite-specific database operations for Laravel migrations.
 * 
 * This class encapsulates all SQLite path resolution and file handling logic
 * to improve performance by removing string manipulation overhead from the main
 * EloquentDatabase class.
 */
class SqliteHandler
{
    /**
     * Default SQLite database filename.
     */
    private const DEFAULT_SQLITE_FILE = 'database.sqlite';

    /**
     * Resolve and normalize SQLite database path.
     *
     * @param string $database The database path from configuration
     * @return string The resolved absolute path
     */
    public function resolveDatabasePath(string $database): string
    {
        if (empty($database)) {
            return $this->getDefaultPath();
        }

        if ($this->isAbsolutePath($database)) {
            return $database;
        }

        if ($this->isDatabasePathHelper($database)) {
            return $this->resolveDatabasePathHelper($database);
        }

        return $this->resolveRelativePath($database);
    }

    /**
     * Check if SQLite database file exists and is accessible.
     *
     * @param string $databasePath The database file path
     * @return bool True if database exists and is accessible
     */
    public function databaseExists(string $databasePath): bool
    {
        return file_exists($databasePath) && is_readable($databasePath);
    }

    /**
     * Create SQLite database file with proper permissions.
     *
     * @param string $databasePath The database file path
     * @return bool True if database was created successfully
     */
    public function createDatabase(string $databasePath): bool
    {
        try {
            $directory = dirname($databasePath);
            
            if (!$this->ensureDirectoryExists($directory)) {
                return false;
            }

            if (!$this->createDatabaseFile($databasePath)) {
                return false;
            }

            $this->setDatabasePermissions($databasePath);
            
            return true;
        } catch (\Exception $e) {
            CLI::error("Failed to create SQLite database: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get database information formatted for Laravel connections.
     *
     * @param array $config Original database configuration
     * @return array Updated configuration with resolved SQLite path
     */
    public function prepareConfig(array $config): array
    {
        if (strtolower($config['driver']) !== 'sqlite') {
            return $config;
        }

        $config['database'] = $this->resolveDatabasePath($config['database']);
        
        return $config;
    }

    /**
     * Validate SQLite database configuration.
     *
     * @param array $config Database configuration
     * @return array Validation results with 'valid' boolean and 'errors' array
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if (empty($config['database'])) {
            $errors[] = 'SQLite database path cannot be empty';
        }

        $databasePath = $this->resolveDatabasePath($config['database']);
        $directory = dirname($databasePath);

        if (!is_dir($directory) && !is_writable(dirname($directory))) {
            $errors[] = "Cannot create directory: {$directory}";
        }

        if (file_exists($databasePath) && !is_writable($databasePath)) {
            $errors[] = "Database file is not writable: {$databasePath}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'resolved_path' => $databasePath
        ];
    }

    /**
     * Check if the given path is absolute.
     *
     * @param string $path The path to check
     * @return bool True if path is absolute
     */
    private function isAbsolutePath(string $path): bool
    {
        // Unix absolute path
        if (strpos($path, '/') === 0) {
            return true;
        }

        // Windows absolute path (C:\, D:\, etc.)
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the database path uses Laravel's database_path() helper format.
     *
     * @param string $database The database configuration value
     * @return bool True if it's a database_path() helper
     */
    private function isDatabasePathHelper(string $database): bool
    {
        return str_contains($database, 'database_path');
    }

    /**
     * Resolve Laravel's database_path() helper format.
     *
     * @param string $database The database path with helper
     * @return string The resolved path
     */
    private function resolveDatabasePathHelper(string $database): string
    {
        // Extract the path from database_path('filename')
        $filename = str_replace(['database_path(\'', '\')'], '', $database);
        
        return WRITEPATH . trim($filename, '/\\');
    }

    /**
     * Resolve relative path to absolute path.
     *
     * @param string $database The relative database path
     * @return string The absolute path
     */
    private function resolveRelativePath(string $database): string
    {
        $filename = $this->ensureSqliteExtension($database);
        
        return WRITEPATH . ltrim($filename, '/\\');
    }

    /**
     * Get the default SQLite database path.
     *
     * @return string The default database path
     */
    private function getDefaultPath(): string
    {
        return WRITEPATH . self::DEFAULT_SQLITE_FILE;
    }

    /**
     * Ensure the filename has .sqlite extension.
     *
     * @param string $filename The filename to check
     * @return string The filename with .sqlite extension
     */
    private function ensureSqliteExtension(string $filename): string
    {
        if (!str_ends_with(strtolower($filename), '.sqlite')) {
            $filename .= '.sqlite';
        }

        return $filename;
    }

    /**
     * Ensure the directory exists and is writable.
     *
     * @param string $directory The directory path
     * @return bool True if directory exists or was created
     */
    private function ensureDirectoryExists(string $directory): bool
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }

        if (!mkdir($directory, 0755, true)) {
            CLI::error("Failed to create directory: {$directory}");
            return false;
        }

        return true;
    }

    /**
     * Create the SQLite database file.
     *
     * @param string $databasePath The database file path
     * @return bool True if file was created
     */
    private function createDatabaseFile(string $databasePath): bool
    {
        if (file_exists($databasePath)) {
            return true;
        }

        if (file_put_contents($databasePath, '') === false) {
            CLI::error("Failed to create database file: {$databasePath}");
            return false;
        }

        return true;
    }

    /**
     * Set proper permissions on the database file.
     *
     * @param string $databasePath The database file path
     * @return void
     */
    private function setDatabasePermissions(string $databasePath): void
    {
        chmod($databasePath, 0644);
    }

    /**
     * Get database file size in bytes.
     *
     * @param string $databasePath The database file path
     * @return int|false File size in bytes or false on failure
     */
    public function getDatabaseSize(string $databasePath)
    {
        if (!$this->databaseExists($databasePath)) {
            return false;
        }

        return filesize($databasePath);
    }

    /**
     * Check if database file is empty.
     *
     * @param string $databasePath The database file path
     * @return bool True if database is empty
     */
    public function isDatabaseEmpty(string $databasePath): bool
    {
        $size = $this->getDatabaseSize($databasePath);
        
        return $size === 0 || $size === false;
    }
}