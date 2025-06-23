<?php

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to the queue.
     */
    function dispatch($job): \Rcalicdan\Ci4Larabridge\Queue\PendingDispatch
    {
        if ($job instanceof \Closure) {
            $job = new \Rcalicdan\Ci4Larabridge\Queue\CallQueuedClosure($job);
        }

        if (method_exists($job, 'dispatch')) {
            return $job::dispatch();
        }

        return new \Rcalicdan\Ci4Larabridge\Queue\PendingDispatch($job);
    }
}

if (!function_exists('dispatch_sync')) {
    /**
     * Dispatch a job synchronously.
     */
    function dispatch_sync($job)
    {
        return service('bus')->dispatchSync($job);
    }
}

if (!function_exists('dispatch_now')) {
    /**
     * Dispatch a job immediately.
     */
    function dispatch_now($job)
    {
        return service('bus')->dispatchNow($job);
    }
}

if (!function_exists('queue_push')) {
    /**
     * Push a job onto the queue.
     */
    function queue_push($job, $data = '', $queue = null): mixed
    {
        return service('queue')->push($job, $data, $queue);
    }
}

if (!function_exists('queue_later')) {
    /**
     * Push a job onto the queue after a delay.
     */
    function queue_later($delay, $job, $data = '', $queue = null): mixed
    {
        return service('queue')->later($delay, $job, $data, $queue);
    }
}
