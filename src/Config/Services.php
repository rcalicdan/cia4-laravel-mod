<?php

namespace Reymart221111\Cia4LaravelMod\Config;

use Reymart221111\Cia4LaravelMod\Config\Eloquent;
use Reymart221111\Cia4LaravelMod\Authentication\Gate;
use Reymart221111\Cia4LaravelMod\Providers\AuthServiceProvider;
use Reymart221111\Cia4LaravelMod\Validation\LaravelValidator;
use CodeIgniter\Config\BaseService;
use Reymart221111\Cia4LaravelMod\Blade\BladeService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /**
     * Returns an instance of the Eloquent class.
     * 
     * @param bool $getShared Whether to return a shared instance.
     * @return Eloquent
     */
    public static function eloquent($getShared = true): Eloquent
    {
        if ($getShared) {
            return static::getSharedInstance('eloquent');
        }
        return new Eloquent();
    }

    /**
     * Returns an instance of the LaravelValidator class.
     * 
     * @param bool $getShared Whether to return a shared instance.
     * @return LaravelValidator
     */
    public static function laravelValidator($getShared = true): LaravelValidator
    {
        if ($getShared) {
            return static::getSharedInstance('laravelValidator');
        }

        return new LaravelValidator();
    }

    /**
     * Returns an instance of the Gate class.
     * 
     * @param bool $getShared Whether to return a shared instance.
     * @return Gate
     */
    public static function authorization($getShared = true): Gate
    {
        if ($getShared) {
            return static::getSharedInstance('authorization');
        }

        $provider = new AuthServiceProvider;
        $provider->register();

        return gate();
    }

    /**
     * Return the BladeService instance
     *
     * @param bool $getShared
     * @return BladeService
     */
    public static function blade(bool $getShared = true): BladeService
    {
        if ($getShared) {
            return static::getSharedInstance('blade');
        }

        return new BladeService();
    }
}
