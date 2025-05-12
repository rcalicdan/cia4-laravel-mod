<?php

namespace Rcalicdan\Ci4Larabridge\Exceptions;

use RuntimeException;
use CodeIgniter\Exceptions\HTTPExceptionInterface;

class UnauthorizedPageException extends RuntimeException implements HTTPExceptionInterface
{
    /**
     * @param string         $message  Custom or default message
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'You do not have permission to access this page.',
        \Throwable $previous = null
    ) {
        // Pass 403 as the exception code
        parent::__construct($message, 403, $previous);
    }

    /**
     * (Optional) A fluent helper:
     */
    public static function forPage(string $message = null): static
    {
        return new static($message ?? 'You do not have permission to access this page.');
    }
}
