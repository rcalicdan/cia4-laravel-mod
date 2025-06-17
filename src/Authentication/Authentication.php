<?php

namespace Rcalicdan\Ci4Larabridge\Authentication;

use Config\Services;
use Illuminate\Support\Carbon;
use Rcalicdan\Ci4Larabridge\Exceptions\UnverifiedEmailException;
use Rcalicdan\Ci4Larabridge\Models\User as BridgeUser;

class Authentication
{
    protected $session;
    protected $user = null;
    protected $userModel;
    protected $config;
    protected $emailHandler;
    protected $rememberTokenHandler;

    public function __construct()
    {
        $this->config = config('LarabridgeAuthentication');
        $this->userModel = $this->resolveUserModel();
        $this->session = Services::session();

        $this->emailHandler = new EmailHandler($this->config);
        $this->rememberTokenHandler = new RememberTokenHandler($this->config, $this->userModel);

        // Check for remember me token on construction
        if (!$this->session->get('auth_user_id')) {
            $this->checkRememberToken();
        }
    }

    /**
     * Resolve the user model class
     */
    protected function resolveUserModel(): string
    {
        return class_exists(\App\Models\User::class)
            ? \App\Models\User::class
            : BridgeUser::class;
    }

    /**
     * Get the currently authenticated user
     */
    public function user(): ?\Illuminate\Database\Eloquent\Model
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $userId = $this->session->get('auth_user_id');
        if (! $userId) {
            return null;
        }

        $this->user = $this->userModel::find($userId);

        return $this->user;
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Check if user is guest
     */
    public function guest(): bool
    {
        return ! $this->check();
    }

    /**
     * Attempt to authenticate user
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $user = $this->findUserByCredentials($credentials);

        if (! $user || ! $this->validatePassword($credentials['password'], $user->password)) {
            return false;
        }

        $this->validateEmailVerification($user);

        return $this->login($user, $remember);
    }

    /**
     * Login user
     */
    public function login($user, bool $remember = false): bool
    {
        if ($remember) {
            $this->rememberTokenHandler->setRememberToken($user);
        }

        $this->setUserSession($user);

        return true;
    }

    /**
     * Logout user
     */
    public function logout(): bool
    {
        $this->clearUserData();
        $this->clearSession();

        return true;
    }

    /**
     * Send password reset link
     */
    public function sendPasswordResetLink(string $email): bool
    {
        $user = $this->findUserByEmail($email);

        if (! $user) {
            return true; // Don't reveal if email exists
        }

        // The generatePasswordResetToken method in HasPasswordReset trait now handles token creation in the new table
        $token = $user->generatePasswordResetToken();

        return $this->emailHandler->sendPasswordResetEmail($user, $token);
    }

    /**
     * Reset password using token
     */
    public function resetPassword(string $token, string $password): bool
    {
        $user = $this->findUserByResetToken($token);

        if (! $user) {
            return false;
        }

        $user->update(['password' => $password]); // Assumes User model has HashedPasswordTrait or similar for password hashing
        $user->clearPasswordResetToken(); // This now clears from password_reset_tokens table via HasPasswordReset trait

        return true;
    }

    /**
     * Send email verification
     */
    public function sendEmailVerification($user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }

        // The generateEmailVerificationToken method in HasEmailVerification trait
        // now handles token creation in the new email_verification_tokens table.
        $token = $user->generateEmailVerificationToken();

        return $this->emailHandler->sendVerificationEmail($user, $token);
    }

    /**
     * Verify email using token
     */
    public function verifyEmail(string $token): bool
    {
        $user = $this->findUserByVerificationToken($token);

        if (! $user) {
            return false;
        }

        return $user->markEmailAsVerified(); // This now also clears the token from the new table via the trait
    }

    /**
     * Get email handler instance
     */
    public function getEmailHandler(): EmailHandler
    {
        return $this->emailHandler;
    }

    /**
     * Get remember token handler instance
     */
    public function getRememberTokenHandler(): RememberTokenHandler
    {
        return $this->rememberTokenHandler;
    }

    // Protected helper methods

    protected function findUserByCredentials(array $credentials): ?object
    {
        $model = $this->userModel;

        return $model::where('email', $credentials['email'])->first();
    }

    protected function findUserByEmail(string $email): ?object
    {
        $model = $this->userModel;

        return $model::where('email', $email)->first();
    }

    protected function findUserByResetToken(string $token): ?object
    {
        $hashedToken = hash('sha256', $token);

        // Use the getPasswordResetTokenData from the User model's trait (statically)
        // assuming your User model uses the HasPasswordReset trait.
        $tokenData = $this->userModel::getPasswordResetTokenData($hashedToken);

        if (! $tokenData) {
            return null;
        }

        // Check if the token has expired
        $expiresAt = Carbon::parse($tokenData->created_at)->addSeconds($this->config->passwordReset['tokenExpiry']);
        if (Carbon::now()->isAfter($expiresAt)) {
            // Optionally, delete the expired token
            // DB::table('password_reset_tokens')->where('token', $hashedToken)->delete();
            return null;
        }

        return $this->findUserByEmail($tokenData->email);
    }

    protected function findUserByVerificationToken(string $token): ?object
    {
        $hashedToken = hash('sha256', $token);

        // Use the getEmailVerificationTokenData from the User model's trait
        $tokenData = $this->userModel::getEmailVerificationTokenData($hashedToken);

        if (! $tokenData) {
            return null;
        }

        // Check if the token has expired
        if (Carbon::now()->isAfter(Carbon::parse($tokenData->expires_at))) {
            // Optionally, delete the expired token from email_verification_tokens table
            // DB::table('email_verification_tokens')->where('token', $hashedToken)->delete();
            return null;
        }

        return $this->findUserByEmail($tokenData->email);
    }

    protected function validatePassword(string $password, string $hashedPassword): bool
    {
        return password_verify($password, $hashedPassword);
    }

    protected function validateEmailVerification($user): void
    {
        if ($this->config->emailVerification['required'] && ! $user->hasVerifiedEmail()) {
            throw new UnverifiedEmailException('Email verification required');
        }
    }

    protected function setUserSession($user): void
    {
        $this->session->set('auth_user_id', $user->id);
        $this->user = $user;

        if ($this->config->session['regenerateOnLogin']) {
            $this->session->regenerate(true);
        }
    }

    protected function clearUserData(): void
    {
        if ($this->user) {
            $this->user->clearRememberToken();
        }

        $this->rememberTokenHandler->clearCookie();
        $this->user = null;
    }

    protected function clearSession(): void
    {
        $this->session->remove('auth_user_id');

        if ($this->config->session['regenerateOnLogout']) {
            $this->session->regenerate(true);
        }
    }

    protected function checkRememberToken(): void
    {
        if ($this->check()) {
            return;
        }

        $user = $this->rememberTokenHandler->checkRememberToken();
        if ($user) {
            $this->login($user);
        }
    }
}
