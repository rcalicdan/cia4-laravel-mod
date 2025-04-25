<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

use CodeIgniter\CLI\CLI;

class ConfigHandler extends SetupHandler
{
    /**
     * Publish all required configuration files
     */
    public function publishConfig(): void
    {
        $this->publishConfigEloquent();
        $this->publishConfigPagination();
        $this->publishConfigServices();
        $this->publishConfigBlade();
    }

    /**
     * Copy and publish the Eloquent configuration
     */
    private function publishConfigEloquent(): void
    {
        $file = 'Config/Eloquent.php';
        $replaces = [
            'namespace Rcalicdan\Ci4Larabridge\Config' => 'namespace Config',
            'use CodeIgniter\Config\BaseConfig;' => 'use CodeIgniter\Config\BaseConfig;
use Rcalicdan\Ci4Larabridge\Config\Eloquent as BaseEloquent;',
            'class Eloquent extends BaseConfig' => 'class Eloquent extends BaseConfig',
        ];

        $this->copyAndReplace($file, $replaces);
    }

    private function publishConfigBlade(): void
    {
        $file = 'Config/Blade.php';
        $replaces = [
            'namespace Rcalicdan\Ci4Larabridge\Config' => 'namespace Config',
        ];

        $this->copyAndReplace($file, $replaces);
    }

    /**
     * Copy and publish the Pagination configuration
     */
    private function publishConfigPagination(): void
    {
        $file = 'Config/Pagination.php';
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
        $appServicesPath = $this->distPath.'Config/Services.php';

        if (file_exists($appServicesPath)) {
            // Add methods to existing Services class
            $this->addServiceMethods($appServicesPath);
        } else {
            // Copy the entire Services class
            $file = 'Config/Services.php';
            $replaces = [
                'namespace Rcalicdan\Ci4Larabridge\Config' => 'namespace Config',
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
            $this->write(CLI::color('  Skipped: ', 'yellow').'Services class already has eloquent() method.');
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
                $this->write(CLI::color('  Updated: ', 'green').clean_path($servicesPath));
            } else {
                $this->error('  Error updating Services class.');
            }
        }
    }
}
