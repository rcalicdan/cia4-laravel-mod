<?php

if (!function_exists('route')) {
    function route(string $route, ...$params): string
    {
        return route_to($route, $params);
    }
}

if (! function_exists('get')) {
    function get($key, $default = null)
    {
        $request = service('request');

        return $request->getGet($key, $default);
    }
}

if (!function_exists('post')) {
    function post($key)
    {
        $request = service('request');

        return $request->getPost($key);
    }
}
