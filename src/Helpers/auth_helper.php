<?php

/**
 * Get the authentication instance
 *
 * @return \Rcalicdan\Ci4Larabridge\Authentication\Authentication
 */
if (!function_exists('auth')) {
    function auth()
    {
        return \Rcalicdan\Ci4Larabridge\Facades\Auth::getInstance();
    }
}
