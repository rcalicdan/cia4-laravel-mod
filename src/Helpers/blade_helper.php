<?php

/**
 * Renders a view using the Blade templating engine
 *
 * Provides a fluent interface for rendering Blade templates within CodeIgniter.
 * The function maintains a singleton instance of BladeViewRenderer for efficiency.
 *
 * @param string|null $view The view identifier in dot notation (e.g. 'pages.users.index')
 *                          When null, returns the renderer instance for method chaining.
 * @param array $data Associative array of data to pass to the view
 *                    Defaults to empty array if not provided.
 *
 * @return BladeViewRenderer|string Returns:
 *   - BladeViewRenderer instance when $view is null (for method chaining)
 *   - Rendered HTML string when view is specified
 *
 * @throws \Throwable Propagates template rendering exceptions with stack trace
 *                    in development environment for debugging purposes.
 *
 * @example
 * // Basic usage
 * echo blade_view('template.name', ['key' => 'value']);
 *
 * // Method chaining
 * blade_view()->view('template')->with(['key' => 'value'])->render();
 */

use Rcalicdan\Ci4Larabridge\Blade\BladeViewRenderer;

if (! function_exists('blade_view')) {
    function blade_view(?string $view = null, array $data = [])
    {
        static $renderer = null;

        if ($renderer === null) {
            $renderer = new BladeViewRenderer;
        }

        $instance = $renderer;

        if ($view !== null) {
            $instance->view($view);

            if (! empty($data)) {
                $instance->with($data);
            }
        }

        return $instance;
    }
}
