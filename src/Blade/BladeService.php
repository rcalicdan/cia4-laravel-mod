<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Container\Container;
use Rcalicdan\Blade\Blade;
use Illuminate\View\Component;
use Rcalicdan\Blade\Container as BladeContainer;
use Rcalicdan\Ci4Larabridge\Config\Blade as ConfigBlade;

class BladeService
{
    /**
     * @var Blade Instance of the Blade engine
     */
    protected $blade;

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
        $this->bladeExtension = new BladeExtension;
        $this->config = [
            'viewsPath' => $this->bladeConfigValues->viewsPath,
            'cachePath' => $this->bladeConfigValues->cachePath,
            'componentNamespace' => $this->bladeConfigValues->componentNamespace,
            'componentPath' => $this->bladeConfigValues->componentPath,
        ];

        $this->initialize();
    }

    /**
     * Initialize the Blade engine with performance optimizations
     */
    protected function initialize(): void
    {
        $this->ensureCacheDirectory();

        $container = new BladeContainer;
        $app = Application::getInstance();

        $this->blade = new \Rcalicdan\Blade\Blade(
            $this->config['viewsPath'],
            $this->config['cachePath'],
            $container
        );

        $this->blade->addNamespace(
            $this->config['componentNamespace'],
            $this->config['componentPath']
        );
        
        // Register x-component directive
        $this->registerXComponentDirective();
    }

    /**
     * Register the x-component directive with Blade
     */
    protected function registerXComponentDirective(): void
    {
        if (method_exists($this->blade, 'directive')) {
            $this->blade->directive('xcomponent', function ($expression) {
                return "<?php echo \$this->renderXComponent({$expression}); ?>";
            });
        } else if (method_exists($this->blade->getCompiler(), 'directive')) {
            $this->blade->getCompiler()->directive('xcomponent', function ($expression) {
                return "<?php echo \$this->renderXComponent({$expression}); ?>";
            });
        }
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
     * @param  string  $path  Path to check
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
     * @param  array  $data  The view data to process
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
     * @param  array  $data  The view data to filter
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

        return array_filter($data, fn ($key) => !in_array($key, $internalKeys), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Set data to be passed to the view
     *
     * @param  array  $data  Data to be passed to the view
     * @return self Returns the current instance for method chaining
     */
    public function setData(array $data = []): self
    {
        $this->viewData = $this->processData($data);
        return $this;
    }

    /**
     * Render a view with the given data
     *
     * @param  string  $view  The view to render
     * @param  array  $data  Data to be passed to the view
     * @return string Rendered view content
     */
    public function render(string $view, array $data = []): string
    {
        $this->applyExtensions();

        $mergedData = array_merge($this->viewData ?? [], $data);
        $processedData = $this->processData($mergedData);
        $filteredData = $this->filterInternalKeys($processedData);

        try {
            $result = $this->blade->make($view, $filteredData)->render();
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
     * Render an x-component
     *
     * @param  string  $component  The component to render
     * @param  array  $data  Data to be passed to the component
     * @return string Rendered component content
     */
    public function renderXComponent(string $component, array $data = []): string
    {
        $componentPath = $this->config['componentPath'] . '/' . str_replace('.', '/', $component) . '.blade.php';
        
        if (!file_exists($componentPath)) {
            log_message('error', "X-component not found: {$componentPath}");
            return '<!-- X-Component Not Found: ' . htmlspecialchars($component) . ' -->';
        }
        
        return $this->render($component, $data);
    }

    /**
     * Get the Blade instance
     *
     * @return Blade The Blade engine instance
     */
    public function getBlade()
    {
        return $this->blade;
    }

    /**
     * Register a component class
     * 
     * @param string $alias The component alias
     * @param string $class The component class
     * @return void
     */
    public function component(string $alias, string $class): void
    {
        $this->blade->component($alias, $class);
    }

    /**
     * Register multiple components
     * 
     * @param array $components Array of components to register
     * @return void
     */
    public function components(array $components): void
    {
        foreach ($components as $alias => $class) {
            $this->component($alias, $class);
        }
    }

    /**
     * Compiles all blade views for improved application startup performance
     *
     * @param  bool  $force  Force recompilation
     * @return array Compilation results
     */
    public function compileViews(bool $force = false): array
    {
        $compiler = $this->blade->getCompiler();
        $viewsPath = $this->config['viewsPath'];
        $files = $this->getBladeFiles($viewsPath);

        $results = [];
        foreach ($files as $file) {
            $relativePath = str_replace($viewsPath.'/', '', $file);
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
     * Get all Blade template files recursively with caching
     *
     * @param  string  $directory  The directory to search
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