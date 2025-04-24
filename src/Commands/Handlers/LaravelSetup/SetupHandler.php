<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Commands\Utils\ContentReplacer;

abstract class SetupHandler
{
    /**
     * The path to `Rcalicdan\Ci4Larabridge\` src directory.
     *
     * @var string
     */
    protected $sourcePath;

    /**
     * The path to the application directory
     * 
     * @var string
     */
    protected $distPath;

    /**
     * Content replacer for file operations
     * 
     * @var ContentReplacer
     */
    protected $replacer;

    /**
     * Constructor
     */
    public function __construct(string $sourcePath, string $distPath)
    {
        $this->sourcePath = $sourcePath;
        $this->distPath = $distPath;
        $this->replacer = new ContentReplacer();
    }

    /**
     * Copy a file from source to destination with optional replacements
     * 
     * @param string $file     Relative file path like 'Config/Auth.php'.
     * @param array  $replaces [search => replace]
     */
    protected function copyAndReplace(string $file, array $replaces = []): void
    {
        $path = "{$this->sourcePath}/{$file}";

        if (!file_exists($path)) {
            $this->error("  Source file not found: " . clean_path($path));
            return;
        }

        $content = file_get_contents($path);

        if (!empty($replaces)) {
            $content = $this->replacer->replace($content, $replaces);
        }

        $this->writeFile($file, $content);
    }

    /**
     * Copy a file from source to destination without modifications
     * 
     * @param string $file Relative file path
     */
    protected function copyFile(string $file): void
    {
        $this->copyAndReplace($file);
    }

    /**
     * Write a file, handling overwrite confirmation
     * 
     * @param string $file    Relative file path
     * @param string $content File content
     */
    protected function writeFile(string $file, string $content): void
    {
        $path = $this->distPath . $file;
        $cleanPath = clean_path($path);

        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (file_exists($path)) {
            $overwrite = (bool) CLI::getOption('f');

            if (
                !$overwrite
                && $this->prompt("  File '{$cleanPath}' already exists in destination. Overwrite?", ['n', 'y']) === 'n'
            ) {
                $this->error("  Skipped {$cleanPath}. If you wish to overwrite, please use the '-f' option or reply 'y' to the prompt.");
                return;
            }
        }

        if (write_file($path, $content)) {
            $this->write(CLI::color('  Created: ', 'green') . $cleanPath);
        } else {
            $this->error("  Error creating {$cleanPath}.");
        }
    }

    /**
     * Display an error message
     * 
     * @param string $message Error message
     */
    protected function error(string $message): void
    {
        CLI::write($message, 'red');
    }

    /**
     * Display a message
     * 
     * @param string $message Message to display
     */
    protected function write(string $message): void
    {
        CLI::write($message);
    }

    /**
     * Prompt for user input
     * 
     * @param string $message Prompt message
     * @param array|null $options Optional response options
     * @param string|null $validation Validation rules
     * @return string User response
     */
    protected function prompt(string $message, ?array $options = null, ?string $validation = null): string
    {
        return CLI::prompt($message, $options, $validation);
    }
}