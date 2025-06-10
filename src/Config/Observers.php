<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Observer Configuration
 *
 * This file contains the configuration for Eloquent model observers.
 * Observers can be registered in multiple ways:
 * 1. Manual registration in the boot() method
 * 2. Using PHP 8 attributes on models
 * 3. Auto-discovery based on naming convention
 */
class Observers extends BaseConfig
{
    /**
     * Auto-discover observers based on naming convention
     * If true, will automatically look for observers in App\Observers
     * that follow the pattern: ModelNameObserver
     */
    public bool $autoDiscover = true;

    /**
     * Observer namespace for auto-discovery
     */
    public string $observerNamespace = 'App\\Observers';

    /**
     * Observer suffix for auto-discovery
     */
    public string $observerSuffix = 'Observer';

    /**
     * Enable attribute-based observer registration
     * If true, will scan models for #[ObservedBy] attributes
     */
    public bool $useAttributes = true;

    /**
     * Manual observer registration
     * Override this method to manually register observers
     */
    public function boot(): void
    {
      //
    }
}