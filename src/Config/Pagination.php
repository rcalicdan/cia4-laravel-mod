<?php

namespace Rcalicdan\Ci4Larabridge\Config;

use CodeIgniter\Config\BaseConfig;

class Pagination extends BaseConfig
{
    /**
     * Default theme for pagination
     */
    public $theme = 'bootstrap';

    /**
     * Number of links to display on each side of current page
     */
    public $window = 5;
}
