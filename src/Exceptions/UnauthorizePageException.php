<?php

namespace App\Exceptions;

use RuntimeException;
use CodeIgniter\Exceptions\HTTPExceptionInterface;

class UnauthorizedPageException extends RuntimeException implements HTTPExceptionInterface
{
    /**
     * The HTTP status code for this exception.
     * CodeIgniter’s handler will use this as the response code
     * and look for app/Views/errors/html/error_403.php
     */
    protected int $code = 403;

    /**
     * Constructor: accepts an optional custom message.
     */
    public function __construct(?string $message = null, ?\Throwable $previous = null)
    {
        $message ??= 'Unauthorized Action.';
        parent::__construct($message, $this->code, $previous);
    }

    /**
     * A static helper to throw it with a fluent API:
     *
     *     throw UnauthorizedPageException::forPage('Custom message');
     */
    public static function forPage(?string $message = null): static
    {
        return new static($message);
    }
}
