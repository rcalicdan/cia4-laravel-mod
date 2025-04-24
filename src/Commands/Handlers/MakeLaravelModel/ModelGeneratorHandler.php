<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelModel;

use CodeIgniter\CLI\CLI;
use Exception;
use Illuminate\Support\Str;

class ModelGeneratorHandler
{
    /** Standard exit codes */
    public const EXIT_SUCCESS = 0;
    public const EXIT_ERROR   = 1;

    /**
     * Resolves class name, namespace, and file paths from the input name.
     *
     * @param string $name Input model name (can include subdirectories).
     * @return array|false Associative array with details or false on error.
     */
    public function resolveModelDetails(string $name): array|false
    {
        // Normalize the input name: replace backslashes with forward slashes, trim slashes and spaces
        $normalizedName = trim(str_replace('\\', '/', $name), '/ ');

        // Validate the name format: should be PascalCase with optional subdirectories
        if (!preg_match('/^([A-Z][a-zA-Z0-9]*\/)*[A-Z][a-zA-Z0-9]*$/', $normalizedName)) {
            CLI::error("Invalid model name format. Use PascalCase names, optionally separated by forward slashes (e.g., User, Common/User, Admin/Auth/Role).");
            return false;
        }

        // Extract the base class name (last part after slashes)
        $baseClassName = basename($normalizedName);

        // Extract the subdirectory part (before the last slash)
        $subNamespacePart = trim(dirname($normalizedName), './ ');

        // Build the full namespace: App\Models + subdirectory namespaces
        $fullNamespace = 'App\\Models';
        if ($subNamespacePart !== '.' && $subNamespacePart !== '') {
            $fullNamespace .= '\\' . str_replace('/', '\\', $subNamespacePart);
        }

        // Build the target directory path
        $baseModelPath = APPPATH . 'Models';
        $targetDir = $baseModelPath;
        if ($subNamespacePart !== '.' && $subNamespacePart !== '') {
            $targetDir .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subNamespacePart);
        }

        // Build the target file path
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $baseClassName . '.php';

        // Relative path for display
        $relativeTargetFile = str_replace(APPPATH, 'app/', $targetFile);

        return compact('baseClassName', 'subNamespacePart', 'fullNamespace', 'targetDir', 'targetFile', 'relativeTargetFile');
    }

    /**
     * Creates the model file.
     *
     * @param array $details Resolved model details from resolveModelDetails().
     * @param bool $force Override existing file if true.
     * @return int Exit code (EXIT_SUCCESS or EXIT_ERROR).
     */
    public function createModelFile(array $details, bool $force = false): int
    {
        ['targetFile' => $targetFile, 'targetDir' => $targetDir, 'relativeTargetFile' => $relativeTargetFile] = $details;

        // Check if file exists and handle force option
        if (!$force && file_exists($targetFile)) {
            CLI::error("Model already exists: " . CLI::color($relativeTargetFile, 'light_cyan'));
            CLI::write('Use the --force option to overwrite.', 'yellow');
            return self::EXIT_ERROR;
        }

        // Ensure the directory exists
        if (!$this->ensureDirectoryExists($targetDir)) {
            return self::EXIT_ERROR; // Error already shown
        }

        // Generate the model code
        $codeGenerator = new ModelCodeGenerator();
        $code = $codeGenerator->generateModelCode($details);

        // Write the file
        helper('filesystem');
        if (!write_file($targetFile, $code)) {
            CLI::error("Error creating model file: {$relativeTargetFile}");
            return self::EXIT_ERROR;
        }

        CLI::write("Model created successfully: " . CLI::color($relativeTargetFile, 'green'));
        return self::EXIT_SUCCESS;
    }

    /**
     * Creates a migration file for the model.
     *
     * @param string $baseModelName The base model class name (without namespace path).
     * @return int Exit code (EXIT_SUCCESS or EXIT_ERROR).
     */
    public function createMigrationFile(string $baseModelName): int
    {
        $migrationHandler = new MigrationGeneratorHandler();
        return $migrationHandler->createMigrationFile($baseModelName);
    }

    /**
     * Ensures the specified directory exists, creating it if necessary.
     *
     * @param string $directory Absolute path to the directory.
     * @return bool True on success or if directory already exists, false on failure.
     */
    public function ensureDirectoryExists(string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }
        try {
            if (!mkdir($directory, 0755, true)) {
                CLI::error("Error: Could not create directory: {$directory}");
                return false;
            }
            CLI::write("Directory created: " . str_replace(APPPATH, 'app/', $directory), 'dark_gray');
            return true;
        } catch (Exception $e) {
            CLI::error("Error creating directory: {$directory}. Reason: " . $e->getMessage());
            return false;
        }
    }
}