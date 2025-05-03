<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Compilers\ComponentTagCompiler;

/**
 * Custom Blade Compiler that safely handles component tags
 */
class CustomBladeCompiler extends BladeCompiler
{
    /**
     * Override the getClassComponentNamespaces method to return an empty array
     * This prevents ComponentTagCompiler from trying to resolve namespaces
     */
    public function getClassComponentNamespaces()
    {
        return [];
    }

    /**
     * Override the componentTagCompiler creation to use our safe version
     */
    protected function newComponentTagCompiler()
    {
        return new SafeComponentTagCompiler(
            $this->getClassComponentAliases(),
            $this->getClassComponentNamespaces(),
            $this
        );
    }
}

/**
 * Safe Component Tag Compiler that doesn't rely on application namespace
 */
class SafeComponentTagCompiler extends ComponentTagCompiler
{
    /**
     * Override guessClassName to avoid using getNamespace()
     */
    public function guessClassName(string $component)
    {
        // Use App namespace directly
        return 'App\\View\\Components\\' . $this->formatClassName($component);
    }
}
