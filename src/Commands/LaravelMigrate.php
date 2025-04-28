<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\DatabaseHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\MigrationHandler as LaravelMigrateMigrationHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\OutputHandler;

class LaravelMigrate extends BaseCommand
{
    protected $group = 'Database';
    protected $name = 'eloquent:migrate';
    protected $description = 'Runs Laravel migrations in CodeIgniter 4';
    protected $usage = 'eloquent:migrate [up|down|refresh|status|fresh]';
    protected $arguments = [
        'action' => 'The action to perform: up, down, refresh, status, or fresh (default: up)',
    ];
    protected $options = [];

    // Handlers
    protected $dbHandler;
    protected $migrationHandler;
    protected $outputHandler;

    protected $dbConfig = [];

    /**
     * Execute the command
     */
    public function run(array $params)
    {
        try {
            // Initialize handlers
            $this->dbHandler = new DatabaseHandler;
            $this->migrationHandler = new LaravelMigrateMigrationHandler;
            $this->outputHandler = new OutputHandler;

            // Load database configuration
            $this->loadDatabaseConfig();

            // Check if database exists before proceeding
            if (! $this->dbHandler->checkDatabaseExists($this->dbConfig)) {
                $this->promptAndCreateDatabase();
            }

            // Setup migration environment
            $this->migrationHandler->setupEnvironment($this->dbConfig);

            // Execute the requested action
            $action = $params[0] ?? 'up';
            $this->executeAction($action);
        } catch (\PDOException $e) {
            // Special handling for database connection errors
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                $this->promptAndCreateDatabase();
            } else {
                CLI::error('Database connection error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            CLI::error('Error executing migration command: ' . $e->getMessage());
        }
    }

    /**
     * Load database configuration from environment
     */
    /**
     * Load database configuration from environment
     */
    private function loadDatabaseConfig()
    {
        // Try loading directly from environment variables first
        $this->dbConfig = [
            'host' => env('database.default.hostname', 'localhost'),
            'driver' => env('database.default.DBDriver', 'mysql'),
            'database' => env('database.default.database', ''),
            'username' => env('database.default.username', 'root'),
            'password' => env('database.default.password', ''),
            'charset' => env('database.default.DBCharset', 'utf8'),
            'collation' => env('database.default.DBCollat', 'utf8_general_ci'),
            'prefix' => env('database.default.DBPrefix', ''),
            'port' => env('database.default.port', '3306'),
        ];

        // Debug the loaded configuration
        CLI::write('Loaded database configuration:', 'green');
        CLI::write("Host: {$this->dbConfig['host']}");
        CLI::write("Driver: {$this->dbConfig['driver']}");
        CLI::write("Database: {$this->dbConfig['database']}");

        // Verify if we have a database name
        if (empty($this->dbConfig['database'])) {
            CLI::error('Could not determine database name from environment. Please check your .env file.');
            CLI::write('Ensure you have set database.default.database in your .env file.', 'yellow');
            exit(1);
        }
    }

    /**
     * Prompt user and create database if confirmed
     */
    private function promptAndCreateDatabase()
    {
        // Check if database key exists and display appropriate message
        $dbName = $this->dbConfig['database'] ?? '(undefined)';
        CLI::write("Database '{$dbName}' does not exist.", 'yellow');

        // Make sure we have a database name before proceeding
        if (!isset($this->dbConfig['database']) || empty($this->dbConfig['database'])) {
            CLI::error('Database name is not defined in your configuration. Please check your .env file or database configuration.');
            exit(1);
        }

        $confirm = CLI::prompt('Would you like to create it?', ['y', 'n']);

        if ($confirm === 'y') {
            $this->dbHandler->createDatabase($this->dbConfig);
        } else {
            CLI::error('Database is required to continue. Aborting.');
            exit(1);
        }
    }

    /**
     * Execute the selected action
     */
    private function executeAction(string $action)
    {
        switch ($action) {
            case 'up':
                $migrations = $this->migrationHandler->runMigrations();
                $this->outputHandler->showUpResult($migrations);

                break;

            case 'down':
                $migrations = $this->migrationHandler->rollbackMigrations();
                $this->outputHandler->showDownResult($migrations);

                break;

            case 'refresh':
                $this->migrationHandler->refreshMigrations();
                $this->outputHandler->showRefreshResult();

                break;

            case 'status':
                $status = $this->migrationHandler->getMigrationStatus();
                $this->outputHandler->showStatusResult($status);

                break;

            case 'fresh':
                $this->handleFreshAction();

                break;

            default:
                $this->outputHandler->showUsage();

                break;
        }
    }

    private function handleFreshAction()
    {
        CLI::write('Dropping all tables...', 'yellow');
        $connection = $this->migrationHandler->getConnection();
        $this->dbHandler->dropAllTables($connection);

        // Recreate migrations table
        CLI::write('Recreating migrations table...', 'yellow');
        $this->migrationHandler->setupEnvironment($this->dbConfig);

        CLI::write('Running all migrations...', 'yellow');
        $migrations = $this->migrationHandler->runMigrations();
        $this->outputHandler->showUpResult($migrations);
        CLI::write('Database has been freshened.', 'green');
    }
}
