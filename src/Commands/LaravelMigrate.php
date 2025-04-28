<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Eloquent;
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
     * @var Eloquent Configuration values for Eloquent
     */
    protected $eloquentConfig;

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
        $this->eloquentConfig = config('Eloquent');
        $this->dbConfig = [
            'host' => env('database.default.hostname', $this->eloquentConfig->databaseHost),
            'driver' => env('database.default.DBDriver', $this->eloquentConfig->databaseDriver),
            'database' => env('database.default.database', $this->eloquentConfig->databaseName),
            'username' => env('database.default.username', $this->eloquentConfig->databaseUsername),
            'password' => env('database.default.password', $this->eloquentConfig->databasePassword),
            'charset' => env('database.default.DBCharset', $this->eloquentConfig->databaseCharset),
            'collation' => env('database.default.DBCollat', $this->eloquentConfig->databaseCollation),
            'prefix' => env('database.default.DBPrefix', $this->eloquentConfig->databasePrefix),
            'port' => env('database.default.port', $this->eloquentConfig->databasePort),
        ];

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
