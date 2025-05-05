<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory; // <-- Make sure this use statement exists
use Illuminate\View\FileViewFinder;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\View\View;

class CustomBlade extends \Rcalicdan\Blade\Blade
{
    private $factory; // This instance is initialized correctly in the constructor
    private $compiler;
    protected $container;

    public function __construct($viewPaths, string $cachePath, ?ContainerInterface $container = null)
    {
        $this->container = $container ?: new Container();

        // Set up all the components manually
        $this->setupContainer((array) $viewPaths, $cachePath);
        $this->registerBladeCompiler($cachePath);
        $this->registerEngineResolver();
        $this->registerViewFactory();

        // Initialize the factory and compiler properties for THIS class
        $this->factory = $this->container->get('view');
        $this->compiler = $this->container->get('blade.compiler');
    }

    protected function setupContainer(array $viewPaths, string $cachePath): void
    {
        $this->container->bindIf('files', function () {
            return new \Illuminate\Filesystem\Filesystem;
        }, true);

        $this->container->bindIf('view.finder', function ($app) use ($viewPaths) {
            return new FileViewFinder($app['files'], $viewPaths);
        }, true);

        $this->container->bindIf('events', function ($app) {
            return new Dispatcher($app);
        }, true);
        $this->container->alias('events', DispatcherContract::class);
    }

    protected function registerBladeCompiler(string $cachePath): void
    {
        $this->container->bindIf('blade.compiler', function ($app) use ($cachePath) {
            return new BladeCompiler($app['files'], $cachePath);
        }, true);
    }

    protected function registerEngineResolver(): void
    {
        $this->container->bindIf('view.engine.resolver', function () {
            $resolver = new EngineResolver;

            $resolver->register('php', function () {
                return new PhpEngine($this->container->get('files'));
            });

            $resolver->register('blade', function () {
                return new CompilerEngine($this->container->get('blade.compiler'));
            });

            return $resolver;
        }, true);
    }

    protected function registerViewFactory(): void
    {
        $this->container->bindIf('view', function ($app) {
            $factory = new Factory(
                $app['view.engine.resolver'],
                $app['view.finder'],
                $app['events']
            );

            $factory->share('__env', $factory);
            return $factory;
        }, true);
        $this->container->alias('view', Factory::class);
    }

    // --- ADD THIS METHOD ---
    /**
     * Add a namespace hint to the finder.
     * Overrides the parent method to use the locally managed factory.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return $this
     */
    public function addNamespace($namespace, $hints): self
    {
        // Use the $factory property initialized in this class's constructor
        // Ensure the 'view.finder' has been resolved and has the addNamespace method
        // The factory itself doesn't have addNamespace, the finder does.
        // We need to access the finder via the factory.
        $this->factory->getFinder()->addNamespace($namespace, $hints);

        return $this;
    }

    // --- ADD THIS METHOD AS WELL (for completeness, if replaceNamespace is also used) ---
    /**
     * Replace the namespace hints for the given namespace.
     * Overrides the parent method to use the locally managed factory's finder.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return $this
     */
    public function replaceNamespace($namespace, $hints): self
    {
        $this->factory->getFinder()->replaceNamespace($namespace, $hints);

        return $this;
    }

    // --- Keep other methods like render, directive, share, composer etc. ---
    public function render(string $view, array $data = [], array $mergeData = []): string
    {
        // Ensure factory is not null before calling make
        if (!$this->factory) {
            throw new \LogicException('View factory not initialized.');
        }
        return $this->factory->make($view, $data, $mergeData)->render();
    }

    public function make($view, $data = [], $mergeData = []): View
    {
        return $this->factory->make($view, $data, $mergeData);
    }

    public function directive(string $name, callable $handler): void
    {
        // Ensure compiler is not null
        if (!$this->compiler) {
            throw new \LogicException('Blade compiler not initialized.');
        }
        $this->compiler->directive($name, $handler);
    }

    public function share($key, $value = null)
    {
        if (!$this->factory) {
            throw new \LogicException('View factory not initialized.');
        }
        return $this->factory->share($key, $value);
    }

    public function composer($views, $callback): array
    {
        if (!$this->factory) {
            throw new \LogicException('View factory not initialized.');
        }
        return $this->factory->composer($views, $callback);
    }

    // Add any other methods from the parent Rcalicdan\Blade\Blade that rely on $this->factory
    // and override them here to use the $this->factory initialized in CustomBlade.
    // For example, if there's a 'exists' method:
 
    public function exists($view): bool
    {
        return $this->factory->exists($view);
    }


    // --- You might also need to delegate component registration ---
    /**
     * Register a component alias.
     *
     * @param  string  $alias
     * @param  string|null  $class
     * @return void
     */
    public function component($alias, $class = null)
    {
        if (!$this->compiler) {
            throw new \LogicException('Blade compiler not initialized.');
        }
        // Assuming the compiler has the component method, typical in newer Laravel versions
        // Adjust if the factory or another object handles this in your version
        if (method_exists($this->compiler, 'component')) {
            $this->compiler->component($alias, $class);
        } elseif (method_exists($this->factory, 'component')) {
            // Older versions might have it on the factory
            $this->factory->component($alias, $class);
        } else {
            // Handle the case where component registration isn't directly available
            // or log a warning. This depends heavily on the underlying versions.
            log_message('warning', 'Cannot register component directly. Method not found on compiler or factory.');
        }
    }
}
