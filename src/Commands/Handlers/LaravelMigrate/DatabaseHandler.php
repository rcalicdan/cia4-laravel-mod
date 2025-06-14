<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate;

use CodeIgniter\CLI\CLI;
use PDO;
use PDOException;
use Rcalicdan\Ci4Larabridge\Database\EloquentDatabase;

class DatabaseHandler
{
    protected EloquentDatabase $eloquentDatabase;
    protected SqliteHandler $sqliteHandler;

    public function __construct()
    {
        $this->eloquentDatabase = new EloquentDatabase;
        $this->sqliteHandler = new SqliteHandler;
    }

    /**
     * Check if the specified database exists
     */
    public function checkDatabaseExists(?string $connection = null): bool
    {
        try {
            $dbConfig = $this->eloquentDatabase->getDatabaseInformation($connection);
            $driver = strtolower($dbConfig['driver']);

            return match ($driver) {
                'mysql', 'mariadb' => $this->checkMysqlDatabaseExists($dbConfig),
                'pgsql' => $this->checkPgsqlDatabaseExists($dbConfig),
                'sqlite' => $this->checkSqliteDatabaseExists($dbConfig),
                'sqlsrv' => $this->checkSqlsrvDatabaseExists($dbConfig),
                default => $this->handleUnsupportedDriver($driver, 'checking')
            };
        } catch (PDOException $e) {
            CLI::error('Database connection error: '.$e->getMessage());
            exit(1);
        }
    }

    /**
     * Create database based on configuration
     */
    public function createDatabase(?string $connection = null): void
    {
        try {
            $dbConfig = $this->eloquentDatabase->getDatabaseInformation($connection);
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
        } catch (PDOException|\Exception $e) {
            CLI::error('Failed to create database: '.$e->getMessage());
            exit(1);
        }
    }

    /**
     * Check if SQLite database exists using the dedicated handler
     */
    private function checkSqliteDatabaseExists(array $dbConfig): bool
    {
        $resolvedPath = $this->sqliteHandler->resolveDatabasePath($dbConfig['database']);
        return $this->sqliteHandler->databaseExists($resolvedPath);
    }

    /**
     * Create SQLite database using the dedicated handler
     */
    private function createSqliteDatabase(array $dbConfig): void
    {
        $resolvedPath = $this->sqliteHandler->resolveDatabasePath($dbConfig['database']);
        
        if (!$this->sqliteHandler->createDatabase($resolvedPath)) {
            throw new \Exception("Failed to create SQLite database at: {$resolvedPath}");
        }
    }

    /**
     * Check if MySQL/MariaDB database exists
     */
    private function checkMysqlDatabaseExists(array $dbConfig): bool
    {
        try {
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']}";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$dbConfig['database']}'");

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
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
     * Drop all database tables after temporarily disabling foreign key constraints.
     *
     * Ensures constraints are re-enabled even if dropping fails.
     */
    public function dropAllTables($connection): void
    {
        $schema = $connection->getSchemaBuilder();

        $this->disableForeignKeyConstraints($connection);

        try {
            $schema->dropAllTables();
            CLI::write('Dropped all tables successfully.', 'green');
        } finally {
            $this->enableForeignKeyConstraints($connection);
        }
    }

    /**
     * Disable foreign key constraints (or equivalent) for the given connection.
     */
    protected function disableForeignKeyConstraints($connection): void
    {
        $driver = $connection->getDriverName();

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $connection->statement('SET FOREIGN_KEY_CHECKS=0;');
                break;
            case 'sqlite':
                $connection->statement('PRAGMA foreign_keys = OFF;');
                break;
            case 'pgsql':
                $connection->statement('SET session_replication_role = replica;');
                break;
            case 'sqlsrv':
                $connection->statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');
                break;
        }
    }

    /**
     * Re-enable foreign key constraints (or equivalent) for the given connection.
     */
    protected function enableForeignKeyConstraints($connection): void
    {
        $driver = $connection->getDriverName();

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                $connection->statement('SET FOREIGN_KEY_CHECKS=1;');
                break;
            case 'sqlite':
                $connection->statement('PRAGMA foreign_keys = ON;');
                break;
            case 'pgsql':
                $connection->statement('SET session_replication_role = default;');
                break;
            case 'sqlsrv':
                $connection->statement('EXEC sp_msforeachtable "ALTER TABLE ? CHECK CONSTRAINT all"');
                break;
        }
    }
}