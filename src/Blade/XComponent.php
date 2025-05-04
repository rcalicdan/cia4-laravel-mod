<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\View\Component as BaseComponent;

abstract class XComponent extends BaseComponent
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Initialize component
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    abstract public function render();
    
    /**
     * Determine if the component should be rendered.
     *
     * @return bool
     */
    public function shouldRender()
    {
        return true;
    }
}