<?php

/**
 * Get the authentication instance
 *
 * @return \Reymart221111\Cia4LaravelMod\Authentication\Authentication
 */
if (!function_exists('auth')) {
    function auth()
    {
        return \Reymart221111\Cia4LaravelMod\Facades\Auth::getInstance();
    }
}
