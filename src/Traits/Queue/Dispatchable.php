<?php

namespace Rcalicdan\Ci4Larabridge\Traits\Queue;

use Rcalicdan\Ci4Larabridge\Queue\PendingDispatch;

trait Dispatchable
{
    /**
     * Dispatch the job with the given arguments.
     */
    public static function dispatch(...$arguments): PendingDispatch
    {
        return new PendingDispatch(new static(...$arguments));
    }

    /**
     * Dispatch the job with the given arguments if the given truth test passes.
     */
    public static function dispatchIf($boolean, ...$arguments): ?PendingDispatch
    {
        return $boolean ? static::dispatch(...$arguments) : null;
    }

    /**
     * Dispatch the job with the given arguments unless the given truth test passes.
     */
    public static function dispatchUnless($boolean, ...$arguments): ?PendingDispatch
    {
        return !$boolean ? static::dispatch(...$arguments) : null;
    }

    /**
     * Dispatch a command to its appropriate handler in the current process.
     */
    public static function dispatchSync(...$arguments)
    {
        return service('bus')->dispatchSync(new static(...$arguments));
    }

    /**
     * Dispatch a command to its appropriate handler after the current process.
     */
    public static function dispatchAfterResponse(...$arguments): PendingDispatch
    {
        return (new PendingDispatch(new static(...$arguments)))->afterResponse();
    }
}