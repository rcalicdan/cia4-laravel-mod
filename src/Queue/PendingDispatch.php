<?php

namespace Rcalicdan\Ci4Larabridge\Queue;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Fluent;

class PendingDispatch
{
    /**
     * The job.
     */
    protected $job;

    /**
     * The connection name.
     */
    protected $connection;

    /**
     * The queue name.
     */
    protected $queue;

    /**
     * The delay before the job should be processed.
     */
    protected $delay;

    /**
     * Indicates if this job should be dispatched after all database transactions have committed.
     */
    protected $afterCommit;

    /**
     * Indicates if this job should be dispatched after the response is sent.
     */
    protected $afterResponse = false;

    /**
     * The middleware the job should pass through.
     */
    protected $middleware = [];

    /**
     * The jobs that should run if this job is successful.
     */
    protected $chained = [];

    /**
     * Create a new pending job dispatch.
     */
    public function __construct($job)
    {
        $this->job = $job;
    }

    /**
     * Set the desired connection for the job.
     */
    public function onConnection($connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Set the desired queue for the job.
     */
    public function onQueue($queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set the desired delay for the job.
     */
    public function delay($delay): self
    {
        $this->delay = $delay;
        return $this;
    }

    /**
     * Set the jobs that should run if this job is successful.
     */
    public function chain(array $chain): self
    {
        $this->chained = $chain;
        return $this;
    }

    /**
     * Indicate that the job should be dispatched after all database transactions have committed.
     */
    public function afterCommit(): self
    {
        $this->afterCommit = true;
        return $this;
    }

    /**
     * Indicate that the job should be dispatched after the response is sent.
     */
    public function afterResponse(): self
    {
        $this->afterResponse = true;
        return $this;
    }

    /**
     * Set the middleware the job should pass through.
     */
    public function through(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Handle the object's destruction.
     */
    public function __destruct()
    {
        if ($this->afterResponse) {
            // Store for later dispatch after response
            $this->storeForAfterResponse();
            return;
        }

        $this->dispatchJob();
    }

    /**
     * Dispatch the job.
     */
    protected function dispatchJob(): void
    {
        if ($this->job instanceof ShouldQueue) {
            $this->dispatchToQueue();
        } else {
            $this->dispatchNow();
        }
    }

    /**
     * Dispatch the job to a queue.
     */
    protected function dispatchToQueue(): void
    {
        $queueManager = service('queue');
        
        $queue = $this->connection 
            ? $queueManager->connection($this->connection)
            : $queueManager;

        // Set job properties
        if ($this->queue && method_exists($this->job, 'onQueue')) {
            $this->job->onQueue($this->queue);
        }

        if ($this->connection && method_exists($this->job, 'onConnection')) {
            $this->job->onConnection($this->connection);
        }

        if ($this->chained && method_exists($this->job, 'chain')) {
            $this->job->chain($this->chained);
        }

        if ($this->middleware && method_exists($this->job, 'through')) {
            $this->job->through($this->middleware);
        }

        if ($this->afterCommit !== null && method_exists($this->job, 'afterCommit')) {
            $this->job->afterCommit = $this->afterCommit;
        }

        // Dispatch with or without delay
        if ($this->delay) {
            $queue->later($this->delay, $this->job, '', $this->queue);
        } else {
            $queue->push($this->job, '', $this->queue);
        }
    }

    /**
     * Dispatch the job synchronously.
     */
    protected function dispatchNow(): void
    {
        service('bus')->dispatchSync($this->job);
    }

    /**
     * Store the job for after response dispatch.
     */
    protected function storeForAfterResponse(): void
    {
        // You can implement this to store jobs that should run after response
        // For now, we'll just dispatch normally
        $this->dispatchJob();
    }
}