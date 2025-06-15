<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuration class for pagination settings in the Ci4Larabridge module.
 *
 * Defines the default views used for rendering pagination links in a CodeIgniter 4
 * application integrated with Laravel's pagination system.
 */
class Pagination extends BaseConfig
{
    /**
     * The default view for standard pagination rendering.
     *
     * @var string
     */
    public $defaultView = 'pagination::bootstrap';

    /**
     * The default view for simple pagination rendering.
     *
     * @var string
     */
    public $defaultSimpleView = 'pagination::simple-default';
}
