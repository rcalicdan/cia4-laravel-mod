<?php

namespace Rcalicdan\Ci4Larabridge\Traits\Authentication;

use Carbon\Carbon;
use Config\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasEmailVerification
{
    /**
     * Check if email is verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Mark email as verified.
     * This sets the email_verified_at timestamp on the user
     * and clears the verification token from the email_verification_tokens table.
     */
    public function markEmailAsVerified(): bool
    {
        if ($this->update(['email_verified_at' => Carbon::now()])) {
            DB::table('email_verification_tokens')->where('email', $this->email)->delete();
            return true;
        }
        return false;
    }

    /**
     * Generate an email verification token and store it in the email_verification_tokens table.
     *
     * @return string The plain text token.
     */
    public function generateEmailVerificationToken(): string
    {
        $token = Str::random(60);
        $config = Services::config('LarabridgeAuthentication');
        $expiresAt = Carbon::now()->addSeconds($config->emailVerification['tokenExpiry']);

        // Remove existing tokens for this email to ensure only one active token exists
        DB::table('email_verification_tokens')->where('email', $this->email)->delete();

        DB::table('email_verification_tokens')->insert([
            'email' => $this->email,
            'token' => hash('sha256', $token),
            'created_at' => Carbon::now(),
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Get email verification token data from the email_verification_tokens table.
     *
     * @param string $hashedToken The hashed token to look up.
     * @return object|null The token data (email, token, created_at, expires_at) or null if not found.
     */
    public static function getEmailVerificationTokenData(string $hashedToken): ?object
    {
        return DB::table('email_verification_tokens')->where('token', $hashedToken)->first();
    }

    /**
     * Resend email verification.
     * Regenerates and stores a new token.
     */
    public function resendEmailVerification(): string
    {
        if ($this->hasVerifiedEmail()) {
            return ''; // prevent generating a new token if already verified.;
        }

        return $this->generateEmailVerificationToken();
    }
}
