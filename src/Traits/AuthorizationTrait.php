<?php

namespace Rcalicdan\Ci4Larabridge\Traits;

use CodeIgniter\Exceptions\PageNotFoundException;
use InvalidArgumentException;
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
     * Authorize the current user for an action.
     * Throws PageNotFoundException if unauthorized.
     *
     * @param  string  $ability
     * @param  mixed  ...$arguments
     * @return bool
     *
     * @throws PageNotFoundException
     */
    protected function authorize($ability, ...$arguments, string $message = '', int $statusCode = 403)
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
