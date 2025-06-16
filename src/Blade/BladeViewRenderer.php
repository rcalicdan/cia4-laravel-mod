<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

/**
 * Blade View Renderer for CodeIgniter 4
 *
 * Provides a fluent interface for rendering Blade templates within CodeIgniter 4,
 * bridging the gap between CodeIgniter's view system and Laravel's Blade templating engine.
 */
class BladeViewRenderer
{
    /**
     * Blade instance
     *
     * @var object
     */
    protected $blade;

    /**
     * View template name
     *
     * @var string
     */
    protected $view;

    /**
     * Data to be passed to the view
     *
     * @var array
     */
    protected $data = [];

    /**
     * Fragment names to render
     *
     * @var array
     */
    protected $fragments = [];

    /**
     * Class constructor
     *
     * Initializes the Blade service instance
     */
    public function __construct()
    {
        $this->blade = service('blade');
    }

    /**
     * Set the view template to render
     *
     * @param  string  $view  Blade template name
     * @return self Returns instance for method chaining
     */
    public function view(string $view)
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Add data to be passed to the view
     *
     * @param  array  $data  Associative array of data
     * @return self Returns instance for method chaining
     */
    public function with(array $data)
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * Set specific fragments to render
     *
     * @param  string|array  $fragments  Fragment name(s) to render
     * @return self Returns instance for method chaining
     */
    public function fragment($fragments)
    {
        $this->fragments = is_array($fragments) ? $fragments : [$fragments];

        return $this;
    }

    /**
     * Set fragments to render based on a condition
     *
     * @param  bool  $condition  The condition to check
     * @param  string|array  $fragments  Fragment name(s) to render if condition is true
     * @param  string|array|null  $fallback  Fragment name(s) to render if condition is false
     * @return self Returns instance for method chaining
     */
    public function fragmentIf(bool $condition, $fragments, $fallback = null)
    {
        if ($condition) {
            $this->fragments = is_array($fragments) ? $fragments : [$fragments];
        } elseif ($fallback !== null) {
            $this->fragments = is_array($fallback) ? $fallback : [$fallback];
        }

        return $this;
    }

    /**
     * Render the Blade template
     *
     * @return string Rendered template output
     *
     * @throws \InvalidArgumentException If no view has been specified
     */
    public function render()
    {
        if (empty($this->view)) {
            throw new \InvalidArgumentException('No view has been specified');
        }

        if (!empty($this->fragments)) {
            $this->data['__fragments'] = $this->fragments;
        }

        $output = $this->blade->render($this->view, $this->data);

        if (!empty($this->fragments)) {
            $output = $this->extractFragments($output, $this->fragments);
        }

        $this->data = [];
        $this->fragments = [];

        return $output;
    }

    /**
     * Extract specific fragments from rendered output
     *
     * @param  string  $output  The full rendered output
     * @param  array  $fragments  Array of fragment names to extract
     * @return string The extracted fragments
     */
    protected function extractFragments(string $output, array $fragments): string
    {
        $extractedContent = '';

        foreach ($fragments as $fragment) {
            $pattern = '/<!--\s*fragment\s*:\s*' . preg_quote($fragment, '/') . '\s*-->(.*?)<!--\s*endfragment\s*:\s*' . preg_quote($fragment, '/') . '\s*-->/s';

            if (preg_match($pattern, $output, $matches)) {
                $extractedContent .= trim($matches[1]);
            }
        }

        return $extractedContent ?: $output;
    }

    /**
     * Magic method for string representation
     *
     * @return string Rendered template output
     */
    public function __toString()
    {
        return $this->render();
    }
}
