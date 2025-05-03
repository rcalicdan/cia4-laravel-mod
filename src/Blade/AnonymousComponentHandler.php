<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Rcalicdan\Ci4Larabridge\Blade\BladeService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class AnonymousComponentHandler
{
    /**
     * @var BladeService The Blade service instance
     */
    protected BladeService $bladeService;
    
    /**
     * @var array Registered components
     */
    protected array $components = [];
    
    /**
     * @var array Component paths
     */
    protected array $componentPaths = [];
    
    /**
     * Initialize with the BladeService
     */
    public function __construct(BladeService $bladeService)
    {
        $this->bladeService = $bladeService;
        
        // Add default components path
        $this->addComponentsPath(APPPATH . 'Views/components');
        
        $this->setupDirectives();
    }
    
    /**
     * Set up blade directives for components
     */
    protected function setupDirectives(): void
    {
        $blade = $this->bladeService->getBlade();
        $compiler = $blade->getCompiler();
        
        // Register component directives
        $compiler->directive('component', function ($expression) {
            return "<?php \$__component = \$this->resolveComponent({$expression}); ?>";
        });
        
        $compiler->directive('endcomponent', function () {
            return "<?php echo \$__component; unset(\$__component); ?>";
        });
        
        // Handle <x-component-name> syntax
        $compiler->directive('x', function ($expression) {
            return "<?php echo \$this->renderComponent({$expression}); ?>";
        });
    }
    
    /**
     * Add a path to discover components from
     *
     * @param string $path Directory path containing blade components
     * @return self
     */
    public function addComponentsPath(string $path): self
    {
        $path = rtrim($path, '/\\');
        $this->componentPaths[] = $path;
        $this->discoverComponents($path);
        
        return $this;
    }
    
    /**
     * Discover components from a directory
     *
     * @param string $path Directory path to scan
     * @return void
     */
    protected function discoverComponents(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        
        // Find all .blade.php files in the directory
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $files = new RegexIterator($iterator, '/^.+\.blade\.php$/i', RegexIterator::GET_MATCH);
        
        foreach ($files as $file) {
            $filePath = $file[0];
            $relativePath = substr($filePath, strlen($path) + 1);
            $fileName = pathinfo($relativePath, PATHINFO_FILENAME);
            $dirName = pathinfo($relativePath, PATHINFO_DIRNAME);
            
            if ($dirName === '.') {
                // Component is in the root directory
                $componentName = $fileName;
            } else {
                // Component is in a subdirectory
                $componentName = str_replace('/', '.', $dirName) . '.' . $fileName;
            }
            
            // Clean up component name (remove .blade suffix if present)
            $componentName = str_replace('.blade', '', $componentName);
            
            // Register the component with its view path
            $viewPath = 'components.' . $componentName;
            $this->components[$componentName] = $viewPath;
        }
    }
    
    /**
     * Register a component manually
     *
     * @param string $name Component name
     * @param string $viewPath View path for the component
     * @return self
     */
    public function registerComponent(string $name, string $viewPath): self
    {
        $this->components[$name] = $viewPath;
        return $this;
    }
    
    /**
     * Register multiple components
     *
     * @param array $components Array of component name => view path
     * @return self
     */
    public function registerComponents(array $components): self
    {
        foreach ($components as $name => $viewPath) {
            $this->registerComponent($name, $viewPath);
        }
        return $this;
    }
    
    /**
     * Render a component by name
     *
     * @param string $name Component name
     * @param array $data Component data
     * @return string Rendered component
     */
    public function renderComponent(string $name, array $data = []): string
    {
        // Clean component name (remove <x-> prefix if present)
        $name = ltrim($name, '<x-');
        $name = rtrim($name, '>');
        
        // Get component view path
        $viewPath = $this->resolveComponentViewPath($name);
        
        // Render the component
        try {
            return $this->bladeService->render($viewPath, $data);
        } catch (\Throwable $e) {
            log_message('error', "Failed to render component '{$name}': " . $e->getMessage());
            return '<!-- Component rendering error: ' . $name . ' -->';
        }
    }
    
    /**
     * Resolve the view path for a component
     *
     * @param string $name Component name
     * @return string View path
     * @throws \Exception If component not found
     */
    protected function resolveComponentViewPath(string $name): string
    {
        // Check if component is registered
        if (isset($this->components[$name])) {
            return $this->components[$name];
        }
        
        // Try some common paths
        $possiblePaths = [
            "components.{$name}",
            "components/{$name}",
            $name
        ];
        
        foreach ($possiblePaths as $path) {
            try {
                if ($this->bladeService->getBlade()->exists($path)) {
                    // Found the view, register it for future use
                    $this->components[$name] = $path;
                    return $path;
                }
            } catch (\Throwable $e) {
                // Continue to next path
            }
        }
        
        throw new \Exception("Component not found: {$name}");
    }
    
    /**
     * Get all registered components
     *
     * @return array
     */
    public function getComponents(): array
    {
        return $this->components;
    }
    
    /**
     * Refresh component discovery
     *
     * @return self
     */
    public function refresh(): self
    {
        $this->components = [];
        
        foreach ($this->componentPaths as $path) {
            $this->discoverComponents($path);
        }
        
        return $this;
    }
}