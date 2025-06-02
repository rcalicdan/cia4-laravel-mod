<?php

if (!function_exists('asset')) {
    /**
     * Generate an asset URL for the application.
     *
     * @param string $path
     * @return string
     */
    function asset($path): string
    {
        $path = ltrim($path, '/');
        return base_url($path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param string $path
     * @return string
     */
    function public_path($path = ''): string
    {
        $path = ltrim($path, '/');
        return FCPATH . $path;
    }
}
