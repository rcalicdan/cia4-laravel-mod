<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;

class AuthHandler extends SetupHandler
{
    /**
     * Copy AuthServiceProvider to App/Libraries/Authentication directory
     */
    public function copyAuthServiceProvider(): void
    {
        // Create directory if it doesn't exist
        $authDir = $this->distPath . 'Libraries/Authorization';
        if (! is_dir($authDir)) {
            mkdir($authDir, 0777, true);
            $this->write(CLI::color('  Created: ', 'green') . clean_path($authDir));
        }

        $destPath = $authDir . '/AuthServiceProvider.php';
        $cleanDestPath = clean_path($destPath);

        // Prepare content with updated namespace
        $content = <<<'EOD'
<?php

namespace App\Libraries\Authorization;

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
                ! $overwrite
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
     * Copy User model to App/Models directory
     */
    public function copyUserModel(): void
    {
        // Create models directory if it doesn't exist
        $modelsDir = $this->distPath . 'Models';
        if (! is_dir($modelsDir)) {
            mkdir($modelsDir, 0777, true);
            $this->write(CLI::color('  Created: ', 'green') . clean_path($modelsDir));
        }

        $sourcePath = $this->sourcePath . 'Models/User.php';
        $destPath = $this->distPath . 'Models/User.php';
        $cleanDestPath = clean_path($destPath);

        // Check if source file exists
        if (! file_exists($sourcePath)) {
            $this->error('  Source User model not found: ' . clean_path($sourcePath));

            return;
        }

        // Read content and update namespace
        $content = file_get_contents($sourcePath);
        $content = str_replace(
            'namespace Rcalicdan\Ci4Larabridge\Models;',
            'namespace App\Models;
            
use Rcalicdan\Ci4Larabridge\Models\Model;',
            $content
        );

        // Check if destination file already exists
        if (file_exists($destPath)) {
            $overwrite = (bool) CLI::getOption('f');

            if (
                ! $overwrite
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
}
