<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;
use Config\Autoload as AutoloadConfig;

class HelpersHandler extends SetupHandler
{
    /**
     * Setup helper autoloading in Config/Autoload.php
     */
    public function setupHelpers(): void
    {
        $file = 'Config/Autoload.php';
        $path = $this->distPath . $file;
        $cleanPath = clean_path($path);

        $config = new AutoloadConfig();
        $helpers = $config->helpers;
        $newHelpers = array_unique(array_merge($helpers, ['auth', 'authorization', 'blade', 'http', 'url_back']));

        $content = file_get_contents($path);
        $output = $this->updateAutoloadHelpers($content, $newHelpers);

        // Check if the content is updated
        if ($output === $content) {
            $this->write(CLI::color('  Autoload Setup: ', 'green') . 'Helper autoloading already configured.');
            return;
        }

        if (write_file($path, $output)) {
            $this->write(CLI::color('  Updated: ', 'green') . $cleanPath);

            // Copy all helper files to App/Helpers
            $this->copyHelperFiles();
        } else {
            $this->error("  Error updating file '{$cleanPath}'.");
        }
    }

    /**
     * Copy helper files to the application's Helpers directory
     */
    private function copyHelperFiles(): void
    {
        $helperFiles = [
            'Helpers/auth_helper.php',
            'Helpers/authorization_helper.php',
            'Helpers/blade_helper.php',
            'Helpers/http_helper.php',
            'Helpers/url_back_helper.php',
        ];

        foreach ($helperFiles as $file) {
            $this->copyFile($file);
        }
    }

    /**
     * @param string $content    The content of Config\Autoload.
     * @param array  $newHelpers The list of helpers.
     */
    private function updateAutoloadHelpers(string $content, array $newHelpers): string
    {
        $pattern = '/^    public \$helpers = \[.*?\];/msu';
        $replace = '    public $helpers = [\'' . implode("', '", $newHelpers) . '\'];';

        return preg_replace($pattern, $replace, $content);
    }
}