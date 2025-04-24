<?php

namespace Rcalicdan\Ci4Larabridge\Config;

use CodeIgniter\Config\BaseConfig;

class Blade extends BaseConfig
{
    /**
     * Path to the views directory
     * 
     * @var string
     */
    public $viewsPath = APPPATH . 'Views';

    /**
     * Path to the cache directory for compiled templates
     * 
     * @var string
     */
    public $cachePath = WRITEPATH . 'cache/blade';

    /**
     * Component namespace for Blade components
     * 
     * @var string
     */
    public $componentNamespace = 'components';

    /**
     * Path to the components directory
     * 
     * @var string
     */
    public $componentPath = APPPATH . 'Views/components';

    /**
     * Disable compilation checks in production for performance
     * When true, templates will only be recompiled when they change
     * 
     * @var bool
     */
    public $disableCompilationChecksInProduction = true;

    /**
     * Additional view paths that should be registered with Blade
     * Key is the namespace, value is the path
     * 
     * @var array
     */
    public $viewNamespaces = [];
}