<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Eloquent;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\DatabaseHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\MigrationHandler as LaravelMigrateMigrationHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\OutputHandler;

/**
 * Command to run Laravel migrations within a CodeIgniter 4 application.
 *
 * This command integrates Laravel's migration system with CodeIgniter 4,
 * allowing execution of migration actions such as up, down, refresh, status,
 * and fresh. It handles database configuration, checks for database existence,
 * and provides user prompts for database creation if needed.
 */
class LaravelMigrate extends BaseCommand
{
    /**
     * The group this command belongs to.
     *
     * @var string
     */
    protected $group = 'Database';

    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'eloquent:migrate';

    /**
     * A brief description of the command's purpose.
     *
     * @var string
     */
    protected $description = 'Runs Laravel migrations in CodeIgniter 4';

    /**
     * The command's usage instructions.
     *
     * @var string
     */
    protected $usage = 'eloquent:migrate [up|down|refresh|status|fresh]';

    /**
     * Available arguments for the command.
     *
     * @var array
     */
    protected $arguments = [
        'action' => 'The action to perform: up, down, refresh, status, or fresh (default: up)',
    ];

    /**
     * Command options (currently none).
     *
     * @var array
     */
    protected $options = [];

    /**
     * Handler for database operations.
     *
     * @var DatabaseHandler
     */
    protected $dbHandler;

    /**
     * Handler for migration operations.
     *
     * @var LaravelMigrateMigrationHandler
     */
    protected $migrationHandler;

    /**
     * Handler for output formatting and display.
     *
     * @var OutputHandler
     */
    protected $outputHandler;

    /**
     * Database configuration array.
     *
     * @var array
     */
    protected $dbConfig = [];

    /**
     * Eloquent configuration instance.
     *
     * @var Eloquent
     */
    protected $eloquentConfig;

    /**
     * Executes the specified migration action.
     *
     * Initializes handlers, loads database configuration, checks for database
     * existence, and performs the requested migration action. Handles exceptions
     * for database connection issues and general errors.
     *
     * @param  array  $params  Command parameters, with the first element being the action.
     * @return void
     *
     * @throws \PDOException If a database connection error occurs.
     * @throws \Exception If a general error occurs during execution.
     */
    public function run(array $params)
    {
        try {
            $this->dbHandler = new DatabaseHandler;
            $this->migrationHandler = new LaravelMigrateMigrationHandler;
            $this->outputHandler = new OutputHandler;

            $this->loadDatabaseConfig();

            if (! $this->dbHandler->checkDatabaseExists($this->dbConfig)) {
                $this->promptAndCreateDatabase();
            }

            $this->migrationHandler->setupEnvironment($this->dbConfig);

            $action = $params[0] ?? 'up';
            $this->executeAction($action);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown database') !== false) {
                $this->promptAndCreateDatabase();
            } else {
                CLI::error('Database connection error: '.$e->getMessage());
            }
        } catch (\Exception $e) {
            CLI::error('Error executing migration command: '.$e->getMessage());
        }
    }

    /**
     * Loads database configuration from environment variables or Eloquent config.
     *
     * Populates the dbConfig property with database connection details. Exits with
     * an error if the database name is not defined.
     *
     * @return void
     */
    private function loadDatabaseConfig()
    {
        $this->eloquentConfig = config('Eloquent');
        $cfg = $this->eloquentConfig;
        $this->dbConfig = [
            'driver' => env('database.default.DBDriver',   env('DB_DRIVER',   $cfg->databaseDriver)),
            'host' => env('database.default.hostname',   env('DB_HOST',       $cfg->databaseHost)),
            'database' => env('database.default.database',   env('DB_DATABASE',   $cfg->databaseName)),
            'username' => env('database.default.username',   env('DB_USERNAME',   $cfg->databaseUsername)),
            'password' => env('database.default.password',   env('DB_PASSWORD',   $cfg->databasePassword)),
            'charset' => env('database.default.DBCharset',  env('DB_CHARSET',    $cfg->databaseCharset)),
            'collation' => env('database.default.DBCollat',   env('DB_COLLATION',  $cfg->databaseCollation)),
            'prefix' => env('database.default.DBPrefix',   env('DB_PREFIX',     $cfg->databasePrefix)),
            'port' => env('database.default.port',       env('DB_PORT',       $cfg->databasePort)),
        ];

        if (empty($this->dbConfig['database'])) {
            CLI::error('Could not determine database name from environment. Please check your .env file.');
            CLI::write('Ensure you have set database.default.database in your .env file.', 'yellow');
            exit(1);
        }
    }

    /**
     * Prompts the user to create a missing database and handles the creation process.
     *
     * Displays a prompt if the database does not exist and creates it if confirmed.
     * Exits with an error if the database name is undefined or creation is declined.
     *
     * @return void
     */
    private function promptAndCreateDatabase()
    {
        $dbName = $this->dbConfig['database'] ?? '(undefined)';
        CLI::write("Database '{$dbName}' does not exist.", 'yellow');

        if (! isset($this->dbConfig['database']) || empty($this->dbConfig['database'])) {
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
     * Executes the specified migration action.
     *
     * Routes the command to the appropriate migration handler method based on the
     * action parameter and displays the results using the output handler.
     *
     * @param  string  $action  The migration action to perform (up, down, refresh, status, fresh).
     * @return void
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

    /**
     * Handles the 'fresh' migration action.
     *
     * Drops all tables, recreates the migrations table, runs all migrations, and
     * displays the results. Provides feedback on each step of the process.
     *
     * @return void
     */
    private function handleFreshAction()
    {
        CLI::write('Dropping all tables...', 'yellow');
        $connection = $this->migrationHandler->getConnection();
        $this->dbHandler->dropAllTables($connection);

        CLI::write('Recreating migrations table...', 'yellow');
        $this->migrationHandler->setupEnvironment($this->dbConfig);

        CLI::write('Running all migrations...', 'yellow');
        $migrations = $this->migrationHandler->runMigrations();
        $this->outputHandler->showUpResult($migrations);
        CLI::write('Database has been freshened.', 'green');
    }
}
