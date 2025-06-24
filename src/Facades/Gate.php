<?php

namespace Rcalicdan\Ci4Larabridge\Facades;

use Rcalicdan\Ci4Larabridge\Authentication\Gate as AuthenticationGate;

/**
 * Facade for authorization gate functionality
 *
 * Provides static access to authorization capabilities including defining abilities,
 * associating policies, and checking permissions.
 */
class Gate
{
    /**
     * Define a new ability
     *
     * @param  string  $ability  The ability identifier
     * @param  callable  $callback  Authorization callback that returns boolean
     * @return void
     */
    public static function define($ability, $callback): AuthenticationGate
    {
        return AuthenticationGate::getInstance()->define($ability, $callback);
    }

    /**
     * Associate a policy with a model class
     *
     * @param  string  $model  Model class name
     * @param  string  $policy  Policy class name
     * @return void
     */
    public static function policy($model, $policy): AuthenticationGate
    {
        return AuthenticationGate::getInstance()->policy($model, $policy);
    }

    /**
     * Determine if ability is allowed for current user
     *
     * @param  string  $ability  Ability identifier
     * @param  array  $arguments  Additional parameters
     */
    public static function allows($ability, $arguments = []): bool
    {
        return AuthenticationGate::getInstance()->allows($ability, $arguments);
    }

    /**
     * Determine if ability is denied for current user
     *
     * @param  string  $ability  Ability identifier
     * @param  array  $arguments  Additional parameters
     */
    public static function denies($ability, $arguments = []): bool
    {
        return AuthenticationGate::getInstance()->denies($ability, $arguments);
    }

    /**
     * Check if ability is granted
     *
     * @param  string  $ability  Ability identifier
     * @param  array  $arguments  Additional parameters
     */
    public static function check($ability, $arguments = []): bool
    {
        return AuthenticationGate::getInstance()->check($ability, $arguments);
    }

    /**
     * Get registered policy for a class
     *
     * @param  object|string  $class  Class instance or name
     * @return mixed Associated policy instance or null
     */
    public static function getPolicyFor($class)
    {
        return AuthenticationGate::getInstance()->getPolicyFor($class);
    }
}
