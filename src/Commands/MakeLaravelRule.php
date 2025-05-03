<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Exception;

/**
 * Command to generate a Laravel-style validation rule class in a CodeIgniter 4 application.
 *
 * This command creates a new validation rule class, supporting nested namespaces
 * (e.g., Common/NoObsceneWord). It ensures the target directory exists, checks for
 * existing files, and generates a class file with a predefined template implementing
 * Laravel's ValidationRule interface. The command supports a force overwrite option.
 */
class MakeLaravelRule extends BaseCommand
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
    protected $name = 'make:laravel-rule';

    /**
     * A brief description of the command's purpose.
     *
     * @var string
     */
    protected $description = 'Generates a new Laravel validation rule class.';

    /**
     * The command's usage instructions.
     *
     * @var string
     */
    protected $usage = 'make:laravel-rule <name> [options]';

    /**
     * Available arguments for the command.
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The name of the rule class (e.g., NoObsceneWord or Common/NoObsceneWord).',
    ];

    /**
     * Available options for the command.
     *
     * @var array<string, string>
     */
    protected $options = [
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
     * Executes the command to generate a validation rule class.
     *
     * Retrieves or prompts for the rule name, resolves class details, ensures the target
     * directory exists, checks for existing files, generates the class template, and writes
     * the file. Returns an exit code indicating success or failure.
     *
     * @param  array  $params  Command parameters and options.
     * @return int Exit code (0 for success, 1 for error).
     */
    public function run(array $params): int
    {
        helper('filesystem');

        $name = $this->getRuleName($params);
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

        $content = $this->generateContent($fullNamespace, $className);

        if ($this->writeFileContent($targetFile, $content)) {
            CLI::write('Rule created successfully: '.CLI::color(str_replace(APPPATH, 'app/', $targetFile), 'green'));

            return self::EXIT_SUCCESS;
        }

        CLI::error('Error writing file: '.str_replace(APPPATH, 'app/', $targetFile));

        return self::EXIT_ERROR;
    }

    /**
     * Retrieves the rule name from parameters or prompts the user.
     *
     * Ensures a valid rule name is provided, displaying an error if the input is empty.
     *
     * @param  array  $params  Command parameters containing the rule name.
     * @return string|null The rule name, or null if invalid.
     */
    private function getRuleName(array $params): ?string
    {
        $name = $params[0] ?? CLI::prompt('Rule class name');

        if (empty($name)) {
            CLI::error('You must provide a rule class name.');

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
     * @param  string  $name  The raw rule name (e.g., Common/NoObsceneWord).
     * @return array Associative array with className, fullNamespace, targetDir, targetFile.
     */
    private function resolveTargetDetails(string $name): array
    {
        $normalizedName = trim(str_replace('\\', '/', $name), '/ ');
        $className = basename($normalizedName);
        $subNamespacePart = trim(dirname($normalizedName), './ ');

        $fullNamespace = 'App\\Rules';
        if ($subNamespacePart !== '.' && $subNamespacePart !== '') {
            $fullNamespace .= '\\'.str_replace('/', '\\', $subNamespacePart);
        }

        $basePath = APPPATH.'Rules';
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
     * Generates the PHP content for the validation rule class.
     *
     * Creates a class template with the specified namespace and class name, implementing
     * the ValidationRule interface and including a validate method.
     *
     * @param  string  $namespace  The full namespace for the class.
     * @param  string  $className  The class name.
     * @return string The generated PHP code.
     */
    private function generateContent(string $namespace, string $className): string
    {
        $template = $this->getTemplate();

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
     * Retrieves the template for the validation rule class.
     *
     * Provides a default class template implementing the ValidationRule interface,
     * with a placeholder validate method.
     *
     * @return string The template content.
     */
    private function getTemplate(): string
    {
        return <<<PHP
<?php

namespace {namespace};

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class {className} implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param string \$attribute The attribute name being validated
     * @param mixed \$value The value of the attribute
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString \$fail The failure callback
     * @return void
     */
    public function validate(string \$attribute, mixed \$value, Closure \$fail): void
    {
        // Example:
        // if (!preg_match('/^[a-zA-Z0-9]+$/', \$value)) {
        //     \$fail('The :attribute must be alphanumeric.');
        // }
    }
}
PHP;
    }
}
