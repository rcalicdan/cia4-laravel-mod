<?php

if (! function_exists('asset')) {
    /**
     * Generate an asset URL for the application.
     *
     * @param  string  $path
     */
    function asset($path): string
    {
        $path = ltrim($path, '/');

        return base_url($path);
    }
}

if (! function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param  string  $path
     */
    function public_path($path = ''): string
    {
        $path = ltrim($path, '/');

        return FCPATH.$path;
    }
}

if (!function_exists('base_path')) {
    function base_path($path = '') {
        return ROOTPATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('database_path')) {
    function database_path($path = '') {
        return WRITEPATH . 'database' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path($path = '') {
        return WRITEPATH . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}
