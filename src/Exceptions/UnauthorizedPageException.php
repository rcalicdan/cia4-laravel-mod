<?php

namespace Rcalicdan\Ci4Larabridge\Exceptions;

use CodeIgniter\Exceptions\HTTPExceptionInterface;
use RuntimeException;

class UnauthorizedPageException extends RuntimeException implements HTTPExceptionInterface
{
    /**
     * @param  string  $message  Custom or default message
     */
    public function __construct(
        string $message = 'You do not have permission to access this page.',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 403, $previous);
    }

    /**
     * (Optional) A fluent helper:
     */
    public static function forPage(?string $message = null): static
    {
        return new static($message ?? 'You do not have permission to access this page.');
    }
}
