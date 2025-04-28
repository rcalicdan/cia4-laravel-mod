<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Pagination\Paginator;
use Rcalicdan\Blade\Blade;
use Rcalicdan\Blade\Container as BladeContainer;
use Rcalicdan\Ci4Larabridge\Config\Blade as ConfigBlade;

class BladeService
{
    /**
     * @var Blade|null Instance of the Blade engine
     */
    protected ?Blade $blade = null;

    /**
     * @var array Configuration for Blade
     */
    protected array $config = [];

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
     * @var array Cache for internal processing keys
     */
    protected static array $internalKeys = [
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

    /**
     * @var array Cache for compiled view paths
     */
    protected static array $compiledViewCache = [];

    /**
     * @var array Static cache for config values
     */
    protected static array $configCache = [];

    /**
     * Initialize the BladeService with configuration
     */
    public function __construct()
    {
        $this->bladeConfigValues = $this->getConfig('Blade');
        $this->bladeExtension = new BladeExtension;
        $this->config = [
            'viewsPath' => $this->bladeConfigValues->viewsPath,
            'cachePath' => $this->bladeConfigValues->cachePath,
            'componentNamespace' => $this->bladeConfigValues->componentNamespace,
            'componentPath' => $this->bladeConfigValues->componentPath,
            'checksCompilationInProduction' => $this->bladeConfigValues->checksCompilationInProduction ?? false,
        ];

        // Ensure cache directory is created but don't initialize engine yet
        $this->ensureCacheDirectory();
    }

    /**
     * Get a config value with caching
     * 
     * @param string $key The config key to retrieve
     * @return mixed The config value
     */
    protected function getConfig(string $key)
    {
        if (!isset(self::$configCache[$key])) {
            self::$configCache[$key] = config($key);
        }
        return self::$configCache[$key];
    }

    /**
     * Ensure the cache directory exists and is writable
     */
    protected function ensureCacheDirectory(): void
    {
        static $checked = false;
        
        if ($checked) return;
        
        $cachePath = $this->config['cachePath'];

        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }

        if (! is_writable($cachePath)) {
            log_message('error', "Blade cache path is not writable: {$cachePath}");
        }
        
        $checked = true;
    }

    /**
     * Initialize the Blade engine (lazy loading)
     */
    protected function initialize(): void
    {
        if ($this->blade !== null) {
            return; // Already initialized
        }

        $container = new BladeContainer;

        $this->blade = new Blade(
            $this->config['viewsPath'],
            $this->config['cachePath'],
            $container
        );

        if (ENVIRONMENT === 'production') {
            try {
                $this->blade->getCompiler()->setIsExpired(function (): bool {
                    return $this->config['checksCompilationInProduction'];
                });
            } catch (\Exception $e) {
                log_message('warning', 'Unable to set compiler expiration check: ' . $e->getMessage());
            }
        }

        $this->blade->addNamespace(
            $this->config['componentNamespace'],
            $this->config['componentPath']
        );

        $this->applyExtensions();
    }

    /**
     * Apply Blade extensions and customizations
     */
    protected function applyExtensions(): void
    {
        if (! class_exists(BladeExtension::class)) {
            log_message('warning', 'BladeExtension class not found. Custom directives are disabled.');
            return;
        }

        if (method_exists($this->bladeExtension, 'registerDirectives')) {
            $this->bladeExtension->registerDirectives($this->blade);
        }

        if (method_exists($this->bladeConfigValues, 'registerCustomDirectives')) {
            $this->bladeConfigValues->registerCustomDirectives($this->blade);
        }
    }

    /**
     * Process view data with extensions and filter internal keys in one pass
     *
     * @param array $data The view data to process
     * @return array Processed and filtered view data
     */
    public function processViewData(array $data): array
    {
        // First process data with extensions if available
        if (class_exists(BladeExtension::class) && method_exists($this->bladeExtension, 'processData')) {
            $data = $this->bladeExtension->processData($data);
        }
        
        // Then filter internal keys
        return array_filter($data, fn($key) => !in_array($key, self::$internalKeys), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Set data to be passed to the view
     * 
     * @param array $data Data to be passed to the view
     * @return self Returns the current instance for method chaining
     */
    public function setData(array $data = []): self
    {
        $this->viewData = $data; // Store raw data, process only when needed
        return $this;
    }

    /**
     * Get view cache key
     *
     * @param string $view View name
     * @param array $data View data
     * @return string Cache key
     */
    protected function getViewCacheKey(string $view, array $data): string
    {
        return "blade_view_{$view}_" . md5(serialize($data));
    }

    /**
     * Get the compiled view path
     *
     * @param string $view View name
     * @return string Compiled view path
     */
    protected function getCompiledPath(string $view): string
    {
        if (!isset(self::$compiledViewCache[$view])) {
            $viewPath = str_replace('.', '/', $view);
            self::$compiledViewCache[$view] = $this->config['viewsPath'] . '/' . $viewPath . '.blade.php';
        }
        
        return self::$compiledViewCache[$view];
    }

    /**
     * Render a view with Blade
     *
     * @param string $view The view identifier in dot notation
     * @param array $data Additional data to be passed to the view
     * @return string Rendered HTML string
     *
     * @throws \Throwable Rendering exceptions in non-production environments
     */
    public function render(string $view, array $data = []): string
    {
        try {
            // Initialize Blade if not already done
            if ($this->blade === null) {
                $this->initialize();
            }

            // Check if we have cached output for this view in production
            if (ENVIRONMENT === 'production' && function_exists('cache') && !$this->config['checksCompilationInProduction']) {
                $cacheKey = $this->getViewCacheKey($view, array_merge($this->viewData, $data));
                $cached = cache()->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Merge and process view data in one step
            $mergedData = array_merge($this->viewData ?? [], $data);
            $processedData = $this->processViewData($mergedData);

            // Render the view
            $output = $this->blade->make($view, $processedData)->render();

            // Cache the output in production
            if (ENVIRONMENT === 'production' && function_exists('cache') && !$this->config['checksCompilationInProduction']) {
                $cacheKey = $this->getViewCacheKey($view, array_merge($this->viewData, $data));
                cache()->save($cacheKey, $output, 3600); // Cache for 1 hour
            }

            return $output;
        } catch (\Throwable $e) {
            log_message('error', "Blade rendering error in view [{$view}]: {$e->getMessage()}\n{$e->getTraceAsString()}");

            if (ENVIRONMENT !== 'production') {
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
        if ($this->blade === null) {
            $this->initialize();
        }
        return $this->blade;
    }

    /**
     * Precompile all Blade templates - ideal for deployment scripts
     *
     * @param bool $force Force recompilation
     * @return array Compilation results
     */
    public function compileViews(bool $force = false): array
    {
        if ($this->blade === null) {
            $this->initialize();
        }

        $filesystem = new \Illuminate\Filesystem\Filesystem;
        $compiler = $this->blade->getCompiler();

        // Get all .blade.php files
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
     * Get all Blade template files recursively
     * 
     * @param string $directory Directory to scan
     * @return array List of Blade template files
     */
    protected function getBladeFiles(string $directory): array
    {
        static $fileCache = [];
        
        if (!empty($fileCache[$directory])) {
            return $fileCache[$directory];
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
        
        $fileCache[$directory] = $files;
        
        return $files;
    }
    
    /**
     * Precompile all views to improve initial request performance
     * 
     * @param bool $force Force recompilation
     * @return void
     */
    public function precompileAllViews(bool $force = false): void
    {
        if (ENVIRONMENT === 'production') {
            $this->compileViews($force);
        }
    }
}