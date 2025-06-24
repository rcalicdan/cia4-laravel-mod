<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Exception;

/**
 * Command to generate a Laravel-style job class in a CodeIgniter 4 application.
 *
 * This command creates a new job class that extends the base Job class from the
 * Ci4Larabridge package. It supports nested namespaces (e.g., Email/SendWelcome)
 * and includes options for sync jobs. The command ensures the target directory
 * exists, checks for existing files, and generates a clean class file with only
 * constructor and handle methods.
 */
class MakeJob extends BaseCommand
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
    protected $name = 'make:job';

    /**
     * A brief description of the command's purpose.
     *
     * @var string
     */
    protected $description = 'Generates a new job class for queue processing.';

    /**
     * The command's usage instructions.
     *
     * @var string
     */
    protected $usage = 'make:job <name> [options]';

    /**
     * Available arguments for the command.
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The name of the job class (e.g., SendEmail or Email/SendWelcome).',
    ];

    /**
     * Available options for the command.
     *
     * @var array<string, string>
     */
    protected $options = [
        '--sync' => 'Create a synchronous job (does not implement ShouldQueue).',
        '--force' => 'Force overwrite existing file.',
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
     * Executes the command to generate a job class.
     *
     * Retrieves or prompts for the job name, resolves class details, ensures the target
     * directory exists, checks for existing files, generates the class template, and writes
     * the file. Returns an exit code indicating success or failure.
     *
     * @param  array  $params  Command parameters and options.
     * @return int Exit code (0 for success, 1 for error).
     */
    public function run(array $params): int
    {
        helper('filesystem');

        $name = $this->getJobName($params);
        if ($name === null) {
            return self::EXIT_ERROR;
        }

        $details = $this->resolveTargetDetails($name);
        ['className' => $className, 'fullNamespace' => $fullNamespace, 'targetDir' => $targetDir, 'targetFile' => $targetFile] = $details;

        if (! $this->ensureDirectoryExists($targetDir)) {
            return self::EXIT_ERROR;
        }

        $force = $params['force'] ?? CLI::getOption('force') ?? false;
        if (! $force && file_exists($targetFile)) {
            $this->showFileExistsError($targetFile);

            return self::EXIT_ERROR;
        }

        $sync = $params['sync'] ?? CLI::getOption('sync') ?? false;
        $content = $this->generateContent($fullNamespace, $className, $sync);

        if ($this->writeFileContent($targetFile, $content)) {
            $jobType = $sync ? 'Synchronous job' : 'Job';
            CLI::write($jobType.' created successfully: '.CLI::color(str_replace(APPPATH, 'app/', $targetFile), 'green'));

            return self::EXIT_SUCCESS;
        }

        CLI::error('Error writing file: '.str_replace(APPPATH, 'app/', $targetFile));

        return self::EXIT_ERROR;
    }

    /**
     * Retrieves the job name from parameters or prompts the user.
     *
     * Ensures a valid job name is provided, displaying an error if the input is empty.
     *
     * @param  array  $params  Command parameters containing the job name.
     * @return string|null The job name, or null if invalid.
     */
    private function getJobName(array $params): ?string
    {
        $name = $params[0] ?? CLI::prompt('Job class name');

        if (empty($name)) {
            CLI::error('You must provide a job class name.');

            return null;
        }

        return $name;
    }

    /**
     * Resolves the class name, namespace, and file paths from the input name.
     *
     * Processes the input to determine the class name, full namespace, and file paths,
     * supporting nested directories and ensuring cross-platform compatibility.
     *
     * @param  string  $name  The raw job name (e.g., Email/SendWelcome).
     * @return array Associative array with className, fullNamespace, targetDir, targetFile.
     */
    private function resolveTargetDetails(string $name): array
    {
        $normalizedName = trim(str_replace('\\', '/', $name), '/ ');
        $className = basename($normalizedName);
        $subNamespacePart = trim(dirname($normalizedName), './ ');

        $fullNamespace = 'App\\Jobs';
        if ($subNamespacePart !== '.' && $subNamespacePart !== '') {
            $fullNamespace .= '\\'.str_replace('/', '\\', $subNamespacePart);
        }

        $basePath = APPPATH.'Jobs';
        $targetDir = $basePath;
        if ($subNamespacePart !== '.' && $subNamespacePart !== '') {
            $targetDir .= DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $subNamespacePart);
        }
        $targetFile = $targetDir.DIRECTORY_SEPARATOR.$className.'.php';

        return compact('className', 'fullNamespace', 'targetDir', 'targetFile');
    }

    /**
     * Ensures the specified directory exists, creating it if necessary.
     *
     * Creates the directory with appropriate permissions, handling errors and providing
     * feedback on success or failure.
     *
     * @param  string  $directory  The absolute path to the directory.
     * @return bool True if the directory exists or was created, false on failure.
     */
    private function ensureDirectoryExists(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        try {
            if (! mkdir($directory, 0755, true)) {
                CLI::error("Error: Could not create directory: {$directory}");

                return false;
            }
            CLI::write('Directory created: '.str_replace(APPPATH, 'app/', $directory), 'dark_gray');

            return true;
        } catch (Exception $e) {
            CLI::error("Error creating directory: {$directory}. Reason: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Displays an error message when the target file already exists.
     *
     * Informs the user about the existing file and suggests using the --force option.
     *
     * @param  string  $targetFile  The absolute path to the file.
     */
    private function showFileExistsError(string $targetFile): void
    {
        CLI::error('File already exists: '.CLI::color(str_replace(APPPATH, 'app/', $targetFile), 'light_cyan'));
        CLI::write('Use the --force option to overwrite.', 'yellow');
    }

    /**
     * Generates the PHP content for the job class.
     *
     * Creates a class template with the specified namespace and class name, extending
     * the base Job class and including only constructor and handle methods.
     *
     * @param  string  $namespace  The full namespace for the class.
     * @param  string  $className  The class name.
     * @param  bool  $sync  Whether to create a synchronous job.
     * @return string The generated PHP code.
     */
    private function generateContent(string $namespace, string $className, bool $sync = false): string
    {
        $template = $sync ? $this->getSyncJobTemplate() : $this->getQueuedJobTemplate();

        return str_replace(
            ['{namespace}', '{className}'],
            [$namespace, $className],
            $template
        );
    }

    /**
     * Writes the generated content to the target file.
     *
     * Attempts to write the content to the specified file, returning the result of the operation.
     *
     * @param  string  $targetFile  The absolute path to the file.
     * @param  string  $content  The content to write.
     * @return bool True on success, false on failure.
     */
    private function writeFileContent(string $targetFile, string $content): bool
    {
        return write_file($targetFile, $content);
    }

    /**
     * Retrieves the template for a queued job class.
     *
     * Provides a clean template extending the Job class with only constructor and handle methods.
     *
     * @return string The template content.
     */
    private function getQueuedJobTemplate(): string
    {
        return <<<PHP
<?php

namespace {namespace};

use Rcalicdan\Ci4Larabridge\Queue\Job;

class {className} extends Job
{
    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
PHP;
    }

    /**
     * Retrieves the template for a synchronous job class.
     *
     * Provides a clean template for synchronous jobs with only constructor and handle methods.
     *
     * @return string The template content.
     */
    private function getSyncJobTemplate(): string
    {
        return <<<PHP
<?php

namespace {namespace};

use Rcalicdan\Ci4Larabridge\Traits\Queue\Dispatchable;

class {className}
{
    use Dispatchable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
PHP;
    }
}