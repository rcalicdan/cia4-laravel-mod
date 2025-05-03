<?php

/**
 * Component Helper Functions
 * 
 * These helper functions make it easier to work with Blade components
 */

if (!function_exists('component')) {
    /**
     * Render a component with the given data
     *
     * @param string $name Component name
     * @param array $data Component data
     * @return string Rendered component
     */
    function component(string $name, array $data = []): string
    {
        $blade = service('blade');
        return $blade->getComponentManager()->renderComponent($name, $data);
    }
}

if (!function_exists('register_component')) {
    /**
     * Register a component with an alias
     *
     * @param string $alias Component alias
     * @param string $view Component view
     * @return void
     */
    function register_component(string $alias, string $view): void
    {
        $blade = service('blade');
        $blade->component($alias, $view);
    }
}

if (!function_exists('register_components')) {
    /**
     * Register multiple components
     *
     * @param array $components Component mappings (alias => view)
     * @return void
     */
    function register_components(array $components): void
    {
        $blade = service('blade');
        $blade->components($components);
    }
}