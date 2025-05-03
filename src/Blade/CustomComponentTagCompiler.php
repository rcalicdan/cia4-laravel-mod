<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\View\Compilers\ComponentTagCompiler as BaseComponentTagCompiler;

class CustomComponentTagCompiler extends BaseComponentTagCompiler
{
    /**
     * Override the original guessClassName to avoid using getNamespace()
     */
    public function guessClassName(string $component)
    {
        // Set a fixed namespace for components instead of using the application's namespace
        $namespace = 'App\\';
        $class = $this->formatClassName($component);

        return $namespace.'View\\Components\\'.$class;
    }
}