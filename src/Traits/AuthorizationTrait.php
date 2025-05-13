<?php

namespace Rcalicdan\Ci4Larabridge\Traits;

use CodeIgniter\Exceptions\PageNotFoundException;
use Rcalicdan\Ci4Larabridge\Authentication\Gate;
use Rcalicdan\Ci4Larabridge\Exceptions\UnauthorizedPageException;

trait AuthorizationTrait
{
    /**
     * Get the Gate instance.
     *
     * @return Gate
     */
    protected function gate()
    {
        return Gate::getInstance();
    }

    /**
     * Determine if the user can perform the given ability.
     *
     * @param  string  $ability
     * @param  mixed  ...$arguments
     * @return bool
     */
    protected function can($ability, ...$arguments)
    {
        return $this->gate()->allows($ability, array_merge([auth()->user()], $arguments));
    }

    /**
     * Determine if the user cannot perform the given ability.
     *
     * @param  string  $ability
     * @param  mixed  ...$arguments
     * @return bool
     */
    protected function cannot($ability, ...$arguments)
    {
        return $this->gate()->denies($ability, array_merge([auth()->user()], $arguments));
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
    protected function authorize($ability, ...$arguments): bool
    {
        if (cannot($ability, ...$arguments)) {
            throw new UnauthorizedPageException('Unauthorized Action.');
        }

        return true;
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
    public function authorizeOrNotFound($ability, ...$arguments): bool
    {
        if (cannot($ability, ...$arguments)) {
            throw PageNotFoundException::forPageNotFound('Page Not Found.');
        }

        return true;
    }
}
