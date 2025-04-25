<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Illuminate\Support\Str;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelMigration\MigrationCodeGenerator;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelMigration\MigrationFileHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelMigration\MigrationInputHandler;

/**
 * Laravel Migration Generator Command
 *
 * Creates Laravel-style migration files within the designated
 * migration directory for CodeIgniter 4 projects using Eloquent/Capsule.
 * Supports generating 'create', 'modify', or generic migration templates.
 */
class MakeLaravelMigration extends BaseCommand
{
    /**
     * The command's group used in spark list.
     *
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The command's name.
     *
     * @var string
     */
    protected $name = 'make:eloquent-migration';

    /**
     * The command's short description.
     *
     * @var string
     */
    protected $description = 'Creates a new Laravel-style migration file.';

    /**
     * The command's usage instructions.
     *
     * @var string
     */
    protected $usage = 'make:eloquent-migration [<name>] [--table=<table>] [--force]';

    /**
     * The command's defined arguments.
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The migration name (e.g., CreateUsersTable, AddEmailToPostsTable). Optional, will prompt if empty.',
    ];

    /**
     * The command's defined options.
     * Note: --table value is parsed manually via argv due to observed issues with CLI::getOption.
     *
     * @var array<string, string>
     */
    protected $options = [
        '--table' => 'Specify the table name for generating a "modify" migration template.',
        '--force' => 'Force overwrite if a file with the exact same name exists (use with caution).',
    ];

    /**
     * Base path where Laravel-style migration files will be stored.
     */
    protected string $migrationPath = APPPATH.'Database/Eloquent-Migrations/';

    /**
     * Standard Command Exit Codes.
     */
    private const EXIT_SUCCESS = 0;
    private const EXIT_ERROR = 1;

    /**
     * Handler instances
     */
    private MigrationInputHandler $inputHandler;
    private MigrationFileHandler $fileHandler;
    private MigrationCodeGenerator $codeGenerator;

    /**
     * Executes the command logic.
     *
     * Initializes handlers, checks dependencies, processes input parameters,
     * and creates the migration file through a series of steps:
     * 1. Determines the migration name from input parameters
     * 2. Gets explicit table name from --table option if provided
     * 3. Creates the migration file with appropriate template
     *
     * @param  array  $params  Command parameters passed by Spark.
     * @return int Exit code (EXIT_SUCCESS or EXIT_ERROR).
     */
    public function run(array $params): int
    {
        $this->inputHandler = new MigrationInputHandler;
        $this->fileHandler = new MigrationFileHandler($this->migrationPath);
        $this->codeGenerator = new MigrationCodeGenerator;

        helper('filesystem');

        if (! class_exists(Str::class)) {
            CLI::error('Missing dependency: illuminate/support. Please run: composer require illuminate/support');

            return self::EXIT_ERROR;
        }

        $migrationName = $this->inputHandler->getMigrationName($params);
        if ($migrationName === null) {
            return self::EXIT_ERROR;
        }

        $explicitTableName = $this->inputHandler->getTableOptionFromArgv();

        if ($this->createMigrationFile($migrationName, $explicitTableName)) {
            return self::EXIT_SUCCESS;
        }

        return self::EXIT_ERROR;
    }

    /**
     * Orchestrates the creation of the migration file.
     *
     * Handles the complete migration file creation process including:
     * - Converting migration name to snake_case format
     * - Generating the complete file path
     * - Validating if file exists (unless --force is used)
     * - Ensuring target directory exists
     * - Determining migration type and generating appropriate code
     * - Writing the final migration file
     *
     * @param  string  $migrationName  The descriptive name of the migration (e.g., CreateUsersTable).
     * @param  string|null  $explicitTableName  Table name provided via --table option, if any.
     * @return bool True on successful file creation, false otherwise.
     */
    protected function createMigrationFile(string $migrationName, ?string $explicitTableName): bool
    {
        $snakeCaseName = Str::snake($migrationName);
        $fileName = $this->fileHandler->generateFileName($snakeCaseName);
        $targetFile = $this->fileHandler->getFullPath($fileName);
        $relativeTargetFile = $this->fileHandler->getRelativePath($targetFile);

        if (! $this->fileHandler->validateMigrationDoesNotExist($targetFile, $relativeTargetFile)) {
            $force = $this->inputHandler->isForceEnabled();
            if (! $force) {
                CLI::write('Use the --force option to overwrite if necessary (use with caution!).', 'yellow');

                return false;
            }
            CLI::write("Overwriting existing file due to --force option: {$relativeTargetFile}", 'light_red');
        }

        if (! $this->fileHandler->ensureMigrationDirectoryExists()) {
            return false;
        }

        $code = $this->generateMigrationCode($migrationName, $explicitTableName);

        if ($this->fileHandler->writeMigrationFileContent($targetFile, $relativeTargetFile, $code)) {
            CLI::write('Migration created successfully: '.CLI::color($relativeTargetFile, 'green'));

            return true;
        }

        return false;
    }

    /**
     * Determines the migration type and generates the corresponding code.
     *
     * Generates appropriate migration code based on:
     * - Explicit table name (forces "modify" template)
     * - Inferred table name from migration name (creates "create" template)
     * - Falls back to generic template if table cannot be inferred
     *
     * @param  string  $migrationName  Original migration name provided by user.
     * @param  string|null  $explicitTableName  Table name from --table option.
     * @return string Generated PHP code for the migration file.
     */
    private function generateMigrationCode(string $migrationName, ?string $explicitTableName): string
    {
        if (! empty($explicitTableName)) {
            CLI::write('Generating MODIFY migration for table: '.CLI::color($explicitTableName, 'cyan'));

            return $this->codeGenerator->generateModifyMigrationCode($explicitTableName);
        }

        $inferredTableName = $this->codeGenerator->inferTableNameForCreate($migrationName);
        if ($inferredTableName) {
            CLI::write('Generating CREATE migration for table: '.CLI::color($inferredTableName, 'cyan'));

            return $this->codeGenerator->generateCreateMigrationCode($inferredTableName);
        }

        CLI::write('Generating GENERIC migration (could not infer table from name, use --table=<table> for specific table modifications)', 'cyan');

        return $this->codeGenerator->generateGenericMigrationCode();
    }
}
