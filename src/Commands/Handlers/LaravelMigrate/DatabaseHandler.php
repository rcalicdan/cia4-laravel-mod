<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate;

use CodeIgniter\CLI\CLI;
use PDO;
use PDOException;

class DatabaseHandler
{
    /**
     * Check if the specified database exists
     */
    public function checkDatabaseExists(array $dbConfig): bool
    {
        try {
            $driver = strtolower($dbConfig['driver']);

            return match ($driver) {
                'mysql', 'mariadb' => $this->checkMysqlDatabaseExists($dbConfig),
                'pgsql' => $this->checkPgsqlDatabaseExists($dbConfig),
                'sqlite' => file_exists($dbConfig['database']),
                'sqlsrv' => $this->checkSqlsrvDatabaseExists($dbConfig),
                default => $this->handleUnsupportedDriver($driver, 'checking')
            };
        } catch (PDOException $e) {
            // Check if error is about unknown database
            if (
                strpos($e->getMessage(), 'Unknown database') !== false ||
                strpos($e->getMessage(), '1049') !== false
            ) {
                return false; // Database doesn't exist
            }

            // For other connection errors, show error and exit
            CLI::error('Database connection error: ' . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Create database based on configuration
     */
    public function createDatabase(array $dbConfig): void
    {
        try {
            $driver = strtolower($dbConfig['driver']);
            $database = $dbConfig['database'];

            match ($driver) {
                'mysql', 'mariadb' => $this->createMysqlDatabase($dbConfig),
                'pgsql' => $this->createPgsqlDatabase($dbConfig),
                'sqlite' => $this->createSqliteDatabase($dbConfig),
                'sqlsrv' => $this->createSqlsrvDatabase($dbConfig),
                default => throw new \Exception("Database driver '{$driver}' is not supported for auto-creation.")
            };

            CLI::write("Database '$database' created successfully.", 'green');
        } catch (PDOException | \Exception $e) {
            CLI::error('Failed to create database: ' . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Check if MySQL/MariaDB database exists
     */
    private function checkMysqlDatabaseExists(array $dbConfig): bool
    {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbConfig['database']}'");

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if PostgreSQL database exists
     */
    private function checkPgsqlDatabaseExists(array $dbConfig): bool
    {
        $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datname = '{$dbConfig['database']}'");

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if SQL Server database exists
     */
    private function checkSqlsrvDatabaseExists(array $dbConfig): bool
    {
        $dsn = "sqlsrv:Server={$dbConfig['host']},{$dbConfig['port']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $stmt = $pdo->query("SELECT name FROM sys.databases WHERE name = '{$dbConfig['database']}'");

        return $stmt->rowCount() > 0;
    }

    /**
     * Handle unsupported driver
     */
    private function handleUnsupportedDriver(string $driver, string $operation): bool
    {
        CLI::write("Warning: Auto-{$operation} not supported for '{$driver}'. Assuming database exists.", 'yellow');

        return true;
    }

    /**
     * Create MySQL/MariaDB database
     */
    private function createMysqlDatabase(array $dbConfig): void
    {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);

        $charset = $dbConfig['charset'];
        $collation = $dbConfig['collation'];
        $database = $dbConfig['database'];
        $pdo->exec("CREATE DATABASE `$database` CHARACTER SET $charset COLLATE $collation");
    }

    /**
     * Create PostgreSQL database
     */
    private function createPgsqlDatabase(array $dbConfig): void
    {
        $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $database = $dbConfig['database'];
        $pdo->exec("CREATE DATABASE \"$database\"");

        if (! empty($dbConfig['charset'])) {
            $charset = $dbConfig['charset'];
            $pdo->exec("ALTER DATABASE \"$database\" SET client_encoding TO '$charset'");
        }
    }

    /**
     * Create SQLite database
     */
    private function createSqliteDatabase(array $dbConfig): void
    {
        $database = $dbConfig['database'];
        $directory = dirname($database);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($database, '');
        chmod($database, 0644);
    }

    /**
     * Create SQL Server database
     */
    private function createSqlsrvDatabase(array $dbConfig): void
    {
        $dsn = "sqlsrv:Server={$dbConfig['host']},{$dbConfig['port']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $database = $dbConfig['database'];
        $pdo->exec("CREATE DATABASE [$database]");

        if (! empty($dbConfig['collation'])) {
            $collation = $dbConfig['collation'];
            $pdo->exec("ALTER DATABASE [$database] COLLATE $collation");
        }
    }

    /**
     * Drop all database tables
     */
    public function dropAllTables($connection): void
    {
        $schema = $connection->getSchemaBuilder();
        $driver = $connection->getDriverName();

        // Disable foreign key checks
        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = OFF;');
        }

        // Drop all tables
        $schema->dropAllTables();

        // Re-enable foreign key checks
        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = ON;');
        }

        CLI::write('Dropped all tables successfully.', 'green');
    }
}
