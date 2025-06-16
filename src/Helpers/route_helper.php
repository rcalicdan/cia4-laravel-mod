<?php

if (function_exists('route')) {
    function route(string $route, ...$params): string
    {
        return route_to($route, $params);
    }
}
