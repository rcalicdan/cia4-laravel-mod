<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Pagination\Paginator;
use Rcalicdan\Blade\Blade;
use Rcalicdan\Blade\Container as BladeContainer;
use Rcalicdan\Ci4Larabridge\Config\Blade as ConfigBlade;

/**
 * BladeService provides a high-performance implementation of the Blade templating engine for CodeIgniter 4.
 * 
 * This service handles view rendering, caching, and performance optimizations to ensure
 * minimal resource usage while providing the full power of Blade templates.
 */
class BladeService
{
    /**
     * @var Blade Instance of the Blade engine
     */
    protected Blade $blade;

    /**
     * @var array Configuration for Blade
     */
    protected array $config;

    /**
     * @var ConfigBlade Configuration values for Blade
     */
    protected $bladeConfigValues;

    /**
     * @var BladeExtension Instance of BladeExtension
     */
    protected $bladeExtension;

    /**
     * @var array Data to be passed to the view
     */
    protected array $viewData = [];

    /**
     * @var array Cached rendered views for improved performance
     */
    protected array $viewCache = [];

    /**
     * @var array Cache for file existence checks to reduce I/O operations
     */
    protected array $fileExistsCache = [];

    /**
     * @var bool Flag indicating whether extensions have been loaded
     */
    protected bool $extensionsLoaded = false;

    /**
     * Initialize the BladeService with configuration
     */
    public function __construct()
    {
        $this->bladeConfigValues = config('Blade');
        $this->bladeExtension = new BladeExtension();
        $this->config = [
            'viewsPath' => $this->bladeConfigValues->viewsPath,
            'cachePath' => $this->bladeConfigValues->cachePath,
            'componentNamespace' => $this->bladeConfigValues->componentNamespace,
            'componentPath' => $this->bladeConfigValues->componentPath,
            'checksCompilationInProduction' => $this->bladeConfigValues->checksCompilationInProduction ?? false,
        ];

        $this->initialize();
    }

    /**
     * Initialize the Blade engine with performance optimizations
     */
    protected function initialize(): void
    {
        $this->ensureCacheDirectory();

        $container = new BladeContainer();

        $this->blade = new Blade(
            $this->config['viewsPath'],
            $this->config['cachePath'],
            $container
        );

        if (ENVIRONMENT === 'production') {
            try {
                $this->blade->getCompiler()->setIsExpired(function (): bool {
                    return false; // Never recompile in production for optimal performance
                });
            } catch (\Exception $e) {
                log_message('warning', 'Unable to set compiler expiration check: ' . $e->getMessage());
            }
        }

        $this->blade->addNamespace(
            $this->config['componentNamespace'],
            $this->config['componentPath']
        );
    }

    /**
     * Ensure the cache directory exists and is writable
     */
    protected function ensureCacheDirectory(): void
    {
        $cachePath = $this->config['cachePath'];

        if (!isset($this->fileExistsCache[$cachePath]) || !$this->fileExistsCache[$cachePath]) {
            $this->fileExistsCache[$cachePath] = is_dir($cachePath);

            if (!$this->fileExistsCache[$cachePath]) {
                mkdir($cachePath, 0777, true);
                $this->fileExistsCache[$cachePath] = true;
            }
        }

        if (!$this->checkFileWritable($cachePath)) {
            log_message('error', "Blade cache path is not writable: {$cachePath}");
        }
    }

    /**
     * Check if a file or directory is writable with caching
     * 
     * @param string $path Path to check
     * @return bool Whether the path is writable
     */
    protected function checkFileWritable(string $path): bool
    {
        $cacheKey = "writable:{$path}";

        if (!isset($this->fileExistsCache[$cacheKey])) {
            $this->fileExistsCache[$cacheKey] = is_writable($path);
        }

        return $this->fileExistsCache[$cacheKey];
    }

    /**
     * Apply Blade extensions and customizations with lazy loading
     */
    protected function applyExtensions(): void
    {
        if ($this->extensionsLoaded) {
            return;
        }

        if (!class_exists(BladeExtension::class)) {
            log_message('warning', 'BladeExtension class not found. Custom directives are disabled.');
            $this->extensionsLoaded = true;
            return;
        }

        if (method_exists($this->bladeExtension, 'registerDirectives')) {
            $this->bladeExtension->registerDirectives($this->blade);
        }

        if (method_exists($this->bladeConfigValues, 'registerCustomDirectives')) {
            $this->bladeConfigValues->registerCustomDirectives($this->blade);
        }

        $this->extensionsLoaded = true;
    }

    /**
     * Process view data with extensions
     *
     * @param array $data The view data to process
     * @return array Processed view data
     */
    public function processData(array $data): array
    {
        if (!class_exists(BladeExtension::class)) {
            return $data;
        }

        if (method_exists($this->bladeExtension, 'processData')) {
            return $this->bladeExtension->processData($data);
        }

        return $data;
    }

    /**
     * Filter internal keys from view data
     *
     * @param array $data The view data to filter
     * @return array Filtered view data
     */
    public function filterInternalKeys(array $data): array
    {
        $internalKeys = [
            '__componentPath',
            '__componentAttributes',
            '__componentData',
            '__componentSlot',
            '__currentSlot',
            'blade',
            'bladeExtension',
            'viewsPath',
            'cachePath',
            'componentNamespace',
            'componentPath',
            'internalKeys',
            'filteredData',
            'render',
            'view',
            'data',
        ];

        return array_filter($data, fn($key) => !in_array($key, $internalKeys), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Set data to be passed to the view
     * 
     * @param array $data Data to be passed to the view
     * @return self Returns the current instance for method chaining
     */
    public function setData(array $data = []): self
    {
        $this->viewData = $this->processData($data);
        return $this;
    }

    /**
     * Process and filter data before passing it to the view or cache key generation.
     *
     * @param array $data Raw data passed to the render method.
     * @return array Processed and filtered data suitable for the view.
     */
    private function prepareViewData(array $data): array
    {
        $mergedData = array_merge($this->viewData ?? [], $data);
        $processedData = $this->processData($mergedData);
        return $this->filterInternalKeys($processedData);
    }

    /**
     * Check if caching should be skipped for the current request.
     * Currently skips if flash data exists in the session.
     *
     * @return bool True if caching should be skipped, false otherwise.
     */
    private function shouldSkipCache(): bool
    {
        $session = session();
        // Skip caching for views with flash data as it's transient
        return !empty($session->getFlashdata());
    }

    /**
     * Generate a unique cache key for the view and its data.
     * Includes user context if a user is logged in.
     *
     * @param string $view The view identifier.
     * @param array $viewData The filtered data being passed to the view.
     * @return string The generated cache key.
     */
    private function generateCacheKey(string $view, array $viewData): string
    {
        $session = session();
        $hasUserContext = $session->has('user_id') || $session->has('logged_in');
        $userSuffix = $hasUserContext ? '_user_' . ($session->get('user_id') ?? 'guest') : '';
        return md5($view . $userSuffix . serialize($viewData));
    }

    /**
     * Attempt to retrieve the rendered view output from the cache.
     * Checks in-memory cache first, then persistent cache if enabled.
     *
     * @param string $cacheKey The cache key to look up.
     * @return string|null The cached HTML output, or null if not found.
     */
    private function getFromCache(string $cacheKey): ?string
    {
        $bladeConfig = $this->bladeConfigValues;

        // Check in-memory cache first
        if ($bladeConfig->useInMemoryCache && isset($this->viewCache[$cacheKey])) {
            return $this->viewCache[$cacheKey];
        }

        // Check persistent cache if enabled and in production
        if ($bladeConfig->usePersistentCache && ENVIRONMENT === 'production') {
            $persistentCache = \Config\Services::cache();
            $cachedOutput = $persistentCache->get('view_' . $cacheKey);

            if ($cachedOutput !== null) {
                // Store in memory cache too if retrieved from persistent and in-memory is enabled
                if ($bladeConfig->useInMemoryCache) {
                    $this->viewCache[$cacheKey] = $cachedOutput;
                }
                return $cachedOutput;
            }
        }

        return null;
    }

    /**
     * Store the rendered view output in the configured caches.
     *
     * @param string $cacheKey The cache key.
     * @param string $output The rendered HTML output to store.
     * @param array $viewData The data used, needed to determine user context for duration.
     * @return void
     */
    private function storeInCache(string $cacheKey, string $output, array $viewData): void
    {
        $bladeConfig = $this->bladeConfigValues;

        if ($bladeConfig->useInMemoryCache) {
            $this->viewCache[$cacheKey] = $output;
        }

        if ($bladeConfig->usePersistentCache && ENVIRONMENT === 'production') {
            $session = session();
            $hasUserContext = $session->has('user_id') || $session->has('logged_in');
            $cacheDuration = $hasUserContext
                ? $bladeConfig->userViewCacheDuration
                : $bladeConfig->publicViewCacheDuration;

            $persistentCache = \Config\Services::cache();
            $persistentCache->save('view_' . $cacheKey, $output, $cacheDuration);
        }
    }

    /**
     * Render a view with Blade, utilizing caching for improved performance.
     *
     * @param string $view The view identifier in dot notation.
     * @param array $data Additional data to be passed to the view.
     * @return string Rendered HTML string.
     */
    public function render(string $view, array $data = []): string
    {
        $this->applyExtensions();
        $viewData = $this->prepareViewData($data);

        if ($this->shouldSkipCache()) {
            return $this->renderView($view, $viewData);
        }

        $cacheKey = $this->generateCacheKey($view, $viewData);
        $cachedOutput = $this->getFromCache($cacheKey);

        if ($cachedOutput !== null) {
            return $cachedOutput;
        }

        $result = $this->renderView($view, $viewData);
        $this->storeInCache($cacheKey, $result, $viewData);

        return $result;
    }

    /**
     * Helper method for view rendering, handling potential errors.
     * Resets instance view data after rendering.
     *
     * @param string $view The view identifier.
     * @param array $data The data to pass to the view.
     * @return string Rendered HTML string or an error placeholder.
     * @throws \Throwable Re-throws rendering exceptions in non-production environments.
     */
    private function renderView(string $view, array $data): string
    {
        try {
            $result = $this->blade->make($view, $data)->render();
            return $result;
        } catch (\Throwable $e) {
            if (ENVIRONMENT === 'production') {
                log_message('error', "Blade rendering error in view [{$view}]: {$e->getMessage()}");
            } else {
                log_message('error', "Blade rendering error in view [{$view}]: {$e->getMessage()}\n{$e->getTraceAsString()}");
                throw $e;
            }
            return '<!-- View Rendering Error -->';
        } finally {
            $this->viewData = [];
        }
    }

    /**
     * Get the Blade instance
     *
     * @return Blade The Blade engine instance
     */
    public function getBlade(): Blade
    {
        return $this->blade;
    }

    /**
     * Compiles all blade views for improved application startup performance
     *
     * @param bool $force Force recompilation
     * @return array Compilation results
     */
    public function compileViews(bool $force = false): array
    {
        $compiler = $this->blade->getCompiler();
        $viewsPath = $this->config['viewsPath'];
        $files = $this->getBladeFiles($viewsPath);

        $results = [];
        foreach ($files as $file) {
            $relativePath = str_replace($viewsPath . '/', '', $file);
            $viewName = str_replace('.blade.php', '', $relativePath);
            $viewName = str_replace('/', '.', $viewName);

            try {
                if ($force || !$compiler->isExpired($file)) {
                    $compiler->compile($file);
                }
                $results[$viewName] = true;
            } catch (\Exception $e) {
                $results[$viewName] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Precompiles all views to optimize performance after deployment
     *
     * @return array Results of the precompilation
     */
    public function precompileAllViews(): array
    {
        $results = $this->compileViews(true);

        foreach ($results as $view => $status) {
            if ($status !== true) {
                log_message('error', "Failed to precompile view {$view}: {$status}");
            }
        }

        return $results;
    }

    /**
     * Get all Blade template files recursively with caching
     * 
     * @param string $directory The directory to search
     * @return array List of Blade template files
     */
    protected function getBladeFiles(string $directory): array
    {
        $cacheKey = "bladeFiles:{$directory}";

        if (ENVIRONMENT === 'production' && isset($this->fileExistsCache[$cacheKey])) {
            return $this->fileExistsCache[$cacheKey];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if (
                $file->isFile() &&
                (str_ends_with($file->getPathname(), '.blade.php'))
            ) {
                $files[] = $file->getPathname();
            }
        }

        if (ENVIRONMENT === 'production') {
            $this->fileExistsCache[$cacheKey] = $files;
        }

        return $files;
    }
}
