<?php

namespace Rcalicdan\Ci4Larabridge\Facades;

use Rcalicdan\Ci4Larabridge\Vite\Vite as ViteRoot;

class Vite
{
    /**
     * The Vite instance.
     *
     * @var ViteRoot|null
     */
    protected static $instance = null;

    /**
     * Get the Vite instance.
     *
     * @return ViteRoot
     */
    protected static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new ViteRoot;
        }

        return static::$instance;
    }

    /**
     * Set a custom Vite instance.
     *
     * @param  ViteRoot  $instance
     * @return void
     */
    public static function setInstance(Vite $instance)
    {
        static::$instance = $instance;
    }

    /**
     * Clear the instance (useful for testing).
     *
     * @return void
     */
    public static function clearInstance()
    {
        static::$instance = null;
    }

    /**
     * Generate Vite tags for entrypoints.
     *
     * @param  string|string[]  $entrypoints
     * @param  string|null  $buildDirectory
     * @return \Illuminate\Support\HtmlString
     */
    public function __invoke($entrypoints, $buildDirectory = null)
    {
        return static::getInstance()($entrypoints, $buildDirectory);
    }

    /**
     * Generate Vite tags for entrypoints.
     *
     * @param  string|string[]  $entrypoints
     * @param  string|null  $buildDirectory
     * @return \Illuminate\Support\HtmlString
     */
    public static function make($entrypoints, $buildDirectory = null)
    {
        return static::getInstance()($entrypoints, $buildDirectory);
    }

    /**
     * Get the preloaded assets.
     *
     * @return array
     */
    public static function preloadedAssets()
    {
        return static::getInstance()->preloadedAssets();
    }

    /**
     * Get the Content Security Policy nonce applied to all generated tags.
     *
     * @return string|null
     */
    public static function cspNonce()
    {
        return static::getInstance()->cspNonce();
    }

    /**
     * Generate or set a Content Security Policy nonce to apply to all generated tags.
     *
     * @param  string|null  $nonce
     * @return string
     */
    public static function useCspNonce($nonce = null)
    {
        return static::getInstance()->useCspNonce($nonce);
    }

    /**
     * Use the given key to detect integrity hashes in the manifest.
     *
     * @param  string|false  $key
     * @return ViteRoot
     */
    public static function useIntegrityKey($key)
    {
        return static::getInstance()->useIntegrityKey($key);
    }

    /**
     * Set the Vite entry points.
     *
     * @param  array  $entryPoints
     * @return ViteRoot
     */
    public static function withEntryPoints($entryPoints)
    {
        return static::getInstance()->withEntryPoints($entryPoints);
    }

    /**
     * Merge additional Vite entry points with the current set.
     *
     * @param  array  $entryPoints
     * @return ViteRoot
     */
    public static function mergeEntryPoints($entryPoints)
    {
        return static::getInstance()->mergeEntryPoints($entryPoints);
    }

    /**
     * Set the filename for the manifest file.
     *
     * @param  string  $filename
     * @return ViteRoot
     */
    public static function useManifestFilename($filename)
    {
        return static::getInstance()->useManifestFilename($filename);
    }

    /**
     * Resolve asset paths using the provided resolver.
     *
     * @param  callable|null  $resolver
     * @return ViteRoot
     */
    public static function createAssetPathsUsing($resolver)
    {
        return static::getInstance()->createAssetPathsUsing($resolver);
    }

    /**
     * Get the Vite "hot" file path.
     *
     * @return string
     */
    public static function hotFile()
    {
        return static::getInstance()->hotFile();
    }

    /**
     * Set the Vite "hot" file path.
     *
     * @param  string  $path
     * @return ViteRoot
     */
    public static function useHotFile($path)
    {
        return static::getInstance()->useHotFile($path);
    }

    /**
     * Set the Vite build directory.
     *
     * @param  string  $path
     * @return ViteRoot
     */
    public static function useBuildDirectory($path)
    {
        return static::getInstance()->useBuildDirectory($path);
    }

    /**
     * Use the given callback to resolve attributes for script tags.
     *
     * @param  (callable(string, string, ?array, ?array): array)|array  $attributes
     * @return ViteRoot
     */
    public static function useScriptTagAttributes($attributes)
    {
        return static::getInstance()->useScriptTagAttributes($attributes);
    }

    /**
     * Use the given callback to resolve attributes for style tags.
     *
     * @param  (callable(string, string, ?array, ?array): array)|array  $attributes
     * @return ViteRoot
     */
    public static function useStyleTagAttributes($attributes)
    {
        return static::getInstance()->useStyleTagAttributes($attributes);
    }

    /**
     * Use the given callback to resolve attributes for preload tags.
     *
     * @param  (callable(string, string, ?array, ?array): (array|false))|array|false  $attributes
     * @return ViteRoot
     */
    public static function usePreloadTagAttributes($attributes)
    {
        return static::getInstance()->usePreloadTagAttributes($attributes);
    }

    /**
     * Eagerly prefetch assets.
     *
     * @param  int|null  $concurrency
     * @param  string  $event
     * @return ViteRoot
     */
    public static function prefetch($concurrency = null, $event = 'load')
    {
        return static::getInstance()->prefetch($concurrency, $event);
    }

    /**
     * Use the "waterfall" prefetching strategy.
     *
     * @param  int|null  $concurrency
     * @return ViteRoot
     */
    public static function useWaterfallPrefetching($concurrency = null)
    {
        return static::getInstance()->useWaterfallPrefetching($concurrency);
    }

    /**
     * Use the "aggressive" prefetching strategy.
     *
     * @return ViteRoot
     */
    public static function useAggressivePrefetching()
    {
        return static::getInstance()->useAggressivePrefetching();
    }

    /**
     * Set the prefetching strategy.
     *
     * @param  'waterfall'|'aggressive'|null  $strategy
     * @param  array  $config
     * @return ViteRoot
     */
    public static function usePrefetchStrategy($strategy, $config = [])
    {
        return static::getInstance()->usePrefetchStrategy($strategy, $config);
    }

    /**
     * Generate React refresh runtime script.
     *
     * @return \Illuminate\Support\HtmlString|void
     */
    public static function reactRefresh()
    {
        return static::getInstance()->reactRefresh();
    }

    /**
     * Get the URL for an asset.
     *
     * @param  string  $asset
     * @param  string|null  $buildDirectory
     * @return string
     */
    public static function asset($asset, $buildDirectory = null)
    {
        return static::getInstance()->asset($asset, $buildDirectory);
    }

    /**
     * Get the content of a given asset.
     *
     * @param  string  $asset
     * @param  string|null  $buildDirectory
     * @return string
     */
    public static function content($asset, $buildDirectory = null)
    {
        return static::getInstance()->content($asset, $buildDirectory);
    }

    /**
     * Get a unique hash representing the current manifest, or null if there is no manifest.
     *
     * @param  string|null  $buildDirectory
     * @return string|null
     */
    public static function manifestHash($buildDirectory = null)
    {
        return static::getInstance()->manifestHash($buildDirectory);
    }

    /**
     * Determine if the HMR server is running.
     *
     * @return bool
     */
    public static function isRunningHot()
    {
        return static::getInstance()->isRunningHot();
    }

    /**
     * Get the Vite tag content as a string of HTML.
     *
     * @return string
     */
    public static function toHtml()
    {
        return static::getInstance()->toHtml();
    }

    /**
     * Flush state.
     *
     * @return void
     */
    public static function flush()
    {
        static::getInstance()->flush();
    }

    /**
     * Handle dynamic static method calls.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return static::getInstance()->$method(...$args);
    }
}
