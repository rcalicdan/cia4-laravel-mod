<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Authentication Configuration
 */
class LarabridgeAuthentication extends BaseConfig
{
    /**
     * User model class to use for authentication
     */
    public string $userModel = \App\Models\User::class;

    /**
     * Default redirect after login
     */
    public string $loginRedirect = '/dashboard';

    /**
     * Default redirect after logout
     */
    public string $logoutRedirect = '/';

    /**
     * Login page URL
     */
    public string $loginUrl = '/login';

    /**
     * Password reset settings
     */
    public array $passwordReset = [
        'tokenExpiry' => 3600, // 1 hour in seconds
        'throttle' => 60, // seconds between reset requests
        'maxAttempts' => 5, // max attempts per hour
    ];

    /**
     * Email verification settings
     */
    public array $emailVerification = [
        'required' => true,
        'tokenExpiry' => 86400, // 24 hours in seconds
        'resendThrottle' => 60, // seconds between resend requests
    ];

    /**
     * Remember me settings
     */
    public array $rememberMe = [
        'enabled' => true,
        'tokenExpiry' => 2592000, // 30 days in seconds
        'cookieName' => 'remember_token',
        'cookieSecure' => true,
        'cookieHttpOnly' => true,
    ];

    /**
     * Session settings
     */
    public array $session = [
        'regenerateOnLogin' => true,
        'regenerateOnLogout' => true,
    ];

    /**
     * Security settings
     */
    public array $security = [
        'hashAlgorithm' => PASSWORD_ARGON2ID,
        'requireEmailVerification' => true,
        'loginAttempts' => [
            'maxAttempts' => 5,
            'lockoutTime' => 900, // 15 minutes
        ],
    ];
}
