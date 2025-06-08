<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;

/**
 * Class SystemHandler
 *
 * Handles the setup of system configurations for Laravel integration.
 */
class SystemHandler extends SetupHandler
{
    /**
     * Sets up the Events configuration by initializing Eloquent and authorization services.
     *
     * @return void
     */
    public function setupEvents(): void
    {
        [$path, $cleanPath] = $this->getFilePath('Config/Events.php');

        if (!$this->checkFileExists($path, 'Events file not found')) {
            return;
        }

        $content = file_get_contents($path);

        if ($this->checkContentExists($content, "service('eloquent')", 'Eloquent already initialized in events')) {
            return;
        }

        if (!$this->shouldProceed($cleanPath, 'initialize Eloquent and auth services')) {
            return;
        }

        $newContent = $this->updateEventsContent($content);
        $this->writeFileChanges($path, $newContent, $cleanPath, 'Events');
    }

    /**
     * Sets up the Filters configuration by adding authentication and guest filter aliases.
     *
     * @return void
     */
    public function setupFilters(): void
    {
        [$path, $cleanPath] = $this->getFilePath('Config/Filters.php');

        if (!$this->checkFileExists($path, 'Filters file not found')) {
            return;
        }

        $content = file_get_contents($path);

        if ($this->checkContentExists($content, 'AuthFilter::class', 'Auth filters already added')) {
            return;
        }

        if (!$this->shouldProceed($cleanPath, 'add auth filter aliases')) {
            return;
        }

        $newContent = $this->updateFiltersContent($content);
        $this->writeFileChanges($path, $newContent, $cleanPath, 'Filters');
    }

    /**
     * Constructs the file path and returns both the path and clean path.
     *
     * @param string $file The relative file path.
     * @return array An array containing the full path and clean path.
     */
    private function getFilePath(string $file): array
    {
        $path = $this->distPath . $file;
        return [$path, clean_path($path)];
    }

    /**
     * Checks if the specified file exists and outputs an error message if not.
     *
     * @param string $path The file path to check.
     * @param string $errorMessage The error message to display if the file does not exist.
     * @return bool Returns true if the file exists, false otherwise.
     */
    private function checkFileExists(string $path, string $errorMessage): bool
    {
        if (!file_exists($path)) {
            $this->error("  {$errorMessage}. Make sure you have a {$path} file.");
            return false;
        }
        return true;
    }

    /**
     * Checks if the specified content contains the given identifier and outputs a success message if found.
     *
     * @param string $content The content to search within.
     * @param string $identifier The identifier to search for.
     * @param string $successMessage The success message to display if the identifier is found.
     * @return bool Returns true if the identifier is found, false otherwise.
     */
    private function checkContentExists(string $content, string $identifier, string $successMessage): bool
    {
        if (strpos($content, $identifier) !== false) {
            $this->write(CLI::color("  {$successMessage}.", 'green'));
            return true;
        }
        return false;
    }

    /**
     * Prompts the user for confirmation to proceed with the specified action.
     *
     * @param string $cleanPath The clean path of the file being modified.
     * @param string $action The action description for the prompt.
     * @return bool Returns true if the user confirms, false otherwise.
     */
    private function shouldProceed(string $cleanPath, string $action): bool
    {
        if ($this->skipConfirmations) {
            return true;
        }

        $response = $this->prompt("  Ready to update '{$cleanPath}' to {$action}. Continue?", ['y', 'n']);
        if ($response === 'n') {
            $this->error("  Skipped updating {$cleanPath}.");
            return false;
        }
        return true;
    }

    /**
     * Updates the Events content by adding Eloquent and authorization service initialization.
     *
     * @param string $content The original content of the Events file.
     * @return string The updated content with added service initialization.
     */
    private function updateEventsContent(string $content): string
    {
        $pattern = '/(Events::on\(\'pre_system\',)/';
        $replacement = <<<'EOD'
Events::on('pre_system', static function (): void {
    // Load the Eloquent configuration
    service('eloquent');
    // Load the authentication configuration
    service('authorization');
});

$1
EOD;
        return preg_replace($pattern, $replacement, $content);
    }

    /**
     * Updates the Filters content by adding authentication and guest filter aliases.
     *
     * @param string $content The original content of the Filters file.
     * @return string The updated content with added filter aliases.
     */
    private function updateFiltersContent(string $content): string
    {
        $pattern = '/(public\s+array\s+\$aliases\s*=\s*\[)([^\]]*?)(\];)/s';
        $replacement = <<<'EOD'
$1$2
        'auth'     => \Rcalicdan\Ci4Larabridge\Filters\AuthFilter::class,
        'guest'    => \Rcalicdan\Ci4Larabridge\Filters\GuestFilter::class,
        'throttle' => \Rcalicdan\Ci4Larabridge\Filters\ThrottleFilter::class,
        'email_verified' => \Rcalicdan\Ci4Larabridge\Filters\EmailVerificationFilter::class,
$3
EOD;
        return preg_replace($pattern, $replacement, $content);
    }

    /**
     * Writes the updated content to the specified file and outputs a success or error message.
     *
     * @param string $path The file path to write to.
     * @param string $newContent The new content to write.
     * @param string $cleanPath The clean path of the file being modified.
     * @param string $type The type of file being updated (e.g., 'Events', 'Filters').
     * @return void
     */
    private function writeFileChanges(string $path, string $newContent, string $cleanPath, string $type): void
    {
        if (write_file($path, $newContent)) {
            $this->write(CLI::color('  Updated: ', 'green') . $cleanPath);
        } else {
            $this->error("  Error updating {$type} file.");
        }
    }
}
