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
     * Email Verification View Path
     */
    public string $emailVerificationViewPath = 'emails/email-verification';

    /**
     * Password Reset View Path
     */
    public string $passwordResetViewPath = 'emails/password-reset';

    /**
     * Default redirect after login
     */
    public string $filterLoginAuthRedirect = '/dashboard';

    /**
     * Default redirect after logout
     */
    public string $logoutRedirect = '/';

    /**
     * Login page URL
     */
    public string $filterLoginAuthUrl = '/login';

    /**
     * Email Verfification page URL
     */
    public string $emailVerficationUrl = '/email/verify';

    /**
     * Password reset settings
     */
    public array $passwordReset = [
        'tokenExpiry' => 3600, // 1 hour in seconds
    ];

    /**
     * Email verification settings
     */
    public array $emailVerification = [
        'required' => false,
        'tokenExpiry' => 86400, // 24 hours in seconds
    ];

    /**
     * Remember me settings
     */
    public array $rememberMe = [
        'enabled' => false,
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
    public array $passwordHash = [
        'driver' => 'bcrypt',
        'round' => 10,
    ];

    /**
     * Email settings
     */
    public array $email = [
        'fromEmail' => null, // Will default to noreply@{domain}
        'fromName' => null,  // Will default to 'Your Application'
    ];
}
