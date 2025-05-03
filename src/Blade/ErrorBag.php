<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

/**
 * ErrorBag provides Laravel-compatible error handling functionality
 * for validation errors in Blade templates.
 */
class ErrorBag implements \Serializable
{
    /**
     * @var array The validation errors
     */
    protected array $errors;

    /**
     * Create a new ErrorBag instance.
     *
     * @param array $errors The validation errors
     */
    public function __construct(array $errors = [])
    {
        $this->errors = $errors;
    }

    /**
     * Check if the given field has an error.
     *
     * @param string $key The field name
     * @return bool Whether the field has an error
     */
    public function has(string $key): bool
    {
        return isset($this->errors[$key]);
    }

    /**
     * Get the first error message for a field.
     *
     * @param string $key The field name
     * @return string|null The error message or null
     */
    public function first(string $key): ?string
    {
        return $this->errors[$key] ?? null;
    }

    /**
     * Get all errors as an error bag.
     *
     * @param string $key The bag name (unused, for Laravel compatibility)
     * @return array The errors
     */
    public function getBag(string $key = 'default'): array
    {
        return $this->errors;
    }

    /**
     * Check if there are any errors.
     *
     * @return bool Whether any errors exist
     */
    public function any(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get all error messages.
     *
     * @return array All error messages
     */
    public function all(): array
    {
        return $this->errors;
    }

    /**
     * Serialize the error bag for caching.
     *
     * @return string The serialized object
     */
    public function serialize(): string
    {
        return serialize($this->errors);
    }

    /**
     * Unserialize the error bag from cache.
     *
     * @param string $data The serialized data
     * @return void
     */
    public function unserialize($data)
    {
        $this->errors = unserialize($data);
    }

    /**
     * __serialize method for PHP 7.4+
     * 
     * @return array
     */
    public function __serialize(): array
    {
        return $this->errors;
    }

    /**
     * __unserialize method for PHP 7.4+
     * 
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->errors = $data;
    }
}