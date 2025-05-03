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
        return !empty($session->getFlashdata());
    }

    /**
     * Generate a simplified cache key focusing on performance.
     *
     * @param string $view The view identifier.
     * @param array $viewData The filtered data being passed to the view.
     * @return string The generated cache key.
     */
    private function generateSimpleCacheKey(string $view, array $viewData): string
    {
        $session = session();

        $prefix = $session->has('auth_user_id')
            ? 'user_' . $session->get('auth_user_id')
            : 'public';

        $keyData = [];
        foreach ($viewData as $key => $value) {
            if (is_scalar($value) || is_null($value)) {
                $keyData[$key] = $value;
            } else {
                // For complex objects/arrays, just use a type indicator and length or count
                $keyData[$key] = gettype($value) . (is_array($value) ? count($value) : '');
            }
        }

        $contentHash = md5($view . json_encode($keyData));
        return $prefix . '_' . $contentHash;
    }

    /**
     * Invalidate all cached views for a specific user.
     * Should be called on password changes, permission updates, etc.
     *
     * @param int|string $userId The user ID to invalidate caches for
     * @return void
     */
    public function invalidateUserCache($userId): void
    {
        if (empty($userId)) {
            return;
        }

        $userPrefix = 'user_' . $userId . '_';
        foreach ($this->viewCache as $key => $value) {
            if (strpos($key, $userPrefix) === 0) {
                unset($this->viewCache[$key]);
            }
        }
    }

    /**
     * Clear user-specific cached views on logout.
     * Should be called from authentication controller's logout method.
     *
     * @return void
     */
    public function clearUserCacheOnLogout(): void
    {
        $session = session();
        $userId = $session->get('auth_user_id');

        if ($userId) {
            $this->invalidateUserCache($userId);
        }
    }

    /**
     * Render a view with Blade, utilizing in-memory caching for improved performance.
     *
     * @param string $view The view identifier in dot notation.
     * @param array $data Additional data to be passed to the view.
     * @param bool $debug Whether to include debug comments in the output.
     * @return string Rendered HTML string.
     */
    public function render(string $view, array $data = [], bool $debug = false): string
    {
        $this->applyExtensions();
        $viewData = $this->prepareViewData($data);

        if ($this->shouldSkipCache()) {
            return $this->renderView($view, $viewData);
        }

        $cacheKey = $this->generateSimpleCacheKey($view, $viewData);

        if (isset($this->viewCache[$cacheKey])) {
            return $debug
                ? $this->viewCache[$cacheKey] . '<!-- Cache hit: ' . $cacheKey . ' -->'
                : $this->viewCache[$cacheKey];
        }

        $result = $this->renderView($view, $viewData);
        $this->viewCache[$cacheKey] = $result;

        return $debug
            ? $result . '<!-- Cache miss: ' . $cacheKey . ' -->'
            : $result;
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
