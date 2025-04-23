<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Pagination\LengthAwarePaginator;
use Jenssegers\Blade\Blade;
use Rcalicdan\Ci4Larabridge\Providers\ComponentDirectiveProvider;

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
    //======================================================================
    // Public Entry Points (Called by blade_view helper)
    //======================================================================

    /**
     * Processes the view data array before it's passed to the Blade engine.
     * This allows for modification or addition of data, such as rendering
     * pagination links or setting up validation error handling.
     *
     * @param array $data The original data array for the view.
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
     * @param Blade $blade The Blade compiler instance.
     * @return void
     */
    public function registerDirectives(Blade $blade): void
    {
        $this->_registerMethodDirectives($blade);
        $this->_registerPermissionDirectives($blade);
        $this->_registerErrorDirectives($blade);
        $this->_registerBackDirectives($blade);

        // Delegate component directive registration to the dedicated provider
        $componentProvider = new ComponentDirectiveProvider();
        $componentProvider->register($blade);
    }

    //======================================================================
    // Data Processing Helpers (Called internally by processData)
    //======================================================================

    /**
     * Processes `LengthAwarePaginator` instances within the view data.
     * Intended to add rendered pagination links (e.g., `linksHtml` property)
     * using a specified renderer class.
     *
     * Note: This method's logic is preserved exactly as provided previously.
     * Ensure `RcalicdanPaginationRenderer` exists and functions as expected.
     *
     * @param array &$data The view data array (passed by reference).
     * @return void
     */
    protected function processPaginators(&$data): void
    {
        foreach ($data as $key => $value) {
            if ($value instanceof LengthAwarePaginator) {
                $theme = config('Pagination')->theme ?? 'bootstrap';
                if (isset($data['paginationTheme'])) {
                    $theme = $data['paginationTheme'];
                }
                // Attempt to use PaginationRenderer if it exists
                if (class_exists(PaginationRenderer::class)) {
                    $renderer = new PaginationRenderer();
                    $data[$key]->linksHtml = $renderer->render($value, $theme);
                } else {
                    // Log a warning if the expected renderer is missing
                    log_message('warning', 'PaginationRenderer class not found. Pagination links not rendered.');
                    $data[$key]->linksHtml = '<!-- Pagination Renderer Missing -->';
                }
            }
        }
    }

    /**
     * Adds a simple error handling object, mimicking Laravel's `$errors` variable.
     * Retrieves validation errors from the session ('errors' key) and makes them
     * accessible in the view via an object with `has()` and `first()` methods,
     * enabling the use of the `@error` directive.
     *
     * @param array $data The view data array.
     * @return array The data array, potentially with an `$errors` object added.
     */
    protected function addErrorsHandler(array $data): array
    {
        $sessionErrors = session('errors');
        if (!isset($data['errors']) && !empty($sessionErrors) && is_array($sessionErrors)) {
            $data['errors'] = new class($sessionErrors) {
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
                    return !empty($this->errors);
                }
                public function all(): array
                {
                    return $this->errors;
                }
            };
        }
        return $data;
    }

    //======================================================================
    // Directive Registrars (Called internally by registerDirectives)
    //======================================================================

    /**
     * Registers directives for HTTP method spoofing in forms.
     * Provides @method('PUT'), @delete, @put, @patch.
     *
     * @param Blade $blade The Blade compiler instance.
     * @return void
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
     * @param Blade $blade The Blade compiler instance.
     * @return void
     */
    private function _registerPermissionDirectives(Blade $blade): void
    {
        $blade->directive('can', fn($expression) => "<?php if(can($expression)): ?>");
        $blade->directive('endcan', fn() => "<?php endif; ?>");
        $blade->directive('cannot', fn($expression) => "<?php if(cannot($expression)): ?>");
        $blade->directive('endcannot', fn() => "<?php endif; ?>");
    }

    private function _registerAuthDirectives(Blade $blade): void
    {
        $blade->directive('auth', fn() => "<?php if(auth()->check()):?>");
        $blade->directive('endauth', fn() => "<?php endif;?>");
        $blade->directive('guest', fn() => "<?php if(auth()->guest()):?>");
        $blade->directive('endguest', fn() => "<?php endif;?>");
    }

    /**
     * Registers directives for displaying validation errors.
     * Provides @error('field_name') ... @enderror. Relies on the `$errors`
     * object added by the `addErrorsHandler` method.
     *
     * @param Blade $blade The Blade compiler instance.
     * @return void
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
        $blade->directive('enderror', fn() => "<?php unset(\$message, \$__fieldName, \$__bladeErrors); endif; ?>");
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
