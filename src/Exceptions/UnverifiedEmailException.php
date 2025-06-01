<?php

namespace Rcalicdan\Ci4Larabridge\Exceptions;

use Exception;
class UnverifiedEmailException extends Exception
{
    public function __construct($message = 'Email verification is required', $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
