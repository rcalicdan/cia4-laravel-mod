<?php

namespace Rcalicdan\Ci4Larabridge\Traits\Authentication;

use Carbon\Carbon;
use Config\Services;
use Illuminate\Support\Str;

trait HasPasswordReset
{
    /**
     * Generate password reset token
     */
    public function generatePasswordResetToken(): string
    {
        $token = Str::random(60);
        $config = Services::config('LarabridgeAuthentication');

        $this->update([
            'password_reset_token' => hash('sha256', $token),
            'password_reset_expires_at' => Carbon::now()->addSeconds($config->passwordReset['tokenExpiry']),
            'password_reset_created_at' => Carbon::now(),
        ]);

        return $token;
    }

    /**
     * Clear password reset token
     */
    public function clearPasswordResetToken(): bool
    {
        return $this->update([
            'password_reset_token' => null,
            'password_reset_expires_at' => null,
            'password_reset_created_at' => null,
        ]);
    }

    /**
     * Check if password reset token is expired
     */
    public function isPasswordResetTokenExpired(): bool
    {
        if (! $this->password_reset_expires_at) {
            return true;
        }

        return Carbon::now()->isAfter($this->password_reset_expires_at);
    }

    /**
     * Check if password reset token is valid
     */
    public function isValidPasswordResetToken(string $token): bool
    {
        if (! $this->password_reset_token || $this->isPasswordResetTokenExpired()) {
            return false;
        }

        return hash_equals($this->password_reset_token, hash('sha256', $token));
    }

    /**
     * Reset password with token validation
     */
    public function resetPasswordWithToken(string $token, string $newPassword): bool
    {
        if (! $this->isValidPasswordResetToken($token)) {
            return false;
        }

        $updated = $this->update([
            'password' => $newPassword,
        ]);

        if ($updated) {
            $this->clearPasswordResetToken();
        }

        return $updated;
    }

    /**
     * Check if user can request password reset (throttling)
     */
    public function canRequestPasswordReset(): bool
    {
        if (! $this->password_reset_created_at) {
            return true;
        }

        $config = config('Ci4Larabridge');
        $throttleTime = $config->passwordReset['throttle'] ?? 60;

        return Carbon::now()->diffInSeconds($this->password_reset_created_at) >= $throttleTime;
    }
}
