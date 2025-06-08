<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Rcalicdan\Blade\Blade;
use Rcalicdan\Ci4Larabridge\Providers\ComponentDirectiveProvider;

/**
 * Provides high-performance CodeIgniter-specific integrations for the Blade templating engine.
 *
 * This class handles preprocessing of view data (like pagination and error handling)
 * and registers optimized Blade directives commonly used in CI4/Laravel development,
 * including delegating component directive registration.
 */
class BladeExtension
{
    /**
     * Directive method mapping for optimized directive registration
     */
    protected array $methodMap = [
        'delete' => 'DELETE',
        'put' => 'PUT',
        'patch' => 'PATCH',
    ];

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
        $this->_registerLangDirectives($blade);
        $this->_registerViteDirectives($blade);

        $componentProvider = new ComponentDirectiveProvider;
        $componentProvider->register($blade);
    }

    // ======================================================================
    // Data Processing Helpers (Called internally by processData)
    // ======================================================================

    /**
     * Adds a simple error handling object, mimicking Laravel's `$errors` variable.
     * Retrieves validation errors from the session ('errors' key) and makes them
     * accessible in the view via an object with methods for error handling.
     *
     * @param  array  $data  The view data array.
     * @return array The data array, potentially with an `$errors` object added.
     */
    /**
     * Adds a simple error handling object, mimicking Laravel's `$errors` variable.
     * Retrieves validation errors from the session ('errors' key) and makes them
     * accessible in the view.
     *
     * @param  array  $data  The view data array.
     * @return array The data array, potentially with an `$errors` object added.
     */
    protected function addErrorsHandler(array $data): array
    {
        $sessionErrors = session('errors');
        if (! isset($data['errors']) && ! empty($sessionErrors) && is_array($sessionErrors)) {
            $data['errors'] = new ErrorBag($sessionErrors);
        }

        return $data;
    }

    // ======================================================================
    // Directive Registrars (Called internally by registerDirectives)
    // ======================================================================

    /**
     * Registers optimized directives for HTTP method spoofing in forms.
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

        foreach ($this->methodMap as $directive => $method) {
            $blade->directive($directive, fn () => "<input type=\"hidden\" name=\"_method\" value=\"{$method}\">");
        }
    }

    /**
     * Registers directives for simple permission checks.
     * Provides @can(...) and @cannot(...).
     *
     * @param  Blade  $blade  The Blade compiler instance.
     */
    private function _registerPermissionDirectives(Blade $blade): void
    {
        $blade->directive('can', fn ($expression) => "<?php if(can($expression)): ?>");
        $blade->directive('endcan', fn () => '<?php endif; ?>');
        $blade->directive('cannot', fn ($expression) => "<?php if(cannot($expression)): ?>");
        $blade->directive('endcannot', fn () => '<?php endif; ?>');
    }

    /**
     * Registers optimized authentication directives.
     *
     * @param  Blade  $blade  The Blade compiler instance.
     */
    private function _registerAuthDirectives(Blade $blade): void
    {
        $blade->directive('auth', fn () => '<?php if(auth()->check()):?>');
        $blade->directive('endauth', fn () => '<?php endif;?>');
        $blade->directive('guest', fn () => '<?php if(auth()->guest()):?>');
        $blade->directive('endguest', fn () => '<?php endif;?>');
    }

    /**
     * Registers directives for displaying validation errors.
     * Provides @error('field_name') ... @enderror.
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
        $blade->directive('enderror', fn () => '<?php unset($message, $__fieldName, $__bladeErrors); endif; ?>');
    }

    /**
     * Registers optimized back directives for navigation.
     *
     * @param  Blade  $blade  The Blade compiler instance.
     */
    private function _registerBackDirectives(Blade $blade): void
    {
        $blade->directive('back', function ($expression) {
            $expression = trim($expression, "()'\"");
            $default = $expression ? ", $expression" : '';

            return "<?php echo '<input type=\"hidden\" name=\"back\" value=\"'.e(back_url($default)).'\">'; ?>";
        });
    }

    private function _registerLangDirectives(Blade $blade): void
    {
        $blade->directive('lang', function ($expression) {
            return "<?php echo lang({$expression});?>";
        });
    }

    private function _registerViteDirectives(Blade $blade): void
    {
        $blade->directive('vite', function ($expression) {
            $expression = trim($expression, '()');

            return "<?php echo \\Rcalicdan\\Ci4Larabridge\\Facades\\Vite::make({$expression}); ?>";
        });

        $blade->directive('viteReactRefresh', function () {
            return '<?php echo \\Rcalicdan\\Ci4Larabridge\\Facades\\Vite::reactRefresh(); ?>';
        });

        $blade->directive('viteAsset', function ($expression) {
            $expression = trim($expression, '()');

            return "<?php echo \\Rcalicdan\\Ci4Larabridge\\Facades\\Vite::asset({$expression}); ?>";
        });
    }
}
