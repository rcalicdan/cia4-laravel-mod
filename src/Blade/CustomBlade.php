<?php

namespace Rcalicdan\Ci4Larabridge\Blade;

use Illuminate\Contracts\Container\Container as ContainerInterface;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Illuminate\View\Compilers\BladeCompiler;

class CustomBlade extends \Rcalicdan\Blade\Blade
{
    private $factory;
    private $compiler;

    public function __construct($viewPaths, string $cachePath, ?ContainerInterface $container = null)
    {
        $this->container = $container ?: new Application();
        
        // Set up all the components manually instead of using ViewServiceProvider
        $this->setupContainer((array) $viewPaths, $cachePath);
        $this->registerBladeCompiler($cachePath);
        $this->registerEngineResolver();
        $this->registerViewFactory();
        
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
            
            $resolver->register('php', function() {
                return new PhpEngine($this->container->get('files'));
            });
            
            $resolver->register('blade', function() {
                return new CompilerEngine($this->container->get('blade.compiler'));
            });
            
            return $resolver;
        }, true);
    }
    
    protected function registerViewFactory(): void
    {
        $this->container->bindIf('view', function($app) {
            $factory = new Factory(
                $app['view.engine.resolver'],
                $app['view.finder'],
                $app
            );
            
            return $factory;
        }, true);
    }
}