<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelModel\ModelGeneratorHandler;

/**
 * Command to generate a Laravel-style Eloquent model with an optional migration file.
 *
 * This command creates a new Eloquent model class in a CodeIgniter 4 application,
 * supporting nested namespaces (e.g., Common/User). It can also generate a corresponding
 * migration file if requested. The command includes options to force overwrite existing
 * files and provides user prompts for missing inputs.
 */
class MakeLaravelModel extends BaseCommand
{
    /**
     * The group this command belongs to.
     *
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'make:eloquent-model';

    /**
     * A brief description of the command's purpose.
     *
     * @var string
     */
    protected $description = 'Create a new Laravel-style Eloquent model with optional migration.';

    /**
     * The command's usage instructions.
     *
     * @var string
     */
    protected $usage = 'make:eloquent-model [<name>] [options]';

    /**
     * Available arguments for the command.
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The model class name (e.g., User or Common/User).',
    ];

    /**
     * Available options for the command.
     *
     * @var array<string, string>
     */
    protected $options = [
        '-m' => 'Create a new migration file for the model.',
        '--force' => 'Force overwrite existing model file.',
    ];

    /**
     * Standard exit code for successful execution.
     *
     * @var int
     */
    private const EXIT_SUCCESS = 0;

    /**
     * Standard exit code for error conditions.
     *
     * @var int
     */
    private const EXIT_ERROR = 1;

    /**
     * Executes the command to create a Laravel-style Eloquent model.
     *
     * Prompts for or retrieves the model name, resolves model details (namespace and paths),
     * creates the model file with optional force overwrite, and generates a migration file
     * if requested. Returns an exit code indicating success or failure.
     *
     * @param array $params Command parameters, including:
     *                      - 'name' (string): The model class name (optional).
     *                      - 'force' (bool): Force overwrite existing file (optional).
     *                      - '-m' (bool): Create a migration file (optional).
     * @return int Exit code (0 for success, 1 for error).
     */
    public function run(array $params): int
    {
        helper('filesystem');

        $fullModelName = $params[0] ?? CLI::prompt('Model class name (e.g., User or Common/User)');
        if (empty($fullModelName)) {
            CLI::error('Model name cannot be empty.');
            return self::EXIT_ERROR;
        }

        $handler = new ModelGeneratorHandler;
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
                CLI::error('Model was created, but migration creation failed.');
            }
        }

        return self::EXIT_SUCCESS;
    }
}