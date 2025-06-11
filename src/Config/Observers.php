<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Observer Configuration
 *
 * This file contains the configuration for Eloquent model observers.
 * Observers can be registered in two ways:
 * 1. Manual registration in the boot() method
 * 2. Using PHP 8 attributes on models with #[ObservedBy]
 */
class Observers extends BaseConfig
{
    /**
     * Enable attribute-based observer registration
     * If true, will scan models for #[ObservedBy] attributes
     */
    public bool $useAttributes = false;

    /**
     * Manual observer registration
     * Register your observers here following Laravel's convention:
     *
     * Example:
     * User::observe(UserObserver::class);
     * Post::observe(PostObserver::class);
     */
    public function boot(): void
    {
        //
    }
}
