<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Pagination\LengthAwarePaginator;
use Rcalicdan\Blade\Blade;
use Rcalicdan\Ci4Larabridge\Providers\ComponentDirectiveProvider;
use Throwable;

/**
 * Provides CodeIgniter-specific integrations for the Blade templating engine.
 *
 * This class handles preprocessing of view data (like pagination and error handling)
 * and registers custom Blade directives commonly used in CI4/Laravel development,
 * including delegating component directive registration. It is typically invoked
 * by the `blade_view` helper function.
 */
class BladeExtension
{
    // ======================================================================
    // Public Entry Points (Called by blade_view helper)
    // ======================================================================

    /**
     * Processes the view data array before it's passed to the Blade engine.
     * This allows for modification or addition of data, such as rendering
     * pagination links or setting up validation error handling.
     *
     * @param  array  $data  The original data array for the view.
     * @return array The processed data array.
     */
    public function processData(array $data): array
    {
        $this->processPaginators($data);

        return $this->addErrorsHandler($data);
    }

    /**
     * Registers all custom directives provided by this extension with Blade.
     * This includes method spoofing, permission checks, error handling,
     * and delegates component system directives to its provider.
     *
     * @param  Blade  $blade  The Blade compiler instance.
     */
    public function registerDirectives(Blade $blade): void
    {
        $this->_registerMethodDirectives($blade);
        $this->_registerPermissionDirectives($blade);
        $this->_registerErrorDirectives($blade);
        $this->_registerBackDirectives($blade);
        $this->_registerAuthDirectives($blade);

        // Delegate component directive registration to the dedicated provider
        $componentProvider = new ComponentDirectiveProvider;
        $componentProvider->register($blade);
    }

    // ======================================================================
    // Data Processing Helpers (Called internally by processData)
    // ======================================================================

    /**
     * Processes Paginator instances, adding 'linksHtml' using Blade views via the 'blade' service.
     */
    protected function processPaginators(array $data): array
    {
        // Flag to check if any paginators exist to avoid unnecessary service calls
        $hasPaginator = false;
        foreach ($data as $value) {
            if ($value instanceof LengthAwarePaginator) {
                $hasPaginator = true;
                break;
            }
        }

        if (!$hasPaginator) {
            return $data; // No paginators, nothing to do
        }

        try {
            // Attempt to get the Blade service instance
            /** @var Blade $bladeInstance */
            $bladeInstance = service('blade'); // Rely on the service container

            // Basic type check (optional but recommended)
            if (!$bladeInstance instanceof Blade) {
                // This shouldn't happen if Services.php is correct, but good safeguard
                log_message('error', 'Service "blade" did not return a valid Blade instance type. Pagination links may not render.');
                throw new \RuntimeException('Invalid Blade service instance type returned.');
            }


            // Instantiate the renderer (can still cache statically for performance within a request)
            static $renderer = null;
            // Check if renderer exists or if blade instance changed (e.g. testing)
            if ($renderer === null || (method_exists($renderer, 'getBladeInstance') && $renderer->getBladeInstance() !== $bladeInstance)) {
                $renderer = new PaginationRenderer($bladeInstance);
            }

            // Iterate and render
            foreach ($data as $key => $value) {
                if ($value instanceof LengthAwarePaginator) {
                    // Avoid overwriting if linksHtml was somehow set manually
                    if (isset($value->linksHtml)) continue;

                    $theme = config('Pagination')->theme ?? 'bootstrap';
                    if (isset($data['paginationTheme'])) {
                        $theme = $data['paginationTheme'];
                    }
                    // The render method now uses the Blade instance passed to the constructor
                    $data[$key]->linksHtml = $renderer->render($value, $theme);
                }
            }
        } catch (Throwable $e) {
            // Log error only once per request if service isn't defined
            static $loggedSvcError = false;
            if (!$loggedSvcError) {
                log_message('error', 'Service "blade" not found. Please configure it in app/Config/Services.php for automatic pagination rendering. ' . $e->getMessage());
                $loggedSvcError = true;
            }
            // Assign placeholder to paginators
            foreach ($data as $key => $value) {
                if ($value instanceof LengthAwarePaginator && !isset($data[$key]->linksHtml)) {
                    $data[$key]->linksHtml = '<!-- Pagination Error: Blade service not found -->';
                }
            }
        } catch (Throwable $e) {
            // Catch other errors during rendering or instantiation
            log_message('error', 'Error processing paginators: ' . $e->getMessage());
            foreach ($data as $key => $value) {
                if ($value instanceof LengthAwarePaginator && !isset($data[$key]->linksHtml)) {
                    $data[$key]->linksHtml = '<!-- Pagination Processing Error -->';
                }
            }
        }

        return $data;
    }

    /**
     * Adds a simple error handling object, mimicking Laravel's `$errors` variable.
     * Retrieves validation errors from the session ('errors' key) and makes them
     * accessible in the view via an object with `has()` and `first()` methods,
     * enabling the use of the `@error` directive.
     *
     * @param  array  $data  The view data array.
     * @return array The data array, potentially with an `$errors` object added.
     */
    protected function addErrorsHandler(array $data): array
    {
        $sessionErrors = session('errors');
        if (! isset($data['errors']) && ! empty($sessionErrors) && is_array($sessionErrors)) {
            $data['errors'] = new class($sessionErrors)
            {
                protected array $errors;

                public function __construct(array $errors)
                {
                    $this->errors = $errors;
                }

                public function has(string $key): bool
                {
                    return isset($this->errors[$key]);
                }

                public function first(string $key): ?string
                {
                    return $this->errors[$key] ?? null;
                }

                public function getBag(string $key = 'default'): array
                {
                    return $this->errors;
                }

                public function any(): bool
                {
                    return ! empty($this->errors);
                }

                public function all(): array
                {
                    return $this->errors;
                }
            };
        }

        return $data;
    }

    // ======================================================================
    // Directive Registrars (Called internally by registerDirectives)
    // ======================================================================

    /**
     * Registers directives for HTTP method spoofing in forms.
     * Provides @method('PUT'), @delete, @put, @patch.
     *
     * @param  Blade  $blade  The Blade compiler instance.
     */
    private function _registerMethodDirectives(Blade $blade): void
    {
        $blade->directive('method', function ($expression) {
            $method = strtoupper(trim($expression, "()\"'"));

            return "<input type=\"hidden\" name=\"_method\" value=\"{$method}\">";
        });
        $blade->directive('delete', fn() => '<input type="hidden" name=\"_method\" value=\"DELETE\">');
        $blade->directive('put', fn() => '<input type="hidden" name=\"_method\" value=\"PUT\">');
        $blade->directive('patch', fn() => '<input type="hidden" name=\"_method\" value=\"PATCH\">');
    }

    /**
     * Registers directives for simple permission checks.
     * Provides @can(...) and @cannot(...).
     *
     * @param  Blade  $blade  The Blade compiler instance.
     */
    private function _registerPermissionDirectives(Blade $blade): void
    {
        $blade->directive('can', fn($expression) => "<?php if(can($expression)): ?>");
        $blade->directive('endcan', fn() => '<?php endif; ?>');
        $blade->directive('cannot', fn($expression) => "<?php if(cannot($expression)): ?>");
        $blade->directive('endcannot', fn() => '<?php endif; ?>');
    }

    private function _registerAuthDirectives(Blade $blade): void
    {
        $blade->directive('auth', fn() => '<?php if(auth()->check()):?>');
        $blade->directive('endauth', fn() => '<?php endif;?>');
        $blade->directive('guest', fn() => '<?php if(auth()->guest()):?>');
        $blade->directive('endguest', fn() => '<?php endif;?>');
    }

    /**
     * Registers directives for displaying validation errors.
     * Provides @error('field_name') ... @enderror. Relies on the `$errors`
     * object added by the `addErrorsHandler` method.
     *
     * @param  Blade  $blade  The Blade compiler instance.
     */
    private function _registerErrorDirectives(Blade $blade): void
    {
        $blade->directive('error', function ($expression) {
            return "<?php
                \$__fieldName = {$expression};
                \$__bladeErrors = \$errors ?? null;
                if (\$__bladeErrors && \$__bladeErrors->has(\$__fieldName)):
                \$message = \$__bladeErrors->first(\$__fieldName);
            ?>";
        });
        $blade->directive('enderror', fn() => '<?php unset($message, $__fieldName, $__bladeErrors); endif; ?>');
    }

    private function _registerBackDirectives(Blade $blade)
    {
        $blade->directive('back', function ($expression) {
            $expression = trim($expression, "()'\"");
            $default = $expression ? ", $expression" : '';

            return "<?php echo '<input type=\"hidden\" name=\"back\" value=\"'.e(back_url($default)).'\">'; ?>";
        });
    }
}
