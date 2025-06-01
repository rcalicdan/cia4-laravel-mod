<?php

namespace Rcalicdan\Ci4Larabridge\Traits\Authentication;

use Illuminate\Support\Str;
use Carbon\Carbon;
use Config\Services;

trait HasEmailVerification
{
    /**
     * Check if email is verified
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified(): bool
    {
        return $this->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'email_verification_expires_at' => null,
        ]);
    }

    /**
     * Generate email verification token
     */
    public function generateEmailVerificationToken(): string
    {
        $token = Str::random(60);
        $config = Services::config('LarabridgeAuthentication');
        
        $this->update([
            'email_verification_token' => hash('sha256', $token),
            'email_verification_expires_at' => Carbon::now()->addSeconds($config->emailVerification['tokenExpiry']),
        ]);

        return $token;
    }

    /**
     * Check if email verification token is expired
     */
    public function isEmailVerificationTokenExpired(): bool
    {
        if (!$this->email_verification_expires_at) {
            return true;
        }

        return Carbon::now()->isAfter($this->email_verification_expires_at);
    }

    /**
     * Clear email verification token
     */
    public function clearEmailVerificationToken(): bool
    {
        return $this->update([
            'email_verification_token' => null,
            'email_verification_expires_at' => null,
        ]);
    }

    /**
     * Resend email verification
     */
    public function resendEmailVerification(): string
    {
        if ($this->hasVerifiedEmail()) {
            throw new \Exception('Email is already verified');
        }

        return $this->generateEmailVerificationToken();
    }
}