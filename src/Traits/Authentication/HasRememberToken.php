<?php

namespace Rcalicdan\Ci4Larabridge\Traits\Authentication;

use Illuminate\Support\Str;

trait HasRememberToken
{
    /**
     * Generate remember token
     */
    public function generateRememberToken(): string
    {
        $token = Str::random(60);

        $this->update([
            'remember_token' => hash('sha256', $token),
        ]);

        return $token;
    }

    /**
     * Clear remember token
     */
    public function clearRememberToken(): bool
    {
        return $this->update([
            'remember_token' => null,
        ]);
    }

    /**
     * Check if remember token is valid
     */
    public function isValidRememberToken(string $token): bool
    {
        if (! $this->remember_token) {
            return false;
        }

        return hash_equals($this->remember_token, hash('sha256', $token));
    }

    /**
     * Refresh remember token
     */
    public function refreshRememberToken(): string
    {
        return $this->generateRememberToken();
    }
}
