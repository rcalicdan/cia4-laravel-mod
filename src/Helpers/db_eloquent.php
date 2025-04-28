<?php 

if (!function_exists('db_eloquent')) {
    /**
     * Get the Eloquent database instance with lazy loading
     *
     * @param string|null $method Method to call on the EloquentDatabase instance
     * @param array $args Arguments to pass to the method
     * @return mixed
     */
    function db_eloquent($method = null, $args = [])
    {
        $eloquent = service('eloquent');
        
        if ($method !== null) {
            return call_user_func_array([$eloquent, $method], $args);
        }
        
        return $eloquent;
    }
}