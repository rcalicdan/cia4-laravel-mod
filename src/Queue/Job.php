<?php

namespace Rcalicdan\Ci4Larabridge\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\SerializableClosure\SerializableClosure;
use Rcalicdan\Ci4Larabridge\Queue\PendingDispatch;
use Rcalicdan\Ci4Larabridge\Traits\Queue\Dispatchable;

abstract class Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Execute the job.
     */
    abstract public function handle(): void;

    /**
     * Handle a job failure.
     * Log the failure
     */
    public function failed(\Throwable $exception): void
    {
        log_message('error', "Job failed: " . get_class($this) . " - " . $exception->getMessage());
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Create a new job instance with closure support
     */
    public static function dispatchClosure(\Closure $closure, ...$arguments): PendingDispatch
    {
        return static::dispatch(new SerializableClosure($closure), ...$arguments);
    }
}
