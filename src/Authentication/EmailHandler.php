<?php

namespace Rcalicdan\Ci4Larabridge\Authentication;

use Config\Services;

/**
 * Handles all email-related authentication operations
 */
class EmailHandler
{
    protected $email;
    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
        $this->email = Services::email();
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($user, string $token): bool
    {
        try {
            $resetUrl = $this->generatePasswordResetUrl($token);

            $this->prepareEmail(
                $user->email,
                'Password Reset Request',
                $this->config->passwordResetViewPath,
                [
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                    'expiry' => $this->config->passwordReset['tokenExpiry'] / 3600,
                ]
            );

            return $this->email->send();
        } catch (\Exception $e) {
            log_message('error', 'Password reset email failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send email verification email
     */
    public function sendVerificationEmail($user, string $token): bool
    {
        try {
            $verificationUrl = $this->generateEmailVerificationUrl($token);

            $this->prepareEmail(
                $user->email,
                'Verify Your Email Address',
                $this->config->emailVerificationViewPath,
                [
                    'user' => $user,
                    'verificationUrl' => $verificationUrl,
                    'expiry' => $this->config->emailVerification['tokenExpiry'] / 3600,
                ]
            );

            return $this->email->send();
        } catch (\Exception $e) {
            log_message('error', 'Email verification failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Prepare email with common settings
     */
    protected function prepareEmail(string $to, string $subject, string $viewPath, array $data): void
    {
        $this->email->clear();
        $this->email->setFrom(
            $this->config->email['fromEmail'] ?? 'noreply@'.$_SERVER['HTTP_HOST'],
            $this->config->email['fromName'] ?? 'Your Application'
        );
        $this->email->setTo($to);
        $this->email->setSubject($subject);

        $emailBody = view($viewPath, $data);
        $this->email->setMessage($emailBody);
    }

    /**
     * Generate password reset URL
     */
    protected function generatePasswordResetUrl(string $token): string
    {
        return site_url("password/reset/{$token}");
    }

    /**
     * Generate email verification URL
     */
    protected function generateEmailVerificationUrl(string $token): string
    {
        return site_url("email/verify/{$token}");
    }
}
