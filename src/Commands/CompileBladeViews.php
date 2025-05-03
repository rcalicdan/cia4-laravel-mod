<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Illuminate\Filesystem\Filesystem;

/**
 * Command to pre-compile all Blade templates in a CodeIgniter 4 application.
 *
 * This command locates and compiles all Blade (.blade.php) templates found in the
 * application's Views directory, providing feedback on the success or failure of
 * each compilation. It enhances performance by pre-compiling templates for use
 * with the Jenssegers/Blade package.
 */
class CompileBladeViews extends BaseCommand
{
    /**
     * The group this command belongs to.
     *
     * @var string
     */
    protected $group = 'Blade';

    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'blade:compile';

    /**
     * A brief description of the command's purpose.
     *
     * @var string
     */
    protected $description = 'Pre-compiles all Blade templates';

    /**
     * Executes the Blade template compilation process.
     *
     * Scans the Views directory for Blade templates, compiles each one using the
     * Blade compiler, and reports the results. Tracks and displays the number of
     * successful and failed compilations.
     *
     * @param  array  $params  Command parameters (not used in this command).
     * @return void
     */
    public function run(array $params)
    {
        CLI::write('Starting Blade view compilation...', 'yellow');

        $bladeService = service('blade');
        $blade = $bladeService->getBlade();
        $compiler = $blade->compiler();
        $viewsPath = APPPATH.'Views';
        $filesystem = new Filesystem;

        $files = $this->findBladeTemplates($viewsPath);

        $successful = 0;
        $failed = 0;

        foreach ($files as $file) {
            $relativePath = str_replace($viewsPath.'/', '', $file);
            $viewName = str_replace('.blade.php', '', $relativePath);
            $viewName = str_replace('/', '.', $viewName);

            try {
                $compiler->compile($file);
                CLI::write("✓ {$viewName}", 'green');
                $successful++;
            } catch (\Exception $e) {
                CLI::error("✗ {$viewName}: {$e->getMessage()}");
                $failed++;
            }
        }

        CLI::write("Compilation complete: {$successful} succeeded, {$failed} failed", 'yellow');
    }

    /**
     * Finds all Blade templates in the specified directory.
     *
     * Recursively scans the provided directory and returns an array of file paths
     * for all files ending with '.blade.php'.
     *
     * @param  string  $directory  The directory to search for Blade templates.
     * @return array List of file paths for Blade templates.
     */
    protected function findBladeTemplates($directory)
    {
        $files = [];
        $filesystem = new Filesystem;

        foreach ($filesystem->allFiles($directory) as $file) {
            if (str_ends_with($file->getPathname(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
