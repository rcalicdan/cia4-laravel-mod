<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\DatabaseHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\MigrationHandler as LaravelMigrateMigrationHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\OutputHandler;
use Rcalicdan\Ci4Larabridge\Database\EloquentDatabase;

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

        } catch (\Exception $e) {
            CLI::error('Error executing migration command: '.$e->getMessage());
        }
    }

    /**
     * Load database configuration from environment
     */
    private function loadDatabaseConfig()
    {
        $eloqentManager = new EloquentDatabase();
        $this->dbConfig = $eloqentManager->getDatabaseInformation();
    }

    /**
     * Prompt user and create database if confirmed
     */
    private function promptAndCreateDatabase()
    {
        CLI::write("Database '{$this->dbConfig['database']}' does not exist.", 'yellow');
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
