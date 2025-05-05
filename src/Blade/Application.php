<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Closure;
use Illuminate\Container\Container;
use Illuminate\View\Factory;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;

class Application extends Container
{
    protected array $terminatingCallbacks = [];

    public function terminating(Closure $callback)
    {
        $this->terminatingCallbacks[] = $callback;

        return $this;
    }

    public function terminate()
    {
        foreach ($this->terminatingCallbacks as $terminatingCallback) {
            $terminatingCallback();
        }
    }

    public function getNamespace()
    {
        return '';
    }

    public function runningInConsole()
    {
        return false;
    }

    public function basePath($path = '')
    {
        return $path;
    }
}
