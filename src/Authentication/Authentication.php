<?php

namespace Rcalicdan\Ci4Larabridge\Authentication;

use Config\Services;
use Illuminate\Support\Carbon;
use Rcalicdan\Ci4Larabridge\Models\User as BridgeUser;
use UnverifiedEmailException;

class Authentication
{
    protected $session;
    protected $user = null;
    protected $userModel;
    protected $config;
    protected $response;
    protected $email;
    protected $request;

    public function __construct()
    {
        $this->config = config('LarabridgeAuthentication');

        $this->userModel = class_exists(\App\Models\User::class)
            ? \App\Models\User::class
            : BridgeUser::class;

        $this->session = Services::session();
        $this->response = Services::response();
        $this->email = Services::email();
        $this->request = Services::request();

        // Check for remember me token on construction
        $this->checkRememberToken();
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

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    /**
     * Attempt to authenticate user
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $model = $this->userModel;
        $user = $model::where('email', $credentials['email'])->first();

        if (! $user || ! password_verify($credentials['password'], $user->password)) {
            return false;
        }

        if ($this->config->emailVerification['required'] && ! $user->hasVerifiedEmail()) {
            throw new UnverifiedEmailException('Email verification required');
        }

        return $this->login($user, $remember);
    }

    /**
     * Login user
     * Handle remember me functionality
     */
    public function login($user, bool $remember = false): bool
    {
        $this->session->set('auth_user_id', $user->id);
        $this->user = $user;

        if ($this->config->session['regenerateOnLogin']) {
            $this->session->regenerate(true);
        }

        if ($remember && $this->config->rememberMe['enabled']) {
            $this->setRememberToken($user);
        }

        return true;
    }

    /**
     * Logout user
     * check if remember token exists
     * if exists, check if user exists
     * if user exists, login user
     * if user does not exist, delete remember token
     */
    public function logout(): bool
    {
        if ($this->user) {
            $this->user->clearRememberToken();
        }

        $this->clearRememberCookie();
        $this->user = null;
        $this->session->remove('auth_user_id');

        if ($this->config->session['regenerateOnLogout']) {
            $this->session->regenerate(true);
        }

        return true;
    }

    public function sendPasswordResetLink(string $email): bool
    {
        $model = $this->userModel;
        $user = $model::where('email', $email)->first();

        if (! $user) {
            return true; // Don't reveal if email exists
        }

        $token = $user->generatePasswordResetToken();
        $user->update(['password_reset_created_at' => Carbon::now()]);

        return $this->sendPasswordResetEmail($user, $token);
    }

    /**
     * Reset password using token
     */
    public function resetPassword(string $token, string $password): bool
    {
        $hashedToken = hash('sha256', $token);
        $model = $this->userModel;

        $user = $model::where('password_reset_token', $hashedToken)
            ->where('password_reset_expires_at', '>', Carbon::now())
            ->first()
        ;

        if (! $user) {
            return false;
        }

        $user->update([
            'password' => $password,
        ]);

        $user->clearPasswordResetToken();

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

        $token = $user->generateEmailVerificationToken();

        return $this->sendVerificationEmail($user, $token);
    }

    /**
     * Verify email using token
     */
    public function verifyEmail(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        $model = $this->userModel;

        $user = $model::where('email_verification_token', $hashedToken)
            ->where('email_verification_expires_at', '>', Carbon::now())
            ->first()
        ;

        if (! $user) {
            return false;
        }

        return $user->markEmailAsVerified();
    }

    /**
     * Set remember token cookie
     */
    protected function setRememberToken($user): void
    {
        $token = $user->generateRememberToken();

        $this->response->setCookie([
            'name' => $this->config->rememberMe['cookieName'],
            'value' => "{$user->id}|{$token}",
            'expire' => $this->config->rememberMe['tokenExpiry'],
            'secure' => $this->config->rememberMe['cookieSecure'],
            'httponly' => $this->config->rememberMe['cookieHttpOnly'],
        ]);
    }

    /**
     * Check remember token
     */
    protected function checkRememberToken(): void
    {
        if (! $this->config->rememberMe['enabled'] || $this->check()) {
            return;
        }

        $cookieValue = $this->request->getCookie($this->config->rememberMe['cookieName']);

        if (! $cookieValue) {
            return;
        }

        [$userId, $token] = explode('|', $cookieValue, 2);

        $model = $this->userModel;
        $user = $model::where('id', $userId)
            ->where('remember_token', hash('sha256', $token))
            ->first()
        ;

        if ($user) {
            $this->login($user);
        } else {
            $this->clearRememberCookie();
        }
    }

    /**
     * Clear remember cookie
     */
    protected function clearRememberCookie(): void
    {
        delete_cookie($this->config->rememberMe['cookieName']);
    }

    /**
     * Send password reset email (implement based on your email service)
     * Load the password reset email template
     */
    protected function sendPasswordResetEmail($user, string $token): bool
    {
        try {
            $resetUrl = site_url("password/reset/{$token}");

            $this->email->clear();
            $this->email->setFrom(
                $this->config->email['fromEmail'] ?? 'noreply@'.$_SERVER['HTTP_HOST'],
                $this->config->email['fromName'] ?? 'Your Application'
            );
            $this->email->setTo($user->email);
            $this->email->setSubject('Password Reset Request');

            $emailBody = view($this->config->passwordResetVerficationViewPath, [
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiry' => $this->config->passwordReset['tokenExpiry'] / 3600,
            ]);

            $this->email->setMessage($emailBody);

            return $this->email->send();
        } catch (\Exception $e) {
            log_message('error', 'Password reset email failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send verification email (implement based on your email service)
     * Load the email verification template
     */
    protected function sendVerificationEmail($user, string $token): bool
    {
        try {
            $verificationUrl = site_url("email/verify/{$token}");

            $this->email->clear();
            $this->email->setFrom(
                $this->config->email['fromEmail'] ?? 'noreply@'.$_SERVER['HTTP_HOST'],
                $this->config->email['fromName'] ?? 'Your Application'
            );
            $this->email->setTo($user->email);
            $this->email->setSubject('Verify Your Email Address');

            $emailBody = view($this->config->emailVerificationViewPath, [
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'expiry' => $this->config->emailVerification['tokenExpiry'] / 3600,
            ]);

            $this->email->setMessage($emailBody);

            return $this->email->send();
        } catch (\Exception $e) {
            log_message('error', 'Email verification failed: '.$e->getMessage());

            return false;
        }
    }
}
