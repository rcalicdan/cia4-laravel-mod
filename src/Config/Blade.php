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
     * When false, templates will not be recompiled when they change and cache will not expired
     * 
     * @var bool
     */
    public $checksCompilationInProduction = false;
}