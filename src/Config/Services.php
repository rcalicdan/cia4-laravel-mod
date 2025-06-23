<?php

namespace Rcalicdan\Ci4Larabridge\Config;

use CodeIgniter\Config\BaseService;
use Rcalicdan\Ci4Larabridge\Authentication\Gate;
use Rcalicdan\Ci4Larabridge\Blade\BladeService;
use Rcalicdan\Ci4Larabridge\Database\EloquentDatabase;
use Rcalicdan\Ci4Larabridge\Providers\AuthServiceProvider;
use Rcalicdan\Ci4Larabridge\Validation\LaravelValidator;

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
    protected static $eloquentInstance = null;

    /**
     * Returns an instance of the Eloquent class.
     *
     * @param  bool  $getShared  Whether to return a shared instance.
     */
    public static function eloquent($getShared = true): EloquentDatabase
    {
        if ($getShared && static::$eloquentInstance) {
            return static::$eloquentInstance;
        }

        static::$eloquentInstance = new EloquentDatabase;

        return static::$eloquentInstance;
    }

    /**
     * Returns an instance of the LaravelValidator class.
     *
     * @param  bool  $getShared  Whether to return a shared instance.
     */
    public static function laravelValidator($getShared = true): LaravelValidator
    {
        if ($getShared) {
            return static::getSharedInstance('laravelValidator');
        }

        return new LaravelValidator;
    }

    /**
     * Returns an instance of the Gate class.
     *
     * @param  bool  $getShared  Whether to return a shared instance.
     */
    public static function authorization($getShared = true): Gate
    {
        if ($getShared) {
            return static::getSharedInstance('authorization');
        }

        $provider = class_exists(\App\Libraries\Authorization\AuthServiceProvider::class)
            ? new \App\Libraries\Authorization\AuthServiceProvider
            : new AuthServiceProvider;

        $provider->register();

        return gate();
    }

    /**
     * Return the BladeService instance
     */
    public static function blade(bool $getShared = true): BladeService
    {
        if ($getShared) {
            return static::getSharedInstance('blade');
        }

        return new BladeService;
    }

    /**
     * Returns an instance of the Queue Manager
     */
    public static function queue($getShared = true): QueueManager
    {
        if ($getShared) {
            return static::getSharedInstance('queue');
        }

        return static::queueService()->getQueueManager();
    }

    /**
     * Returns the Queue Service instance
     */
    public static function queueService($getShared = true): QueueService
    {
        if ($getShared) {
            return static::getSharedInstance('queueService');
        }

        return new QueueService();
    }

    /**
     * Returns an instance of the Bus Dispatcher
     */
    public static function bus($getShared = true): BusDispatcher
    {
        if ($getShared) {
            return static::getSharedInstance('bus');
        }

        return static::busService()->getBusDispatcher();
    }

    /**
     * Returns the Bus Service instance
     */
    public static function busService($getShared = true): BusService
    {
        if ($getShared) {
            return static::getSharedInstance('busService');
        }

        return new BusService();
    }
}
