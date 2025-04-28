<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuration for Larabridge package
 */
class Larabridge extends BaseConfig
{
    /**
     * Enable query logging
     * Only effective when ENVIRONMENT is development
     * 
     * @var bool
     */
    public $enableQueryLog = false;
    
    /**
     * Enable Laravel Facades
     * Set to true if your application uses Laravel Facades
     * 
     * @var bool
     */
    public $enableFacades = true;
    
    /**
     * Enable Eloquent debug collector
     * 
     * @var bool
     */
    public $enableEloquentCollector = false;
    
    /**
     * Connection pooling settings
     * 
     * @var bool
     */
    public $usePersistentConnections = true;
    
    /**
     * Prepared statements mode
     * Using emulated prepares may be faster in some scenarios
     * 
     * @var bool
     */
    public $useEmulatedPrepares = false;
}