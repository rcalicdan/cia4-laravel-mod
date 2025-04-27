<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

/**
 * PaginationRenderer - Handles rendering of pagination views using Blade templating
 *
 * This class provides methods to render pagination views using the Blade template engine.
 */
class PaginationRenderer
{
    /**
     * @var string The view name to render
     */
    protected $view;

    /**
     * @var mixed The Blade view bridge instance
     */
    protected $viewBridge;

    /**
     * @var array The data to pass to the view
     */
    protected $data = [];

    /**
     * @var array Data to be passed to the view
     */
    protected array $viewData = [];

    /**
     * Constructor - Initializes the PaginationRenderer
     *
     * Sets up the Blade view bridge and adds the pagination namespace
     */
    public function __construct()
    {
        $this->viewBridge = service('blade');
        $paginationPath = is_dir(APPPATH.'Views/pagination')
            ? APPPATH.'Views/pagination'
            : __DIR__.'/../Views/pagination';
        $this->viewBridge->getBlade()->addNamespace('pagination', $paginationPath);
    }

    /**
     * make - Sets the view and data for rendering
     *
     * @param  string  $view  The view name to render
     * @param  array  $data  The data to pass to the view
     * @return $this
     */
    public function make($view, $data = [])
    {
        $this->view = $view;
        $this->data = $data;

        return $this;
    }

    /**
     * setData - Updates the view data
     *
     * @param  array  $data  The data to pass to the view
     * @return $this
     */
    public function setData($data = [])
    {
        $this->data = $data;

        return $this;
    }

    /**
     * render - Renders the view with the current data
     *
     * @return string The rendered view content
     */
    public function render()
    {
        return $this->viewBridge->setData($this->data)->render($this->view);
    }

    /**
     * __toString - Magic method to render the view when object is treated as string
     *
     * @return string The rendered view content
     */
    public function __toString()
    {
        return $this->render();
    }
}
