<?php

use Rcalicdan\Ci4Larabridge\Routing\RedirectBackManager;

if (!function_exists('back_url')) {
    /**
     * Get URL to return to previous page
     */
    function back_url(string $default = ''): string
    {
        $manager = new RedirectBackManager();
        return $manager->getRedirectBackUrl($default);
    }
}

if (!function_exists('redirect_intended')) {
    /**
     * Get URL to redirect to intended destination
     */
    function redirect_intended(string $default = ''): string
    {
        return back_url($default);
    }
}

if (!function_exists('link_with_back')) {
    /**
     * Create URL with back navigation token
     */
    function link_with_back(string $uri): string
    {
        $manager = new RedirectBackManager();
        return $manager->urlWithBack($uri);
    }
}

if (!function_exists('route_with_back')) {
    /**
     * Create route URL with back navigation token
     */
    function route_with_back(string $routeName, ...$params): string
    {
        $manager = new RedirectBackManager();
        return $manager->routeWithBack($routeName, ...$params);
    }
}