<?php

namespace Reymart221111\Cia4LaravelMod\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Autoload as AutoloadConfig;

class Setup extends BaseCommand
{
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'laravel:setup';

    /**
     * The Command's short description
     *
     * @var string
     */
    protected $description = 'Initial setup for CodeIgniter 4 Laravel Module.';

    /**
     * The Command's usage
     *
     * @var string
     */
    protected $usage = 'laravel:setup';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '-f' => 'Force overwrite ALL existing files in destination.',
    ];

    /**
     * The path to `Reymart221111\Cia4LaravelMod\` src directory.
     *
     * @var string
     */
    protected $sourcePath;

    /**
     * The path to the application directory
     * 
     * @var string
     */
    protected $distPath = APPPATH;

    /**
     * Content replacer for file operations
     * 
     * @var ContentReplacer
     */
    private $replacer;

    /**
     * Execute the setup process
     */
    public function run(array $params): void
    {
        $this->replacer = new ContentReplacer();
        $this->sourcePath = __DIR__ . '/../';

        $this->publishConfig();
        $this->setupHelpers();
        $this->setupRoutes();
        $this->setupEloquent();
    }

    /**
     * Publish all required configuration files
     */
    private function publishConfig(): void
    {
        $this->publishConfigEloquent();
        $this->publishConfigPagination();
        $this->publishConfigServices();
    }

    /**
     * Copy and publish the Eloquent configuration
     */
    private function publishConfigEloquent(): void
    {
        $file     = 'Config/Eloquent.php';
        $replaces = [
            'namespace Reymart221111\Cia4LaravelMod\Config' => 'namespace Config',
            'use CodeIgniter\Config\BaseConfig;' => 'use CodeIgniter\Config\BaseConfig;
use Reymart221111\Cia4LaravelMod\Config\Eloquent as BaseEloquent;',
            'class Eloquent extends BaseConfig' => 'class Eloquent extends BaseEloquent',
        ];

        $this->copyAndReplace($file, $replaces);
    }

    /**
     * Copy and publish the Pagination configuration
     */
    private function publishConfigPagination(): void
    {
        $file     = 'Config/Pagination.php';
        $replaces = [
            'namespace Reymart221111\Cia4LaravelMod\Config' => 'namespace Config',
            'use CodeIgniter\Config\BaseConfig;' => 'use CodeIgniter\Config\BaseConfig;
use Reymart221111\Cia4LaravelMod\Config\Pagination as BasePagination;',
            'class Pagination extends BaseConfig' => 'class Pagination extends BasePagination',
        ];

        $this->copyAndReplace($file, $replaces);
    }

    /**
     * Copy and publish the Services configuration
     */
    private function publishConfigServices(): void
    {
        // First check if App/Config/Services.php exists
        $appServicesPath = $this->distPath . 'Config/Services.php';
        
        if (file_exists($appServicesPath)) {
            // Add methods to existing Services class
            $this->addServiceMethods($appServicesPath);
        } else {
            // Copy the entire Services class
            $file     = 'Config/Services.php';
            $replaces = [
                'namespace Reymart221111\Cia4LaravelMod\Config' => 'namespace Config',
            ];

            $this->copyAndReplace($file, $replaces);
        }
    }

    /**
     * Add service methods to existing Services class
     */
    private function addServiceMethods(string $servicesPath): void
    {
        $content = file_get_contents($servicesPath);
        
        // Check if class already has our methods
        if (strpos($content, 'eloquent(') !== false) {
            $this->write(CLI::color('  Skipped: ', 'yellow') . 'Services class already has eloquent() method.');
        } else {
            // Add eloquent method
            $pattern = '/}(\s*)$/'; // Find the closing brace of the class
            $eloquentMethod = <<<'EOD'

    /**
     * Return the Eloquent service instance
     *
     * @param bool $getShared
     * @return \Reymart221111\Cia4LaravelMod\Config\Eloquent
     */
    public static function eloquent($getShared = true): \Reymart221111\Cia4LaravelMod\Config\Eloquent
    {
        if ($getShared) {
            return static::getSharedInstance('eloquent');
        }
        return new \Reymart221111\Cia4LaravelMod\Config\Eloquent();
    }

    /**
     * Return the Laravel Validator service instance
     *
     * @param bool $getShared
     * @return \Reymart221111Validation\LaravelValidator
     */
    public static function laravelValidator($getShared = true): \Reymart221111Validation\LaravelValidator
    {
        if ($getShared) {
            return static::getSharedInstance('laravelValidator');
        }

        return new \Reymart221111Validation\LaravelValidator();
    }

    /**
     * Return the Blade service instance
     *
     * @param bool $getShared
     * @return \Reymart221111Blade\BladeService
     */
    public static function blade(bool $getShared = true): \Reymart221111Blade\BladeService
    {
        if ($getShared) {
            return static::getSharedInstance('blade');
        }

        return new \Reymart221111Blade\BladeService();
    }
}
EOD;
            $newContent = preg_replace($pattern, $eloquentMethod, $content);
            
            if ($newContent !== $content && write_file($servicesPath, $newContent)) {
                $this->write(CLI::color('  Updated: ', 'green') . clean_path($servicesPath));
            } else {
                $this->error("  Error updating Services class.");
            }
        }
    }

    /**
     * Setup helper autoloading in Config/Autoload.php
     */
    private function setupHelpers(): void
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
     * Setup routes for Laravel integration
     */
    private function setupRoutes(): void
    {
        $file = 'Config/Routes.php';
        $path = $this->distPath . $file;
        
        if (!file_exists($path)) {
            $this->error("  Routes file not found. Make sure you have a Config/Routes.php file.");
            return;
        }

        $content = file_get_contents($path);
        $setupCode = 'service(\'eloquent\'); // Initialize Laravel Eloquent';
        
        // Check if the code is already there
        if (strpos($content, $setupCode) !== false) {
            $this->write(CLI::color('  Routes Setup: ', 'green') . 'Eloquent already initialized in routes.');
            return;
        }

        // Find a good place to add our code - right after the routes initialization
        $pattern = '/(.*\$routes\s*=\s*Services::routes\(\);.*\n)/';
        $replace = '$1' . "\n" . $setupCode . "\n";
        
        $newContent = preg_replace($pattern, $replace, $content);
        
        if ($newContent !== $content && write_file($path, $newContent)) {
            $this->write(CLI::color('  Updated: ', 'green') . clean_path($path));
        } else {
            $this->error("  Error updating Routes file.");
        }
    }

    /**
     * Setup Eloquent and run migrations if requested
     */
    private function setupEloquent(): void
    {
        // Create a models directory if it doesn't exist
        $modelsDir = $this->distPath . 'Models';
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0777, true);
            $this->write(CLI::color('  Created: ', 'green') . clean_path($modelsDir));
        }
        
        // Copy the User model as an example
        $this->copyFile('Models/User.php');
        
        // Ask if we should run migrations
        if ($this->prompt('  Run Laravel migrations now?', ['y', 'n']) === 'y') {
            $this->runMigrations();
        }
    }

    /**
     * Run Laravel migrations
     */
    private function runMigrations(): void
    {
        $this->write(CLI::color('  Running migrations...', 'green'));
        
        // Initialize Eloquent first
        service('eloquent');
        
        // Find migration files
        $migrationDir = $this->sourcePath . 'Database/Laravel-Migrations';
        if (!is_dir($migrationDir)) {
            $this->error("  Migration directory not found.");
            return;
        }
        
        // Process each migration file
        $files = scandir($migrationDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $this->write("  Migrating: " . $file);
            $migration = require $migrationDir . '/' . $file;
            $migration->up();
        }
        
        $this->write(CLI::color('  Migrations completed!', 'green'));
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

/**
 * Content replacer utility class for file modifications
 */
class ContentReplacer
{
    /**
     * Replace content using search and replace arrays
     * 
     * @param string $content Original content
     * @param array $replaces [search => replace] pairs
     * @return string Modified content
     */
    public function replace(string $content, array $replaces): string
    {
        return strtr($content, $replaces);
    }

    /**
     * Add content if it doesn't already exist
     * 
     * @param string $content Original content
     * @param string $text Text to add
     * @param string $pattern Regexp search pattern
     * @param string $replace Regexp replacement including text to add
     * @return bool|string true: already updated, false: regexp error, string: modified content
     */
    public function add(string $content, string $text, string $pattern, string $replace)
    {
        $return = preg_match('/' . preg_quote($text, '/') . '/u', $content);
        if ($return === 1) {
            // It has already been updated.
            return true;
        }
        if ($return === false) {
            // Regexp error.
            return false;
        }
        return preg_replace($pattern, $replace, $content);
    }
}