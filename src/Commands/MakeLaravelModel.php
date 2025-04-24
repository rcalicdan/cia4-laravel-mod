<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelModel\ModelGeneratorHandler;

class MakeLaravelModel extends BaseCommand
{
    /**
     * The command group under which this command appears in the CLI.
     * 
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The command name that will be used to call this command.
     * 
     * @var string
     */
    protected $name = 'make:eloquent-model';

    /**
     * The command description shown in the CLI help.
     * 
     * @var string
     */
    protected $description = 'Create a new Laravel-style Eloquent model with optional migration.';

    /**
     * The command usage syntax shown in the CLI help.
     * 
     * @var string
     */
    protected $usage = 'make:eloquent-model [<name>] [options]';

    /**
     * The command arguments with their descriptions.
     * 
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The model class name (e.g., User or Common/User).',
    ];

    /**
     * The command options with their descriptions.
     * 
     * @var array<string, string>
     */
    protected $options = [
        '-m' => 'Create a new migration file for the model.',
        '--force' => 'Force overwrite existing model file.',
    ];

    /** Standard exit codes */
    private const EXIT_SUCCESS = 0;
    private const EXIT_ERROR   = 1;

    /**
     * Executes the command to create a Laravel-style Eloquent model with optional migration.
     * 
     * The method performs the following operations:
     * 1. Prompts for or retrieves the model name from parameters
     * 2. Initializes the model generator handler
     * 3. Resolves model details including namespace and file paths
     * 4. Creates the model file with optional force overwrite
     * 5. Optionally creates a migration file if requested
     *
     * @param array $params Command parameters including:
     *                      - 'name' (string): The model class name
     *                      - 'force' (bool): Force overwrite existing file
     * @return int Exit code (0 for success, 1 for error)
     */
    public function run(array $params): int
    {
        helper('filesystem');

        $fullModelName = $params[0] ?? CLI::prompt('Model class name (e.g., User or Common/User)');
        if (empty($fullModelName)) {
            CLI::error('Model name cannot be empty.');
            return self::EXIT_ERROR;
        }

        $handler = new ModelGeneratorHandler();
        $details = $handler->resolveModelDetails($fullModelName);

        if (!$details) {
            return self::EXIT_ERROR;
        }

        $force = $params['force'] ?? CLI::getOption('force') ?? false;
        $modelCreated = $handler->createModelFile($details, $force);

        if ($modelCreated !== self::EXIT_SUCCESS) {
            return $modelCreated;
        }

        if (CLI::getOption('m')) {
            $migrationCreated = $handler->createMigrationFile($details['baseClassName']);
            if ($migrationCreated !== self::EXIT_SUCCESS) {
                CLI::error("Model was created, but migration creation failed.");
            }
        }

        return self::EXIT_SUCCESS;
    }
}
