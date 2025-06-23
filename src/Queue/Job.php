<?php

namespace Rcalicdan\Ci4Larabridge\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravel\SerializableClosure\SerializableClosure;

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
     */
    public function failed(\Throwable $exception): void
    {
        // Log the failure
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
    public static function dispatchClosure(\Closure $closure, ...$arguments): \Illuminate\Foundation\Bus\PendingDispatch
    {
        return static::dispatch(new SerializableClosure($closure), ...$arguments);
    }
}
