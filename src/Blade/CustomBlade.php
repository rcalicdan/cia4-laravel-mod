<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Contracts\Container\Container as ContainerContract; // Use the specific Illuminate contract
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container; // The concrete container class
use Illuminate\View\View;
use Config\App as CIAppConfig;

class CustomBlade extends \Rcalicdan\Blade\Blade
{
    private $factory;
    private $compiler;
    protected $container;

    public function __construct($viewPaths, string $cachePath, ?ContainerContract $container = null)
    {
        // Use the provided container or create a new one
        $this->container = $container ?: new Container();

        // --- BEGIN REVISED MOCK APPLICATION ---

        // Get the CodeIgniter application namespace
        $appNamespace = rtrim(config(CIAppConfig::class)->appNamespace ?? 'App', '\\') . '\\';

        // Create the mock Application object that implements ApplicationContract
        // and delegates ContainerContract methods to the actual container instance.
        $mockApp = new class($appNamespace, $this->container) implements ApplicationContract {
            protected string $namespace;
            protected ContainerContract $containerDelegate; // Hold the real container

            // Store essential paths needed by some view operations
            protected string $basePath = ROOTPATH; // CodeIgniter ROOTPATH
            protected string $langPath = APPPATH . 'Language';
            protected string $configPath = APPPATH . 'Config';
            protected string $publicPath = FCPATH; // CodeIgniter FCPATH
            protected string $storagePath = WRITEPATH;
            protected string $databasePath = WRITEPATH . 'database'; // Example
            protected string $resourcePath = APPPATH . 'Views'; // Default, can be overridden
            protected string $bootstrapPath = WRITEPATH . 'cache'; // Often cache path for bootstrapping

            public function __construct(string $ns, ContainerContract $container) {
                 $this->namespace = $ns;
                 $this->containerDelegate = $container;
            }

            // == ApplicationContract Specific Methods ==
            public function version() { return 'CI4-LaravelBridge-Mock'; }
            public function getNamespace() { return $this->namespace; }
            public function environment(...$environments) { return ENVIRONMENT; } // Use CI4 Environment
            public function runningInConsole() { return is_cli(); } // Use CI4 is_cli()
            public function runningUnitTests() { return false; } // Assume false unless testing
            public function hasDebugModeEnabled() { return ENVIRONMENT !== 'production'; }
            public function isDownForMaintenance() { return false; } // Assume not in maintenance
            public function registerConfiguredProviders() { /* No-op */ }
            public function register($provider, $options = [], $force = false) { /* No-op */ }
            public function registerDeferredProvider($provider, $service = null) { /* No-op */ }
            public function resolveProvider($provider) { /* No-op */ return null; }
            public function boot() { /* No-op */ }
            public function booting($callback) { /* No-op */ }
            public function booted($callback) { /* No-op */ }
            public function getCachedServicesPath() { return $this->bootstrapPath() . '/services.php'; }
            public function getCachedPackagesPath() { return $this->bootstrapPath() . '/packages.php'; }
            public function configurationIsCached() { return false; } // Assume false
            public function getConfigurationPath($path = '') { return $this->configPath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function useDatabasePath($path) { $this->databasePath = $path; return $this; }
            public function useLangPath($path) { $this->langPath = $path; return $this; }
            public function useResourcePath($path) { $this->resourcePath = $path; return $this; }
            public function useStoragePath($path) { $this->storagePath = $path; return $this; }
            public function useBootstrapPath($path) { $this->bootstrapPath = $path; return $this; }
            public function usePublicPath($path) { $this->publicPath = $path; return $this; }
            public function useAppPath($path) { /* No-op or map to APPPATH */ return $this; }
            public function appPath($path = '') { return APPPATH . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function getProviders($provider) { return []; }
            public function hasBeenBootstrapped() { return true; } // Assume true
            public function loadDeferredProviders() { /* No-op */ }
            public function shouldSkipMiddleware() { return false; }
            public function terminating($callback) { return $this; } // Allow chaining
            public function terminate() { /* No-op */ }
            public function getLocale() { return config('App')->defaultLocale ?? 'en'; }
            public function setLocale($locale) { /* No-op or log */ }
            public function isLocale($locale) { return $this->getLocale() === $locale; }
            public function basePath($path = '') { return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function configPath($path = '') { return $this->configPath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function databasePath($path = '') { return $this->databasePath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function resourcePath($path = '') { return $this->resourcePath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function storagePath($path = '') { return $this->storagePath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function langPath($path = '') { return $this->langPath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function publicPath($path = '') { return $this->publicPath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function bootstrapPath($path = '') { return $this->bootstrapPath . ($path ? DIRECTORY_SEPARATOR . $path : $path); }
            public function maintenanceMode() { return null; } // Or implement a check if needed
            public function bootstrapWith(array $bootstrappers) { /* No-op */ }
            public function getServiceProviders($provider) { return []; }
            public function getLoadedProviders() { return []; }
            public function configurationNeedsToBeCached() { return false; }
            public function loadEnvironmentFrom($file) { return $this; }
            public function environmentFile() { return '.env'; } // Default
            public function environmentFilePath() { return $this->basePath(config('Paths')->environmentFile ?? '.env'); } // Get from CI Paths if possible
            public function environmentFileExists() { return file_exists($this->environmentFilePath()); }
            public function isProduction() { return $this->environment() === 'production'; }
            public function isLocal() { return $this->environment() === 'development'; } // Map CI 'development' to Laravel 'local'
            public function detectEnvironment(\Closure $callback) { return $this->environment(); } // Just return current
            public function runningTests() { return false; } // Assume false


            // == ContainerContract Methods (Delegated) ==
            public function bound($abstract) { return $this->containerDelegate->bound($abstract); }
            public function alias($abstract, $alias) { $this->containerDelegate->alias($abstract, $alias); }
            public function tag($abstracts, $tags) { $this->containerDelegate->tag($abstracts, $tags); }
            public function tagged($tag) { return $this->containerDelegate->tagged($tag); }
            public function bind($abstract, $concrete = null, $shared = false) { $this->containerDelegate->bind($abstract, $concrete, $shared); }
            public function bindIf($abstract, $concrete = null, $shared = false) { return $this->containerDelegate->bindIf($abstract, $concrete, $shared); }
            public function singleton($abstract, $concrete = null) { $this->containerDelegate->singleton($abstract, $concrete); }
            public function singletonIf($abstract, $concrete = null) { return $this->containerDelegate->singletonIf($abstract, $concrete); }
            public function scoped($abstract, $concrete = null) { $this->containerDelegate->scoped($abstract, $concrete); }
            public function scopedIf($abstract, $concrete = null) { return $this->containerDelegate->scopedIf($abstract, $concrete); }
            public function instance($abstract, $instance) { return $this->containerDelegate->instance($abstract, $instance); }
            public function addContextualBinding($concrete, $abstract, $implementation) { $this->containerDelegate->addContextualBinding($concrete, $abstract, $implementation); }
            public function extend($abstract, \Closure $closure) { $this->containerDelegate->extend($abstract, $closure); }
            public function make($abstract, array $parameters = []) { return $this->containerDelegate->make($abstract, $parameters); }
            // Make with was added later, check your illuminate/container version
            // public function makeWith($abstract, array $parameters = []) { return $this->containerDelegate->makeWith($abstract, $parameters); }
            public function call($callback, array $parameters = [], $defaultMethod = null) { return $this->containerDelegate->call($callback, $parameters, $defaultMethod); }
            public function resolved($abstract) { return $this->containerDelegate->resolved($abstract); }
            public function resolving($abstract, \Closure $callback = null) { $this->containerDelegate->resolving($abstract, $callback); }
            public function afterResolving($abstract, \Closure $callback = null) { $this->containerDelegate->afterResolving($abstract, $callback); }
            public function when($concrete) { return $this->containerDelegate->when($concrete); }
            public function factory($abstract) { return $this->containerDelegate->factory($abstract); }
            public function flush() { $this->containerDelegate->flush(); }
            public function isShared($abstract) { return $this->containerDelegate->isShared($abstract); }
            public function isAlias($name) { return $this->containerDelegate->isAlias($name); }
            public function forgetInstance($abstract) { $this->containerDelegate->forgetInstance($abstract); }
            public function forgetInstances() { $this->containerDelegate->forgetInstances(); }
            public function forgetScopedInstances() { $this->containerDelegate->forgetScopedInstances(); }
            public function getBindings() { return $this->containerDelegate->getBindings(); }
            public function getAlias($abstract) { return $this->containerDelegate->getAlias($abstract); }
            public function bindMethod($method, $callback) { return $this->containerDelegate->bindMethod($method, $callback); }
            public function hasMethodBinding($method) { return $this->containerDelegate->hasMethodBinding($method); }
            public function callMethodBinding($method, $instance) { return $this->containerDelegate->callMethodBinding($method, $instance); }
            public function addScopedInstances(array $instances) { $this->containerDelegate->addScopedInstances($instances); }
            public function forgetExtenders($abstract) { $this->containerDelegate->forgetExtenders($abstract); }
            public function build($concrete) { return $this->containerDelegate->build($concrete); }
            public function getContextualConcrete($abstract) { return $this->containerDelegate->getContextualConcrete($abstract); }
            public function beforeResolving($abstract, \Closure $callback = null) { $this->containerDelegate->beforeResolving($abstract, $callback); }

            // == PSR-11 Methods (Delegated) ==
            public function get(string $id) { return $this->containerDelegate->get($id); }
            public function has(string $id): bool { return $this->containerDelegate->has($id); }

            // == ArrayAccess Methods (Delegated) ==
            public function offsetExists($key): bool { return $this->containerDelegate->offsetExists($key); }
            public function offsetGet($key): mixed { return $this->containerDelegate->offsetGet($key); }
            public function offsetSet($key, $value): void { $this->containerDelegate->offsetSet($key, $value); }
            public function offsetUnset($key): void { $this->containerDelegate->offsetUnset($key); }
        };

        // Bind the mock instance to the interface and the 'app' alias
        $this->container->instance(ApplicationContract::class, $mockApp);
        $this->container->instance('app', $mockApp); // Common alias used internally

        // Also bind the container itself to its own contract and the interface
        // This ensures that if something asks specifically for ContainerContract, it gets the real one
        if (!$this->container->bound(ContainerContract::class)) {
            $this->container->instance(ContainerContract::class, $this->container);
        }
        if (!$this->container->bound(Container::class)) {
             $this->container->instance(Container::class, $this->container);
        }
        // Make the container instance globally available IF NEEDED by other parts that use Container::getInstance()
        // Container::setInstance($this->container); // Uncomment if static access is truly required by some component

        // --- END REVISED MOCK APPLICATION ---


        // Set up all the components manually using the *real* container
        $this->setupContainer((array) $viewPaths, $cachePath); // Pass viewPaths to setup
        $this->registerBladeCompiler($cachePath);
        $this->registerEngineResolver();
        $this->registerViewFactory();

        // Initialize the factory and compiler properties for THIS class by getting them from the container
        $this->factory = $this->container->get('view');
        $this->compiler = $this->container->get('blade.compiler');
    }

    // Ensure setupContainer uses $this->container which is now fully set up
    protected function setupContainer(array $viewPaths, string $cachePath): void
    {
        // Bind filesystem if not already bound
        $this->container->bindIf('files', function () {
            return new \Illuminate\Filesystem\Filesystem;
        }, true);

        // Bind view finder if not already bound
        $this->container->bindIf('view.finder', function ($app) use ($viewPaths) {
            // $app here is the IoC container instance ($this->container)
            return new FileViewFinder($app['files'], $viewPaths);
        }, true);

        // Bind events dispatcher if not already bound
        $this->container->bindIf('events', function ($app) {
            // Pass the container itself to the Dispatcher
            return new Dispatcher($app);
        }, true);
        $this->container->alias('events', DispatcherContract::class);

        // Bind the 'config' alias if needed by some components (provide a minimal mock or real CI config access)
        if (!$this->container->bound('config')) {
            $this->container->singleton('config', function() {
                // Return a simple object/array or a class that wraps CI's config() function
                return new class {
                    public function get($key, $default = null) {
                        // Basic implementation - split key by dot if needed
                        $keys = explode('.', $key);
                        $configValue = null;
                        if (count($keys) > 1) {
                             $configFile = $keys[0];
                             $configItem = implode('.', array_slice($keys, 1));
                             // Assuming CI config files map to first part of key
                             try {
                                 $configClass = config(ucfirst($configFile)); // e.g., config('App')
                                 if ($configClass && property_exists($configClass, $configItem)) {
                                     $configValue = $configClass->$configItem;
                                 } elseif ($configClass && is_array($configClass) && isset($configClass[$configItem])) {
                                     // Handle config returning arrays (less common in CI4)
                                      $configValue = $configClass[$configItem];
                                 }
                             } catch (\Throwable $e) { /* ignore if config file doesn't exist */ }
                        } else {
                            try {
                                $configValue = config($key); // Try direct CI config access
                            } catch (\Throwable $e) { /* ignore */ }
                        }

                        return $configValue ?? $default;
                    }
                    public function has($key) {
                         // Basic check - might need refinement based on how 'config' is used
                         return $this->get($key) !== null;
                    }
                    // Add set() etc. if needed
                };
            });
        }
    }

    // Ensure other registration methods use $this->container
    protected function registerBladeCompiler(string $cachePath): void
    {
        $this->container->bindIf('blade.compiler', function ($app) use ($cachePath) {
            // $app is the container
            return new BladeCompiler($app['files'], $cachePath);
        }, true);
    }

    protected function registerEngineResolver(): void
    {
        $this->container->bindIf('view.engine.resolver', function ($app) { // $app is container
            $resolver = new EngineResolver;

            // The closures now correctly receive the container ($app)
            $resolver->register('php', function () use ($app) {
                return new PhpEngine($app['files']); // Use $app['files']
            });

            $resolver->register('blade', function () use ($app) {
                return new CompilerEngine($app['blade.compiler']); // Use $app['blade.compiler']
            });

            return $resolver;
        }, true);
    }

    protected function registerViewFactory(): void
    {
        $this->container->bindIf('view', function ($app) { // $app is container
            $factory = new Factory(
                $app['view.engine.resolver'],
                $app['view.finder'],
                $app['events']
            );

            // Share the factory itself as '__env' for use within views
            $factory->share('__env', $factory); // Use the created $factory

            // Share the container instance itself as '$app' for use within views/components if needed
            $factory->share('app', $app); // Share the actual container

            return $factory;
        }, true);
        $this->container->alias('view', Factory::class); // Alias Illuminate\View\Factory
    }


    // --- Method Overrides (Ensure they use local properties) ---

    public function addNamespace($namespace, $hints): self
    {
        if (!$this->factory) { throw new \LogicException('View factory not initialized for addNamespace.'); }
        $this->factory->getFinder()->addNamespace($namespace, $hints);
        return $this;
    }

    public function replaceNamespace($namespace, $hints): self
    {
        if (!$this->factory) { throw new \LogicException('View factory not initialized for replaceNamespace.'); }
        $this->factory->getFinder()->replaceNamespace($namespace, $hints);
        return $this;
    }

    public function render(string $view, array $data = [], array $mergeData = []): string
    {
        if (!$this->factory) { throw new \LogicException('View factory not initialized for render.'); }
        // Use the make method of *this* class, which delegates correctly
        return $this->make($view, $data, $mergeData)->render();
    }

    public function make($view, $data = [], $mergeData = []): View
    {
        if (!$this->factory) { throw new \LogicException('View factory not initialized for make.'); }
        // Delegate to the underlying factory instance
        return $this->factory->make($view, $data, $mergeData);
    }

    public function directive(string $name, callable $handler): void
    {
        if (!$this->compiler) { throw new \LogicException('Blade compiler not initialized for directive.'); }
        $this->compiler->directive($name, $handler);
    }

    public function share($key, $value = null)
    {
        if (!$this->factory) { throw new \LogicException('View factory not initialized for share.'); }
        return $this->factory->share($key, $value);
    }

    public function composer($views, $callback): array
    {
        if (!$this->factory) { throw new \LogicException('View factory not initialized for composer.'); }
        return $this->factory->composer($views, $callback);
    }

    public function exists($view): bool
    {
        if (!$this->factory) { throw new \LogicException('View factory not initialized for exists.'); }
        return $this->factory->exists($view);
    }

    // Return the actual compiler instance
    public function compiler(): BladeCompiler
    {
         if (!$this->compiler) { throw new \LogicException('Blade compiler not initialized for compiler().'); }
         return $this->compiler;
    }

    public function component($alias, $class = null)
    {
        if (!$this->compiler) { throw new \LogicException('Blade compiler not initialized for component.'); }

        // Use the compiler's component registration method (common in modern Laravel)
        if (method_exists($this->compiler, 'component')) {
            $this->compiler->component($alias, $class);
        }
        // Fallback for older versions where Factory might handle components
        elseif (method_exists($this->factory, 'component')) {
             $this->factory->component($alias, $class);
        }
        // Or handle component aliases directly if needed
        elseif (method_exists($this->compiler, 'aliasComponent')) {
             $this->compiler->aliasComponent($alias, $class);
        }
         else {
            log_message('warning', 'Cannot register component directly. Method not found on compiler or factory.');
        }
    }

     /**
     * Dynamically handle calls to the class.
     * Added to potentially catch calls to methods existing on the Factory but not explicitly overridden.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (!$this->factory) {
            throw new \LogicException('View factory not initialized for __call.');
        }

        // Delegate unknown method calls to the underlying View Factory
        if (method_exists($this->factory, $method)) {
            return $this->factory->$method(...$parameters);
        }

        // Or delegate to the Compiler if appropriate
        if (!$this->compiler) {
             throw new \LogicException('Blade compiler not initialized for __call.');
        }
        if (method_exists($this->compiler, $method)) {
            return $this->compiler->$method(...$parameters);
        }

        throw new \BadMethodCallException(sprintf(
            'Method %s::%s does not exist.', static::class, $method
        ));
    }

}