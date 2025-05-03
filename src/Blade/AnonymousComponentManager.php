<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Rcalicdan\Blade\Blade;

/**
 * AnonymousComponentManager handles registration and rendering of anonymous Blade components.
 * 
 * This class implements auto-discovery of components within registered component paths,
 * eliminating the need to manually register each component.
 */
class AnonymousComponentManager
{
    /**
     * @var array Registered anonymous components
     */
    protected array $components = [];

    /**
     * @var array Component aliases for easy reference
     */
    protected array $aliases = [];

    /**
     * @var array Paths where component views are located
     */
    protected array $componentPaths = [];

    /**
     * @var Blade The Blade instance
     */
    protected Blade $blade;

    /**
     * @var array Cache of discovered components
     */
    protected array $discoveredComponents = [];

    /**
     * @var bool Whether component auto-discovery has been performed
     */
    protected bool $discoveryPerformed = false;

    /**
     * Create a new AnonymousComponentManager instance.
     *
     * @param Blade $blade The Blade instance
     * @param array $paths Initial component paths
     */
    public function __construct(Blade $blade, array $paths = [])
    {
        $this->blade = $blade;
        
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $this->componentPaths[] = $path;
            }
        }
    }

    /**
     * Add a path where component views are located
     *
     * @param string $path Path to component views
     * @return self Returns the current instance for method chaining
     */
    public function addComponentPath(string $path): self
    {
        if (!in_array($path, $this->componentPaths) && is_dir($path)) {
            $this->componentPaths[] = $path;
            // Reset discovery flag to force rediscovery with new path
            $this->discoveryPerformed = false;
        }

        return $this;
    }

    /**
     * Register an anonymous component
     *
     * @param string $alias The component alias
     * @param string $view The component view name
     * @return self Returns the current instance for method chaining
     */
    public function component(string $alias, string $view): self
    {
        $this->components[$alias] = $view;
        $this->aliases[$view] = $alias;

        return $this;
    }

    /**
     * Register multiple anonymous components
     *
     * @param array $components An array of alias => view mappings
     * @return self Returns the current instance for method chaining
     */
    public function components(array $components): self
    {
        foreach ($components as $alias => $view) {
            $this->component($alias, $view);
        }

        return $this;
    }

    /**
     * Register directives needed for anonymous components
     *
     * @return void
     */
    public function registerDirectives(): void
    {
        $compiler = $this->blade->getCompiler();

        // Register the @component directive
        $compiler->directive('component', function ($expression) {
            return "<?php \$__componentOriginal = \$this->getFirstComponentNamespace({$expression}); ?>";
        });

        // Register the x- directive for component tags
        $compiler->directive('x', function ($expression) {
            return "<?php echo \$this->renderComponent({$expression}); ?>";
        });

        // Add the component tag handler
        $this->registerComponentTagCompiler();
    }

    /**
     * Register the component tag compiler
     *
     * @return void
     */
    protected function registerComponentTagCompiler(): void
    {
        $compiler = $this->blade->getCompiler();
        
        // Add method to the compiler if it doesn't exist
        if (!method_exists($compiler, 'precompileComponentTags')) {
            $compiler->precompiler(function ($content) {
                return $this->compileComponentTags($content);
            });
        }
    }

    /**
     * Compile component tags in the template
     *
     * @param string $content Template content
     * @return string Compiled content
     */
    protected function compileComponentTags(string $content): string
    {
        // A simple regex pattern to find x- component tags
        $pattern = '/<x-([a-zA-Z0-9\-\:\.]+)(?:\s+([^>]*))?(?:\s*\/)?>(?:(.*?)<\/x-\1>)?/s';
        
        return preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $attributes = isset($matches[2]) ? $this->parseAttributes($matches[2]) : '';
            $slot = isset($matches[3]) ? $matches[3] : '';
            
            // Generate Blade syntax for the component
            return "<?php echo \$this->renderComponent('{$component}', [{$attributes}], function() { ?>{$slot}<?php }); ?>";
        }, $content);
    }

    /**
     * Parse component attributes
     *
     * @param string $attributeString String of attributes
     * @return string PHP array representation of attributes
     */
    protected function parseAttributes(string $attributeString): string
    {
        $attributes = [];
        $pattern = '/(?:^|\s)([a-zA-Z0-9\-_:]+)(?:=(["\'])(.*?)\2)?/';
        
        preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $name = $match[1];
            $value = isset($match[3]) ? $match[3] : 'true';
            $attributes[] = "'{$name}' => {$value}";
        }
        
        return implode(', ', $attributes);
    }

    /**
     * Auto-discover components in registered paths
     *
     * @return void
     */
    protected function discoverComponents(): void
    {
        if ($this->discoveryPerformed) {
            return;
        }

        foreach ($this->componentPaths as $basePath) {
            $this->scanDirectory($basePath, '', $basePath);
        }

        $this->discoveryPerformed = true;
    }

    /**
     * Scan a directory for component files
     *
     * @param string $path Current scan path
     * @param string $prefix Component namespace prefix
     * @param string $basePath The original base path for this scan
     * @return void
     */
    protected function scanDirectory(string $path, string $prefix, string $basePath): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = scandir($path);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($fullPath)) {
                // Build namespace for subdirectory components
                $newPrefix = $prefix ? $prefix . '.' . $file : $file;
                $this->scanDirectory($fullPath, $newPrefix, $basePath);
            } elseif (strpos($file, '.blade.php') !== false) {
                // Found a component file
                $name = str_replace('.blade.php', '', $file);
                $componentName = $prefix ? $prefix . '.' . $name : $name;
                
                // Calculate the view name relative to base path
                $relativePath = substr($path, strlen($basePath) + 1);
                $viewName = $relativePath ? 
                    str_replace(DIRECTORY_SEPARATOR, '.', $relativePath) . '.' . $name :
                    $name;
                
                // Store in discovered components cache
                $this->discoveredComponents[$componentName] = $viewName;
            }
        }
    }

    /**
     * Find a component view by its name
     *
     * @param string $name Component name
     * @return string|null Component view path or null if not found
     */
    public function findComponentView(string $name): ?string
    {
        // Always run discovery to ensure we have all components
        if (!$this->discoveryPerformed) {
            $this->discoverComponents();
        }
        
        // Check if component is explicitly registered
        if (isset($this->components[$name])) {
            return $this->components[$name];
        }
        
        // Check in discovered components
        if (isset($this->discoveredComponents[$name])) {
            return $this->discoveredComponents[$name];
        }
        
        // Try to find the component directly in component paths
        foreach ($this->componentPaths as $path) {
            // Convert dots to directory separators for the file path
            $componentPath = str_replace('.', DIRECTORY_SEPARATOR, $name);
            $viewPath = $path . DIRECTORY_SEPARATOR . $componentPath . '.blade.php';
            
            if (file_exists($viewPath)) {
                // Convert to dot notation for view rendering
                $pathSegments = explode(DIRECTORY_SEPARATOR, $path);
                $basePath = end($pathSegments);
                return $basePath . '.' . str_replace(DIRECTORY_SEPARATOR, '.', $componentPath);
            }
        }
        
        return null;
    }

    /**
     * Render a component with the given data
     *
     * @param string $name Component name
     * @param array $data Component data
     * @param callable|null $slot Component slot content
     * @return string Rendered component
     */
    public function renderComponent(string $name, array $data = [], ?callable $slot = null): string
    {
        $view = $this->findComponentView($name);
        
        if (!$view) {
            return "<!-- Component {$name} not found -->";
        }
        
        // Add slot content if provided
        if ($slot) {
            ob_start();
            $slot();
            $data['slot'] = ob_get_clean();
        }
        
        // Create attributes bag for components
        $data['attributes'] = new class($data) {
            private $attributes;
            
            public function __construct(array &$attributes) {
                $this->attributes = &$attributes;
            }
            
            public function get($key, $default = null) {
                return $this->attributes[$key] ?? $default;
            }
            
            public function has($key) {
                return isset($this->attributes[$key]);
            }
            
            public function exceptProps(array $props) {
                $filtered = [];
                foreach ($this->attributes as $key => $value) {
                    if (!in_array($key, $props)) {
                        $filtered[$key] = $value;
                    }
                }
                return new self($filtered);
            }
            
            public function merge(array $attributes) {
                $this->attributes = array_merge($this->attributes, $attributes);
                return $this;
            }
        };
        
        try {
            return $this->blade->make($view, $data)->render();
        } catch (\Exception $e) {
            log_message('error', "Error rendering component '{$name}': " . $e->getMessage());
            return "<!-- Error rendering component '{$name}': " . $e->getMessage() . " -->";
        }
    }

    /**
     * Get all discovered components
     *
     * @return array List of discovered components
     */
    public function getDiscoveredComponents(): array
    {
        if (!$this->discoveryPerformed) {
            $this->discoverComponents();
        }
        
        return $this->discoveredComponents;
    }
}