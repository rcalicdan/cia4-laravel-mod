<?php

namespace Rcalicdan\Ci4Larabridge\Traits\Authentication;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait HasPasswordReset
{
    /**
     * Generate a password reset token and store it in the password_reset_tokens table.
     *
     * @return string The plain text token.
     */
    public function generatePasswordResetToken(): string
    {
        $token = Str::random(60);

        // Remove existing tokens for this email
        $this->clearPasswordResetToken();

        DB::table('password_reset_tokens')->insert([
            'email' => $this->email,
            'token' => hash('sha256', $token),
            'created_at' => Carbon::now(),
        ]);

        return $token;
    }

    /**
     * Clear the password reset token from the password_reset_tokens table.
     *
     * @return bool True if the token was cleared or did not exist.
     */
    public function clearPasswordResetToken(): bool
    {
        return DB::table('password_reset_tokens')->where('email', $this->email)->delete() > 0;
    }

    /**
     * Get password reset token data from the password_reset_tokens table.
     *
     * @param  string  $token  The plain text token to look up.
     * @return object|null The token data (email, token, created_at) or null if not found.
     */
    public static function getPasswordResetTokenData(string $hashedToken): ?object
    {
        return DB::table('password_reset_tokens')->where('token', $hashedToken)->first();
    }
}
