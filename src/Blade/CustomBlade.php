<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Contracts\Container\Container as ContainerInterface;
// --- Add this use statement if not already present ---
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract; 
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Compilers\BladeCompiler;
// --- Add this use statement ---
use Illuminate\Events\Dispatcher; 
use Illuminate\Container\Container; // Assuming Application extends or is compatible with this

// --- It might extend Rcalicdan\Blade\Blade or maybe directly Illuminate components ---
// --- Keep the original class definition structure ---
class CustomBlade extends \Rcalicdan\Blade\Blade 
{
    private $factory;
    private $compiler;
    
    // --- Keep original container property if it exists ---
    protected $container; 

    public function __construct($viewPaths, string $cachePath, ?ContainerInterface $container = null)
    {
        // --- Use the provided container or a default one ---
        // --- Make sure 'Application' is defined or replace with 'Illuminate\Container\Container' ---
        // --- Original code used 'Application()', let's assume it's compatible or use Container ---
        $this->container = $container ?: new Container(); 

        // Set up all the components manually instead of using ViewServiceProvider
        $this->setupContainer((array) $viewPaths, $cachePath);
        $this->registerBladeCompiler($cachePath);
        $this->registerEngineResolver();
        $this->registerViewFactory(); // This now depends on 'events' being bound

        $this->factory = $this->container->get('view');
        $this->compiler = $this->container->get('blade.compiler');
    }

    protected function setupContainer(array $viewPaths, string $cachePath): void
    {
        $this->container->bindIf('files', function() {
            return new \Illuminate\Filesystem\Filesystem;
        }, true);

        $this->container->bindIf('view.finder', function($app) use ($viewPaths) {
            return new FileViewFinder($app['files'], $viewPaths);
        }, true);
        
        // --- ADDED: Bind the Event Dispatcher ---
        $this->container->bindIf('events', function ($app) {
             // Pass the container to the dispatcher if needed for resolving listeners
            return new Dispatcher($app);
        }, true);
        // --- Ensure the bound service implements the contract ---
        $this->container->alias('events', DispatcherContract::class); 

    }

    protected function registerBladeCompiler(string $cachePath): void
    {
        $this->container->bindIf('blade.compiler', function($app) use ($cachePath) {
            return new BladeCompiler($app['files'], $cachePath);
        }, true);
    }

    protected function registerEngineResolver(): void
    {
        $this->container->bindIf('view.engine.resolver', function() {
            $resolver = new EngineResolver;

            // --- Register PHP engine ---
            $resolver->register('php', function() {
                 // Ensure 'files' is available in the container when this closure runs
                return new PhpEngine($this->container->get('files'));
            });

            // --- Register Blade engine ---
            $resolver->register('blade', function() {
                 // Ensure 'blade.compiler' is available when this closure runs
                return new CompilerEngine($this->container->get('blade.compiler'));
            });

            return $resolver;
        }, true);
    }

    protected function registerViewFactory(): void
    {
        // --- Bind the main 'view' factory ---
        $this->container->bindIf('view', function($app) { // $app is the Container instance
            // --- MODIFIED: Use the bound event dispatcher ---
            $factory = new Factory(
                $app['view.engine.resolver'], // Engine Resolver instance
                $app['view.finder'],         // View Finder instance
                $app['events']               // Corrected: Event Dispatcher instance
            );
            
            // --- Share the environment with the views ---
            // --- This line might have been in the original Illuminate\View\ViewServiceProvider ---
            // --- It's often needed for things like @inject ---
             $factory->share('__env', $factory); 

            return $factory;
        }, true);
         // --- Alias the factory for easier access or type hinting if needed ---
         $this->container->alias('view', Factory::class);
    }

    // --- Include any other methods from the original class below ---
    // public function render(...) etc.
    
    // Example minimal render method (if it was missing or part of parent)
    public function render(string $view, array $data = [], array $mergeData = []): string
    {
        return $this->factory->make($view, $data, $mergeData)->render();
    }

    // Example for directives (if needed)
    public function directive(string $name, callable $handler): void
    {
        $this->compiler->directive($name, $handler);
    }
    
    // Add other necessary methods like share(), composer(), creator(), etc.
    // if they are expected to be called on this Blade instance, delegating
    // them to the underlying $this->factory. For example:
    
    public function share($key, $value = null)
    {
        return $this->factory->share($key, $value);
    }

    public function composer($views, $callback): array
    {
        return $this->factory->composer($views, $callback);
    }
    
    // ... etc.
}