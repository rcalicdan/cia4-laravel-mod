<?php

/**
 * Bcrypt Helper
 *
 * Provides convenient functions for hashing and verifying passwords using bcrypt.
 */
if (! function_exists('bcrypt')) {
    /**
     * Hash a value using bcrypt
     *
     * @param  string  $value  The value to hash
     * @param  array  $options  Options array containing 'cost' (default: 10)
     * @return string The hashed value
     */
    function bcrypt(string $value, array $options = []): string
    {
        $cost = $options['cost'] ?? 10;

        $cost = max(4, min(31, $cost));

        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $cost,
        ]);

        if ($hash === false) {
            throw new RuntimeException('Bcrypt hashing failed');
        }

        return $hash;
    }
}

if (! function_exists('bcrypt_verify')) {
    /**
     * Verify a value against a bcrypt hash
     *
     * @param  string  $value  The value to verify
     * @param  string  $hashedValue  The hashed value to compare against
     * @return bool True if the value matches the hash, false otherwise
     */
    function bcrypt_verify(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }
}

if (! function_exists('bcrypt_needs_rehash')) {
    /**
     * Check if the hash needs to be rehashed
     *
     * @param  string  $hashedValue  The hashed value to check
     * @param  array  $options  Options array containing 'cost' (default: 10)
     * @return bool True if the hash needs to be rehashed, false otherwise
     */
    function bcrypt_needs_rehash(string $hashedValue, array $options = []): bool
    {
        $cost = $options['cost'] ?? 10;

        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $cost,
        ]);
    }
}
