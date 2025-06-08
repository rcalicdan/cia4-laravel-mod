<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Authorization Configuration
 *
 * This configuration class defines all authorization policies and gates
 * for the application in a centralized location.
 */
class Authorization extends BaseConfig
{
    /**
     * The policy mappings for the application
     *
     * Maps model classes to their corresponding policy classes.
     * Example: 'App\Models\User::class => App\Policies\UserPolicy::class'
     *
     * @var array<string, string>
     */
    public array $policies = [
        //
    ];

    /**
     * Gate definitions
     *
     * Define custom gates/abilities here. Each gate should return a callable
     * that receives the user as the first parameter.
     *
     * Example usage of defining a gate:
     * ```php
     * gate()->define('view-dashboard', function($user) {
     *     return $user->isAdmin() || $user->hasRole('editor');
     * });
     *
     * @var array<string, callable>
     */
    public function gates()
    {
        //
    }
}
