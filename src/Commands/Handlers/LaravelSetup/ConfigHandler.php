<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup;

class ConfigHandler extends SetupHandler
{
    /**
     * Publish all required configuration files
     */
    public function publishConfig(): void
    {
        $this->publishConfigEloquent();
        $this->publishConfigPagination();
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
            'use CodeIgniter\Config\BaseConfig;' => 'use CodeIgniter\Config\BaseConfig;',
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
            'class Pagination extends BaseConfig' => 'class Pagination extends BaseConfig',
        ];

        $this->copyAndReplace($file, $replaces);
    }
}
