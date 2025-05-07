<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Command to publish pagination templates to application directory
 */
class PublishPagination extends BaseCommand
{
    /**
     * @var string The group the command is lumped under
     */
    protected $group = 'Pagination';

    /**
     * @var string The Command's name
     */
    protected $name = 'publish:pagination';

    /**
     * @var string The Command's description
     */
    protected $description = 'Publishes eloquent pagination templates to the application directory';

    /**
     * @var string The Command's usage
     */
    protected $usage = 'publish:pagination';

    /**
     * @var array The Command's arguments
     */
    protected $arguments = [];

    /**
     * @var array The Command's options
     */
    protected $options = [];

    /**
     * Executes the command to publish pagination templates
     *
     * @param  array  $params  Command parameters
     * @return void
     */
    public function run(array $params)
    {
        $sourcePath = __DIR__.'/../Views/pagination';
        $destinationPath = APPPATH.'Views/pagination';

        if (! is_dir($sourcePath)) {
            CLI::error("Source directory not found: {$sourcePath}");

            return;
        }

        if (! is_dir($destinationPath)) {
            if (! mkdir($destinationPath, 0755, true)) {
                CLI::error("Failed to create destination directory: {$destinationPath}");

                return;
            }
        }

        $this->copyFiles($sourcePath, $destinationPath);

        CLI::write('Pagination templates published successfully!', 'green');
    }

    /**
     * Copies files recursively from source to destination
     *
     * @param  string  $source  Source directory path
     * @param  string  $destination  Destination directory path
     * @return void
     */
    protected function copyFiles($source, $destination)
    {
        $directory = opendir($source);

        while (($file = readdir($directory)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourcePath = $source.'/'.$file;
            $destPath = $destination.'/'.$file;

            if (is_file($sourcePath)) {
                if (copy($sourcePath, $destPath)) {
                    CLI::write("Copied: {$file}", 'yellow');
                } else {
                    CLI::error("Failed to copy: {$file}");
                }
            } elseif (is_dir($sourcePath)) {
                if (! is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
                $this->copyFiles($sourcePath, $destPath);
            }
        }

        closedir($directory);
    }
}
