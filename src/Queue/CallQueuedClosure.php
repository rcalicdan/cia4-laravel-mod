<?php

namespace Rcalicdan\Ci4Larabridge\Queue;

use Laravel\SerializableClosure\SerializableClosure;

class CallQueuedClosure extends Job
{
    protected SerializableClosure $closure;

    public function __construct(\Closure $closure)
    {
        $this->closure = new SerializableClosure($closure);
    }

    public function handle(): void
    {
        $closure = $this->closure->getClosure();
        $closure();
    }
}
