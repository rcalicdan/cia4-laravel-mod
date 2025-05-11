<?php

/**
 * Session Redirect Helper
 * 
 * Provides functionality for managing redirect tokens and back URLs
 */

use Config\Services;
use PhpParser\Node\Expr\Throw_;

if (! function_exists('ensure_rb_token')) {
    /**
     * Generates or retrieves a redirect token
     * 
     * @param string $baseKey Session storage key prefix
     * @return string Sanitized token string
     */
    function ensure_rb_token(string $baseKey = 'redirect_back_url'): string
    {
        $request = Services::request();
        $token   = $request->getGet('rb_token');

        if (! $token) {
            $token = bin2hex(random_bytes(8));
            session()->set("{$baseKey}_{$token}", (string) current_url(true));
        }

        return preg_replace('/[^A-Za-z0-9_-]/', '', $token);
    }
}

if (! function_exists('get_redirect_back_url')) {
    /**
     * Retrieve the stored “back” URL for the current token, or fall back to:
     *  1) A named route (if $default is a route name),
     *  2) A full URL (if $default is a URL),
     *  3) The HTTP Referer header,
     *  4) site_url('/')
     *
     * @param  string  $default   Either a route name (e.g. 'songs.artists.index')
     *                            or a full URL (e.g. 'https://example.com/foo')
     *                            or empty to skip to referer.
     * @param  string  $baseKey   Session key prefix
     * @return string
     */
    function get_redirect_back_url(string $default   = '', string $baseKey   = 'redirect_back_url'): string
    {
        $request = Services::request();
        $token   = $request->getGet('rb_token');
        $key     = $token
            ? "{$baseKey}_" . preg_replace('/[^A-Za-z0-9_-]/', '', $token)
            : $baseKey;

        if (session()->has($key)) {
            $url = session($key);
            session()->remove($key);
            return $url;
        }

        if (str_starts_with($default, 'http://') || str_starts_with($default, 'https://')) {
            return $default;
        }

        if ($default !== '') {
            try {
                return route_to($default);
            } catch (\Throwable $e) {
                throw new Exception('Invalid Route Name');
            }
        }

        $referer = $request->getServer('HTTP_REFERER');
        if ($referer) {
            $host = parse_url(site_url(), PHP_URL_HOST);
            $refererHost = parse_url($referer, PHP_URL_HOST);

            if ($refererHost && $refererHost === $host) {
                return filter_var($referer, FILTER_SANITIZE_URL);
            }
        }

        return site_url('/');
    }
}


if (! function_exists('url_with_back')) {
    /**
     * Generates URL with redirect token parameter
     * 
     * @param string $uri Target URI
     * @return string URL with token parameter
     */
    function url_with_back(string $uri): string
    {
        $token = ensure_rb_token();
        $url   = preg_match('#^https?://#', $uri) ? $uri : site_url($uri);

        $sep = parse_url($url, PHP_URL_QUERY) ? '&' : '?';
        return "{$url}{$sep}rb_token={$token}";
    }
}

if (! function_exists('route_with_back')) {
    /**
     * Generates route URL with redirect token parameter
     * 
     * @param string $routeName Name of the route
     * @param mixed ...$params Route parameters
     * @return string URL with token parameter
     */
    function route_with_back(string $routeName, ...$params): string
    {
        $token = ensure_rb_token();
        $url   = route_to($routeName, ...$params);

        $sep = parse_url($url, PHP_URL_QUERY) ? '&' : '?';
        return "{$url}{$sep}rb_token={$token}";
    }
}
