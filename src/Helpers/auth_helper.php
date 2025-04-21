<?php

/**
 * Get the authentication instance
 *
 * @return \Reymart221111\Libraries\Authentication\Authentication
 */
if (!function_exists('auth')) {
    function auth()
    {
        return \Reymart221111\Facades\Auth::getInstance();
    }
}
