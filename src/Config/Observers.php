<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Observer Configuration
 *
 * This file contains the mapping of Eloquent models to their observers.
 * Observers will be automatically registered when the application boots.
 */
class Observers extends BaseConfig
{
    /**
     * Model to Observer mappings
     * 
     * Format: 'ModelClass' => 'ObserverClass'
     * Example: \App\Models\User::class => \App\Observers\UserObserver::class,
     * 
     * @var array<string, string>
     */
    public array $observers = [
        //
    ];

    /**
     * Auto-discover observers based on naming convention
     * If true, will automatically look for observers in App\Observers
     * that follow the pattern: ModelNameObserver
     * 
     * @var bool
     */
    public bool $autoDiscover = true;

    /**
     * Observer namespace for auto-discovery
     * 
     * @var string
     */
    public string $observerNamespace = 'App\\Observers';

    /**
     * Observer suffix for auto-discovery
     * 
     * @var string
     */
    public string $observerSuffix = 'Observer';
}