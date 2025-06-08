<?php

namespace Rcalicdan\Ci4Larabridge\Facades;

use Rcalicdan\Ci4Larabridge\Authentication\Authentication;
use Rcalicdan\Ci4Larabridge\Authentication\EmailHandler;
use Rcalicdan\Ci4Larabridge\Authentication\RememberTokenHandler;

/**
 * Facade for the Authentication library.
 *
 * This class provides a static interface to the Authentication library,
 * allowing easy access to authentication-related methods.
 */
class Auth
{
    /**
     * The singleton instance of the Authentication library.
     *
     * @var Authentication|null
     */
    protected static $instance;

    /**
     * Retrieves the singleton instance of the Authentication library.
     *
     * @return Authentication The Authentication instance.
     */
    public static function getInstance(): Authentication
    {
        if (!self::$instance) {
            self::$instance = new Authentication();
        }

        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Handles dynamic static method calls to the Authentication library.
     *
     * @param string $method The method name to call.
     * @param array $args The arguments to pass to the method.
     * @return mixed The result of the method call.
     */
    public static function __callStatic($method, $args)
    {
        return self::getInstance()->$method(...$args);
    }

    // Core Authentication Methods

    /**
     * Retrieves the currently authenticated user.
     *
     * @return \Illuminate\Database\Eloquent\Model|null The authenticated user, or null if no user is authenticated.
     */
    public static function user(): ?\Illuminate\Database\Eloquent\Model
    {
        return self::getInstance()->user();
    }

    /**
     * Checks if a user is currently authenticated.
     *
     * @return bool True if a user is authenticated, false otherwise.
     */
    public static function check(): bool
    {
        return self::getInstance()->check();
    }

    /**
     * Checks if a user is not currently authenticated.
     *
     * @return bool True if no user is authenticated, false otherwise.
     */
    public static function guest(): bool
    {
        return self::getInstance()->guest();
    }

    /**
     * Attempts to authenticate a user with the given credentials.
     *
     * @param array $credentials The user credentials to authenticate.
     * @param bool $remember Whether to remember the user.
     * @return bool True if authentication is successful, false otherwise.
     */
    public static function attempt(array $credentials, bool $remember = false): bool
    {
        return self::getInstance()->attempt($credentials, $remember);
    }

    /**
     * Logs in a user.
     *
     * @param \Illuminate\Database\Eloquent\Model $user The user to log in.
     * @param bool $remember Whether to remember the user.
     * @return bool True if the login is successful, false otherwise.
     */
    public static function login($user, bool $remember = false): bool
    {
        return self::getInstance()->login($user, $remember);
    }

    /**
     * Logs out the currently authenticated user.
     *
     * @return bool True if the logout is successful, false otherwise.
     */
    public static function logout(): bool
    {
        return self::getInstance()->logout();
    }

    // Password Reset Methods

    /**
     * Send a password reset link to the given email address.
     *
     * @param string $email The email address to send the reset link to.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public static function sendPasswordResetLink(string $email): bool
    {
        return self::getInstance()->sendPasswordResetLink($email);
    }

    /**
     * Reset the user's password using the given token.
     *
     * @param string $token The password reset token.
     * @param string $password The new password.
     * @return bool True if the password was reset successfully, false otherwise.
     */
    public static function resetPassword(string $token, string $password): bool
    {
        return self::getInstance()->resetPassword($token, $password);
    }

    // Email Verification Methods

    /**
     * Send an email verification link to the given user.
     *
     * @param \Illuminate\Database\Eloquent\Model $user The user to send the verification email to.
     * @return bool True if the email was sent successfully, false otherwise.
     */
    public static function sendEmailVerification($user): bool
    {
        return self::getInstance()->sendEmailVerification($user);
    }

    /**
     * Verify the user's email using the given token.
     *
     * @param string $token The email verification token.
     * @return bool True if the email was verified successfully, false otherwise.
     */
    public static function verifyEmail(string $token): bool
    {
        return self::getInstance()->verifyEmail($token);
    }

    // Handler Access Methods

    /**
     * Get the email handler instance.
     *
     * @return EmailHandler The email handler instance.
     */
    public static function emailHandler(): EmailHandler
    {
        return self::getInstance()->getEmailHandler();
    }

    /**
     * Get the remember token handler instance.
     *
     * @return RememberTokenHandler The remember token handler instance.
     */
    public static function rememberTokenHandler(): RememberTokenHandler
    {
        return self::getInstance()->getRememberTokenHandler();
    }

    // Convenience Methods

    /**
     * Get the authenticated user's ID.
     *
     * @return int|string|null The user's ID, or null if not authenticated.
     */
    public static function id()
    {
        $user = self::user();
        return $user?->id;
    }

    /**
     * Check if remember me functionality is enabled.
     *
     * @return bool True if remember me is enabled, false otherwise.
     */
    public static function isRememberMeEnabled(): bool
    {
        return self::rememberTokenHandler()->isEnabled();
    }

    /**
     * Manually set remember token for the given user.
     *
     * @param \Illuminate\Database\Eloquent\Model $user The user to set the remember token for.
     */
    public static function setRememberToken($user): void
    {
        self::rememberTokenHandler()->setRememberToken($user);
    }

    /**
     * Clear the remember token cookie.
     */
    public static function clearRememberToken(): void
    {
        self::rememberTokenHandler()->clearCookie();
    }

    // Alias Methods (for Laravel compatibility)

    /**
     * Alias for attempt() method.
     *
     * @param array $credentials The user credentials to authenticate.
     * @param bool $remember Whether to remember the user.
     * @return bool True if authentication is successful, false otherwise.
     */
    public static function validate(array $credentials, bool $remember = false): bool
    {
        return self::attempt($credentials, $remember);
    }

    /**
     * Alias for login() method.
     *
     * @param \Illuminate\Database\Eloquent\Model $user The user to log in.
     * @param bool $remember Whether to remember the user.
     * @return bool True if the login is successful, false otherwise.
     */
    public static function loginUsingId($userId, bool $remember = false): bool
    {
        $userModel = self::getInstance()->userModel;
        $user = $userModel::find($userId);
        
        if (!$user) {
            return false;
        }

        return self::login($user, $remember);
    }

    /**
     * Attempt to authenticate using "remember me" functionality.
     *
     * @return bool True if authentication via remember token was successful.
     */
    public static function viaRemember(): bool
    {
        $user = self::rememberTokenHandler()->checkRememberToken();
        
        if (!$user) {
            return false;
        }

        return self::login($user);
    }

    // Testing Helper Methods

    /**
     * Set a user as authenticated (useful for testing).
     *
     * @param \Illuminate\Database\Eloquent\Model $user The user to authenticate.
     */
    public static function actingAs($user): void
    {
        self::getInstance()->user = $user;
        self::getInstance()->session->set('auth_user_id', $user->id);
    }

    /**
     * Check if the current user has a specific role (if implemented in User model).
     *
     * @param string $role The role to check for.
     * @return bool True if the user has the role, false otherwise.
     */
    public static function hasRole(string $role): bool
    {
        $user = self::user();
        
        if (!$user || !method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole($role);
    }

    /**
     * Check if the current user has a specific permission (if implemented in User model).
     *
     * @param string $permission The permission to check for.
     * @return bool True if the user has the permission, false otherwise.
     */
    public static function hasPermission(string $permission): bool
    {
        $user = self::user();
        
        if (!$user || !method_exists($user, 'hasPermission')) {
            return false;
        }

        return $user->hasPermission($permission);
    }

    /**
     * Check if the current user can perform a specific ability (integration with Gate).
     *
     * @param string $ability The ability to check.
     * @param mixed ...$arguments Additional arguments for the ability check.
     * @return bool True if the user can perform the ability, false otherwise.
     */
    public static function can(string $ability, ...$arguments): bool
    {
        if (!function_exists('gate')) {
            return false;
        }

        return gate()->allows($ability, array_merge([self::user()], $arguments));
    }

    /**
     * Check if the current user cannot perform a specific ability (integration with Gate).
     *
     * @param string $ability The ability to check.
     * @param mixed ...$arguments Additional arguments for the ability check.
     * @return bool True if the user cannot perform the ability, false otherwise.
     */
    public static function cannot(string $ability, ...$arguments): bool
    {
        return !self::can($ability, ...$arguments);
    }
}