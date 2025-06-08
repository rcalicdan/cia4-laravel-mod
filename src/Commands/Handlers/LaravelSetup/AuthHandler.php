<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;

class AuthHandler extends SetupHandler
{
    private const MODELS_DIR = 'Models';
    private const USER_MODEL_FILE = 'User.php';

    private const SOURCE_NAMESPACE = 'namespace Rcalicdan\Ci4Larabridge\Models;';
    private const TARGET_NAMESPACE = 'namespace App\Models;

use Rcalicdan\Ci4Larabridge\Models\Model;';

    /**
     * Copy User model to App/Models directory
     */
    public function copyUserModel(): void
    {
        if (! $this->ensureModelsDirectoryExists()) {
            return;
        }

        $sourcePath = $this->getSourceModelPath();
        $destinationPath = $this->getDestinationModelPath();

        if (! $this->validateSourceFile($sourcePath)) {
            return;
        }

        if (! $this->handleExistingFile($destinationPath)) {
            return;
        }

        $this->createUserModel($sourcePath, $destinationPath);
    }

    /**
     * Ensure the Models directory exists
     */
    private function ensureModelsDirectoryExists(): bool
    {
        $modelsDir = $this->getModelsDirectory();

        if (is_dir($modelsDir)) {
            return true;
        }

        if (! $this->createDirectory($modelsDir)) {
            $this->error('Failed to create models directory: '.clean_path($modelsDir));

            return false;
        }

        $this->write(CLI::color('  Created: ', 'green').clean_path($modelsDir));

        return true;
    }

    /**
     * Create directory with proper permissions
     */
    private function createDirectory(string $path): bool
    {
        return mkdir($path, 0777, true);
    }

    /**
     * Get the models directory path
     */
    private function getModelsDirectory(): string
    {
        return $this->distPath.self::MODELS_DIR;
    }

    /**
     * Get the source User model path
     */
    private function getSourceModelPath(): string
    {
        return $this->sourcePath.self::MODELS_DIR.'/'.self::USER_MODEL_FILE;
    }

    /**
     * Get the destination User model path
     */
    private function getDestinationModelPath(): string
    {
        return $this->distPath.self::MODELS_DIR.'/'.self::USER_MODEL_FILE;
    }

    /**
     * Validate that the source file exists
     */
    private function validateSourceFile(string $sourcePath): bool
    {
        if (file_exists($sourcePath)) {
            return true;
        }

        $this->error('  Source User model not found: '.clean_path($sourcePath));

        return false;
    }

    /**
     * Handle existing destination file
     */
    private function handleExistingFile(string $destinationPath): bool
    {
        if (! file_exists($destinationPath)) {
            return true;
        }

        if ($this->shouldOverwriteFile()) {
            return true;
        }

        $cleanPath = clean_path($destinationPath);
        if ($this->promptForOverwrite($cleanPath)) {
            return true;
        }

        $this->showSkipMessage($cleanPath);

        return false;
    }

    /**
     * Check if force overwrite option is enabled
     */
    private function shouldOverwriteFile(): bool
    {
        return (bool) CLI::getOption('f');
    }

    /**
     * Prompt user for overwrite confirmation
     */
    private function promptForOverwrite(string $filePath): bool
    {
        $response = $this->prompt(
            "  File '{$filePath}' already exists. Overwrite?",
            ['n', 'y']
        );

        return $response === 'y';
    }

    /**
     * Show skip message to user
     */
    private function showSkipMessage(string $filePath): void
    {
        $this->error(
            "  Skipped {$filePath}. If you wish to overwrite, please use the '-f' option or reply 'y' to the prompt."
        );
    }

    /**
     * Create the User model file
     */
    private function createUserModel(string $sourcePath, string $destinationPath): void
    {
        $content = $this->prepareModelContent($sourcePath);

        if (! $content) {
            $this->error('  Failed to read source file: '.clean_path($sourcePath));

            return;
        }

        if ($this->writeModelFile($destinationPath, $content)) {
            $this->write(CLI::color('  Created: ', 'green').clean_path($destinationPath));
        } else {
            $this->error('  Error creating User model at '.clean_path($destinationPath).'.');
        }
    }

    /**
     * Prepare the model content with updated namespace
     */
    private function prepareModelContent(string $sourcePath): string|false
    {
        $content = file_get_contents($sourcePath);

        if ($content === false) {
            return false;
        }

        return $this->updateNamespace($content);
    }

    /**
     * Update the namespace in the model content
     */
    private function updateNamespace(string $content): string
    {
        return str_replace(
            self::SOURCE_NAMESPACE,
            self::TARGET_NAMESPACE,
            $content
        );
    }

    /**
     * Write the model file to destination
     */
    private function writeModelFile(string $destinationPath, string $content): bool
    {
        return write_file($destinationPath, $content);
    }
}
