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

        $output = $this->blade->render($this->view, $this->data);
        $this->data = [];

        return $output;
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
