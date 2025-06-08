<?php

use Rcalicdan\Ci4Larabridge\Authentication\RememberTokenHandler;

/**
 * Get the authentication instance
 */
if (!function_exists('auth')) {
    function auth()
    {
        return Rcalicdan\Ci4Larabridge\Facades\Auth::getInstance();
    }
}

/**
 * Get remember token handler
 */
if (!function_exists('rememberToken')) {
    function rememberToken(): RememberTokenHandler
    {
        return auth()->getRememberTokenHandler();
    }
}

/**
 * Check if remember me is enabled
 */
if (!function_exists('isRememberMeEnabled')) {
    function isRememberMeEnabled(): bool
    {
        return rememberToken()->isEnabled();
    }
}

/**
 * Manually set remember token for user
 */
if (!function_exists('setRememberToken')) {
    function setRememberToken($user): void
    {
        rememberToken()->setRememberToken($user);
    }
}

/**
 * Clear remember token
 */
if (!function_exists('clearRememberToken')) {
    function clearRememberToken(): void
    {
        rememberToken()->clearCookie();
    }
}

/**
 * Get email handler
 */
if (!function_exists('authEmail')) {
    function authEmail()
    {
        return auth()->getEmailHandler();
    }
}
