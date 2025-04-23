<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Autoload as AutoloadConfig;
use Rcalicdan\Ci4Larabridge\Commands\Utils\ContentReplacer;

class LaravelSetup extends BaseCommand
{
    protected $group = 'Laravel Setup';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'laravel-setup';

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
    protected $usage = 'laravel-setup';

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
        $this->copyMigrationFiles();
        $this->setupEvents();
        $this->setupFilters();
        $this->copyUserModel();
        $this->copyAuthServiceProvider();
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
            'namespace Rcalicdan\Ci4Larabridge\Config' => 'namespace Config',
            'use CodeIgniter\Config\BaseConfig;' => 'use CodeIgniter\Config\BaseConfig;
use Rcalicdan\Ci4Larabridge\Config\Eloquent as BaseEloquent;',
            'class Eloquent extends BaseConfig' => 'class Eloquent extends BaseConfig',
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
            'namespace Rcalicdan\Ci4Larabridge\Config' => 'namespace Config',
            'use CodeIgniter\Config\BaseConfig;' => 'use CodeIgniter\Config\BaseConfig;
use Rcalicdan\Ci4Larabridge\Config\Pagination as BasePagination;',
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
                'namespace Rcalicdan\Ci4Larabridge\Config' => 'namespace Config',
            ];

            $this->copyAndReplace($file, $replaces);
        }
    }

    /**
     * Add service methods to existing Services class
     */
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
            $serviceMethods = <<<'EOD'

    /**
     * Return the Eloquent service instance
     *
     * @param bool $getShared
     * @return Eloquent
     */
    public static function eloquent($getShared = true): Eloquent
    {
        if ($getShared) {
            return static::getSharedInstance('eloquent');
        }
        return new Eloquent();
    }

    /**
     * Returns an instance of the Gate class.
     * 
     * @param bool $getShared Whether to return a shared instance.
     * @return \Rcalicdan\Ci4Larabridge\Authentication\Gate
     */
    public static function authorization($getShared = true): \Rcalicdan\Ci4Larabridge\Authentication\Gate
    {
        if ($getShared) {
            return static::getSharedInstance('authorization');
        }

        $provider = new \App\Libraries\Authentication\AuthServiceProvider;
        $provider->register();

        return gate();
    }

    /**
     * Return the Laravel Validator service instance
     *
     * @param bool $getShared
     * @return \Rcalicdan\Ci4Larabridge\Validation\LaravelValidator;
     */
    public static function laravelValidator($getShared = true): \Rcalicdan\Ci4Larabridge\Validation\LaravelValidator
    {
        if ($getShared) {
            return static::getSharedInstance('laravelValidator');
        }

        return new \Rcalicdan\Ci4Larabridge\Validation\LaravelValidator();
    }

    /**
     * Return the Blade service instance
     *
     * @param bool $getShared
     * @return \Rcalicdan\Ci4Larabridge\Blade\BladeService
     */
    public static function blade(bool $getShared = true): \Rcalicdan\Ci4Larabridge\Blade\BladeService
    {
        if ($getShared) {
            return static::getSharedInstance('blade');
        }

        return new \Rcalicdan\Ci4Larabridge\Blade\BladeService();
    }
}
EOD;
            $newContent = preg_replace($pattern, $serviceMethods, $content);

            if ($newContent !== $content && write_file($servicesPath, $newContent)) {
                $this->write(CLI::color('  Updated: ', 'green') . clean_path($servicesPath));
            } else {
                $this->error("  Error updating Services class.");
            }
        }
    }

    /**
     * Copy AuthServiceProvider to App/Libraries/Authentication directory
     */
    private function copyAuthServiceProvider(): void
    {
        // Create directory if it doesn't exist
        $authDir = $this->distPath . 'Libraries/Authorization';
        if (!is_dir($authDir)) {
            mkdir($authDir, 0777, true);
            $this->write(CLI::color('  Created: ', 'green') . clean_path($authDir));
        }

        $destPath = $authDir . '/AuthServiceProvider.php';
        $cleanDestPath = clean_path($destPath);

        // Prepare content with updated namespace
        $content = <<<'EOD'
<?php

namespace App\Libraries\Authentication;

use Rcalicdan\Ci4Larabridge\Gate;
use App\Models\User;


/**
 * AuthServiceProvider
 * 
 * This class is responsible for registering authorization policies
 * throughout the application. It maps model classes to their respective
 * policy classes and registers them with the Gate.
 */
class AuthServiceProvider
{
    /**
     * The policy mappings for the application
     * 
     * This array maps model classes to their corresponding policy classes.
     * Example: 'App\Models\User::class => App\Policies\UserPolicy::class'
     * 
     * @var array
     */
    protected $policies = [
        // Register your policies here
    ];

    /**
     * Register all authentication and authorization services
     * 
     * This method initializes all authorization-related services,
     * including registering policies with the Gate.
     * 
     * Example usage of defining a gate:
     * ```php
     * gate()->define('view-dashboard', function($user) {
     *     return $user->isAdmin() || $user->hasRole('editor');
     * });
     * ```
     * @return void
     */
    public function register(): void
    {
        // Define your gate here
        $this->registerPolicies(); //Do not delete this line, this is required for policies to work
    }

    /**
     * Register defined policies with the Gate
     * 
     * This method reads the policy mappings from the $policies property
     * and registers each model-policy pair with the Gate instance.
     * 
     * @return void
     */
    public function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            gate()->policy($model, $policy);
        }
    }
}
EOD;

        // Check if destination file already exists
        if (file_exists($destPath)) {
            $overwrite = (bool) CLI::getOption('f');

            if (
                !$overwrite
                && $this->prompt("  File '{$cleanDestPath}' already exists. Overwrite?", ['n', 'y']) === 'n'
            ) {
                $this->error("  Skipped {$cleanDestPath}. If you wish to overwrite, please use the '-f' option or reply 'y' to the prompt.");
                return;
            }
        }

        // Write the file
        if (write_file($destPath, $content)) {
            $this->write(CLI::color('  Created: ', 'green') . $cleanDestPath);
        } else {
            $this->error("  Error creating AuthServiceProvider at {$cleanDestPath}.");
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
     * Copy Laravel migration files to App/Database/Laravel-Migrations
     */
    private function copyMigrationFiles(): void
    {
        // Create the Database/Laravel-Migrations directory if it doesn't exist
        $migrationsDir = $this->distPath . 'Database/Laravel-Migrations';
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0777, true);
            $this->write(CLI::color('  Created: ', 'green') . clean_path($migrationsDir));
        }

        // Find migration files
        $sourceMigrationDir = $this->sourcePath . 'Database/Laravel-Migrations';
        if (!is_dir($sourceMigrationDir)) {
            $this->error("  Source migration directory not found.");
            return;
        }

        // Copy each migration file
        $files = scandir($sourceMigrationDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $sourceFile = $sourceMigrationDir . '/' . $file;
            $destFile = $migrationsDir . '/' . $file;

            if (copy($sourceFile, $destFile)) {
                $this->write(CLI::color('  Copied: ', 'green') . clean_path($destFile));
            } else {
                $this->error("  Error copying migration file: " . $file);
            }
        }

        $this->write(CLI::color('  Migration files copied successfully!', 'green'));
    }

    /**
     * Update Events.php to add Eloquent and authorization services initialization
     */
    private function setupEvents(): void
    {
        $file = 'Config/Events.php';
        $path = $this->distPath . $file;
        $cleanPath = clean_path($path);

        if (!file_exists($path)) {
            $this->error("  Events file not found. Make sure you have a Config/Events.php file.");
            return;
        }

        $content = file_get_contents($path);

        // Check if the code is already there
        if (strpos($content, "service('eloquent')") !== false) {
            $this->write(CLI::color('  Events Setup: ', 'green') . 'Eloquent already initialized in events.');
            return;
        }

        // Find the position to add the new event (before the existing Events::on)
        $pattern = '/(Events::on\(\'pre_system\',)/';
        $eloquentCode = <<<'EOD'
Events::on('pre_system', static function (): void {
    // Load the Eloquent configuration
    service('eloquent');
    // Load the authentication configuration
    service('authorization');
});

$1
EOD;

        $newContent = preg_replace($pattern, $eloquentCode, $content);

        if ($newContent !== $content && write_file($path, $newContent)) {
            $this->write(CLI::color('  Updated: ', 'green') . $cleanPath);
        } else {
            $this->error("  Error updating Events file.");
        }
    }

    /**
     * Update Filters.php to add auth and guest filter aliases
     */
    private function setupFilters(): void
    {
        $file = 'Config/Filters.php';
        $path = $this->distPath . $file;
        $cleanPath = clean_path($path);

        if (!file_exists($path)) {
            $this->error("  Filters file not found. Make sure you have a Config/Filters.php file.");
            return;
        }

        $content = file_get_contents($path);

        // Check if the code is already there
        if (strpos($content, "\\Rcalicdan\\Ci4Larabridge\\Filter\\AuthFilter::class") !== false) {
            $this->write(CLI::color('  Filters Setup: ', 'green') . 'Auth filters already added.');
            return;
        }

        // Find the aliases array
        $pattern = '/(public\s+array\s+\$aliases\s*=\s*\[)([^\]]*?)(\];)/s';
        $filterAliases = <<<'EOD'
$1$2
        'auth'     => \Rcalicdan\Ci4Larabridge\Filter\AuthFilter::class,
        'guest'    => \Rcalicdan\Ci4Larabridge\Filter\GuestFilter::class,
$3
EOD;

        $newContent = preg_replace($pattern, $filterAliases, $content);

        if ($newContent !== $content && write_file($path, $newContent)) {
            $this->write(CLI::color('  Updated: ', 'green') . $cleanPath);
        } else {
            $this->error("  Error updating Filters file.");
        }
    }

    /**
     * Copy User model to App/Models directory
     */
    private function copyUserModel(): void
    {
        // Create models directory if it doesn't exist
        $modelsDir = $this->distPath . 'Models';
        if (!is_dir($modelsDir)) {
            mkdir($modelsDir, 0777, true);
            $this->write(CLI::color('  Created: ', 'green') . clean_path($modelsDir));
        }

        $sourcePath = $this->sourcePath . 'Models/User.php';
        $destPath = $this->distPath . 'Models/User.php';
        $cleanDestPath = clean_path($destPath);

        // Check if source file exists
        if (!file_exists($sourcePath)) {
            $this->error("  Source User model not found: " . clean_path($sourcePath));
            return;
        }

        // Read content and update namespace
        $content = file_get_contents($sourcePath);
        $content = str_replace(
            'namespace Rcalicdan\Ci4Larabridge\Models',
            'namespace App\Models',
            $content
        );

        // Check if destination file already exists
        if (file_exists($destPath)) {
            $overwrite = (bool) CLI::getOption('f');

            if (
                !$overwrite
                && $this->prompt("  File '{$cleanDestPath}' already exists. Overwrite?", ['n', 'y']) === 'n'
            ) {
                $this->error("  Skipped {$cleanDestPath}. If you wish to overwrite, please use the '-f' option or reply 'y' to the prompt.");
                return;
            }
        }

        // Write the file
        if (write_file($destPath, $content)) {
            $this->write(CLI::color('  Created: ', 'green') . $cleanDestPath);
        } else {
            $this->error("  Error creating User model at {$cleanDestPath}.");
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
