<?php

namespace Rcalicdan\Ci4Larabridge\Exceptions;

use Exception;

class PasswordResetThrottledException extends Exception
{
    public function __construct(string $message = 'Password reset request throttled. Please wait before requesting another reset.', int $code = 429, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}