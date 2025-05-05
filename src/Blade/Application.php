<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Container\Container;

class Application extends Container
{
    /**
     * The callbacks to be run after all bootstrappers are registered.
     *
     * @var array
     */
    protected $terminatingCallbacks = [];

    /**
     * Get the application namespace.
     *
     * @return string
     */
    public function getNamespace()
    {
        return '';
    }

    /**
     * Determine if the application is running in the console.
     *
     * @return bool
     */
    public function runningInConsole()
    {
        return false;
    }

    /**
     * Get the path to the application "app" directory.
     *
     * @param  string  $path
     * @return string
     */
    public function basePath($path = '')
    {
        return $path ? APPPATH.$path : APPPATH;
    }

    /**
     * Register a terminating callback with the application.
     *
     * @param  callable|string  $callback
     * @return $this
     */
    public function terminating($callback)
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Terminate the application.
     *
     * @return void
     */
    public function terminate()
    {
        foreach ($this->terminatingCallbacks as $terminating) {
            $this->call($terminating);
        }
    }

    /**
     * Get the application's base path.
     *
     * @return string
     */
    public function path($path = '')
    {
        return $this->basePath('app').($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the path to the storage directory.
     *
     * @return string
     */
    public function storagePath($path = '')
    {
        return $this->basePath('writable').($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the path to the resources directory.
     *
     * @param  string  $path
     * @return string
     */
    public function resourcePath($path = '')
    {
        return $this->basePath('resources').($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Get the path to the views directory.
     *
     * @param  string  $path
     * @return string
     */
    public function viewPath($path = '')
    {
        return $this->resourcePath('views').($path ? DIRECTORY_SEPARATOR.$path : $path);
    }

    /**
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string  $provider
     * @param  bool  $force
     * @return \Illuminate\Support\ServiceProvider
     */
    public function register($provider, $force = false)
    {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }

        $provider->register();

        return $provider;
    }
}
