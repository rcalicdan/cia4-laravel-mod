<?php

if (! function_exists('remember')) {
    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param  string  $key  The cache key
     * @param  int  $ttl  Time to live in seconds
     * @param  callable  $callback  Function to execute if cache misses
     * @return mixed
     */
    function remember(string $key, int $ttl, callable $callback)
    {
        $value = cache($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();

        cache()->save($key, $value, $ttl);

        return $value;
    }
}
