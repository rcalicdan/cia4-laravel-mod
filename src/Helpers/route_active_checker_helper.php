<?php

/**
 * Check if the current route matches the given route name or pattern
 *
 * @param string|array $patterns Route name/pattern or array of route names/patterns to check
 * @return bool
 */
function is_route_active($patterns)
{
    $currentRoute = service('router')->getMatchedRoute();
    
    if (!$currentRoute) {
        return false;
    }
    
    $currentRouteName = $currentRoute[0];
    
    if (is_array($patterns)) {
        foreach ($patterns as $pattern) {
            if (check_route_pattern($currentRouteName, $pattern)) {
                return true;
            }
        }
        return false;
    }
    
    return check_route_pattern($currentRouteName, $patterns);
}

/**
 * Check if a route name matches a pattern
 * 
 * @param string $routeName Current route name
 * @param string $pattern Pattern to match against (supports * wildcard)
 * @return bool
 */
function check_route_pattern($routeName, $pattern)
{
    // Exact match
    if ($routeName === $pattern) {
        return true;
    }
    
    // Wildcard match
    if (strpos($pattern, '*') !== false) {
        $pattern = str_replace('*', '.*', $pattern);
        return preg_match('/^' . $pattern . '$/', $routeName) === 1;
    }
    
    return false;
}

/**
 * Returns 'active' if the current route matches the given route pattern
 *
 * @param string|array $patterns Route name/pattern or array of route names/patterns to check
 * @return string
 */
function active_class($patterns)
{
    return is_route_active($patterns) ? 'active' : '';
}