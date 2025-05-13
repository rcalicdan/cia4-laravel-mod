<?php

use CodeIgniter\Exceptions\PageNotFoundException;
use Rcalicdan\Ci4Larabridge\Authentication\Gate;
use Rcalicdan\Ci4Larabridge\Exceptions\UnauthorizedPageException;

/**
 * Retrieves an instance of the Gate class.
 *
 * @return Gate The Gate instance.
 */
if (! function_exists('gate')) {
    function gate()
    {
        return Gate::getInstance();
    }
}

/**
 * Checks if the current user has a specific ability.
 *
 * @param  string  $ability  The ability to check.
 * @param  mixed  ...$arguments  Additional arguments for the ability check.
 * @return bool True if the user can perform the ability, false otherwise.
 */
if (! function_exists('can')) {
    function can($ability, ...$arguments)
    {
        return gate()->allows($ability, array_merge([auth()->user()], $arguments));
    }
}

/**
 * Checks if the current user does not have a specific ability.
 *
 * @param  string  $ability  The ability to check.
 * @param  mixed  ...$arguments  Additional arguments for the ability check.
 * @return bool True if the user cannot perform the ability, false otherwise.
 */
if (! function_exists('cannot')) {
    function cannot($ability, ...$arguments)
    {
        return gate()->denies($ability, array_merge([auth()->user()], $arguments));
    }
}

/**
 * Authorizes the current user to perform a specific ability.
 * Throws a PageNotFoundException if the user is not authorized.
 *
 * @param  string  $ability  The ability to authorize.
 * @param  mixed  ...$arguments  Additional arguments for the authorization check.
 * @return bool True if the user is authorized.
 *
 * @throws PageNotFoundException If the user is not authorized.
 */
if (! function_exists('authorize')) {
    function authorize($ability, ...$arguments, string $message = '', int $statusCode = 403): bool
    {
        if ($statusCode !== 403 || $statusCode !== 404) {
            throw new InvalidArgumentException('Invalid status code. Only 403 and 404 are allowed.');
        }

        if (cannot($ability, ...$arguments) && $statusCode === 404) {
            $message ??= 'Page Not Found.';

            throw PageNotFoundException::forPageNotFound($message);
        }

        if (cannot($ability, ...$arguments) && $statusCode === 403) {
            $message ??= 'Unauthorized Action.';

            throw new UnauthorizedPageException($message);
        }

        return true;
    }
}
