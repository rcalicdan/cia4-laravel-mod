<?php

namespace Rcalicdan\Ci4Larabridge\Config;

use CodeIgniter\Config\BaseConfig;
use Rcalicdan\Blade\Blade as BladeDirective;

/**
 * Configuration class for Blade templating in the Ci4Larabridge module.
 *
 * Defines settings for Blade view rendering, including paths for views, cache, and
 * components, as well as compilation behavior in production. Provides a method to
 * register custom Blade directives.
 */
class Blade extends BaseConfig
{
    /**
     * Path to the directory containing Blade view templates.
     *
     * @var string
     */
    public $viewsPath = APPPATH.'Views';

    /**
     * Path to the directory for storing compiled Blade template cache.
     *
     * @var string
     */
    public $cachePath = WRITEPATH.'cache/blade';

    /**
     * Namespace for Blade components.
     *
     * @var string
     */
    public $componentNamespace = 'components';

    /**
     * Path to the directory containing Blade component templates.
     *
     * @var string
     */
    public $componentPath = APPPATH.'Views/components';

    /**
     * Registers custom Blade directives.
     *
     * Allows developers to define custom directives for the Blade templating engine.
     *
     * @param  BladeDirective  $blade  The Blade instance to register directives with.
     */
    public function registerCustomDirectives(BladeDirective $blade): void
    {
        //
    }
}
