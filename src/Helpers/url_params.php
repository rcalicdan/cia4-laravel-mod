<?php

/**
 * Helper file for URL parameter management
 *
 * Place this in app/Helpers/url_helper.php or another appropriate location
 */
if (! function_exists('preserve_get_params')) {
    /**
     * Preserve GET parameters and merge with new parameters
     *
     * @param  array  $newParams  New parameters to add or override
     * @param  array  $excludeParams  Parameters to exclude from the result
     * @return string URL query string
     */
    function preserve_get_params(array $newParams = [], array $excludeParams = [])
    {
        $request = Config\Services::request();
        $params = $request->getGet();

        // Remove excluded parameters
        foreach ($excludeParams as $param) {
            unset($params[$param]);
        }

        // Merge with new parameters
        $params = array_merge($params, $newParams);

        return http_build_query($params);
    }
}

if (! function_exists('preserve_post_params')) {
    /**
     * Preserve POST parameters and merge with new parameters
     *
     * @param  array  $newParams  New parameters to add or override
     * @param  array  $excludeParams  Parameters to exclude from the result
     * @return array Merged POST parameters
     */
    function preserve_post_params(array $newParams = [], array $excludeParams = [])
    {
        $request = Config\Services::request();
        $params = $request->getPost();

        // Remove excluded parameters
        foreach ($excludeParams as $param) {
            unset($params[$param]);
        }

        // Merge with new parameters
        return array_merge($params, $newParams);
    }
}

if (! function_exists('url_with_params')) {
    /**
     * Create a URL with preserved GET parameters and new parameters
     *
     * @param  string  $url  Base URL
     * @param  array  $newParams  New parameters to add or override
     * @param  array  $excludeParams  Parameters to exclude from the result
     * @return string Complete URL with parameters
     */
    function url_with_params($url, array $newParams = [], array $excludeParams = [])
    {
        $query = preserve_get_params($newParams, $excludeParams);

        if (! empty($query)) {
            return $url.'?'.$query;
        }

        return $url;
    }
}
