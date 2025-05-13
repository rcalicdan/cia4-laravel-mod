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
 * Throws an UnauthorizedPageException (403) if the user is not authorized.
 *
 * @param  string  $ability  The ability to authorize.
 * @param  string  $message  Custom error message.
 * @param  mixed  ...$arguments  Additional arguments for the authorization check.
 * @return bool True if the user is authorized.
 *
 * @throws UnauthorizedPageException If the user is not authorized.
 */
if (! function_exists('authorize')) {
    function authorize($ability, $message = 'Unauthorized Action.', ...$arguments): bool
    {
        if (! is_string($message)) {
            array_unshift($arguments, $message);
            $message = 'Unauthorized Action.';
        }

        if (cannot($ability, ...$arguments)) {
            throw new UnauthorizedPageException($message);
        }

        return true;
    }
}

/**
 * Authorizes the current user to perform a specific ability.
 * Throws a PageNotFoundException (404) if the user is not authorized.
 *
 * @param  string  $ability  The ability to authorize.
 * @param  string  $message  Custom error message.
 * @param  mixed  ...$arguments  Additional arguments for the authorization check.
 * @return bool True if the user is authorized.
 *
 * @throws PageNotFoundException If the user is not authorized.
 */
if (! function_exists('authorizeOrNotFound')) {
    function authorizeOrNotFound($ability, $message = 'Page Not Found.', ...$arguments): bool
    {
        if (! is_string($message)) {
            array_unshift($arguments, $message);
            $message = 'Page Not Found.';
        }

        if (cannot($ability, ...$arguments)) {
            throw PageNotFoundException::forPageNotFound($message);
        }

        return true;
    }
}
