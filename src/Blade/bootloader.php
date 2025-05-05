<?php

use Rcalicdan\Ci4Larabridge\Blade\Application;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Support\Fluent;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\FileViewFinder;
use Rcalicdan\Blade\Blade;

define('APP_PATH', __DIR__);

$app = Application::getInstance();

$app->bind(ApplicationContract::class, Application::class);

// Create and configure Blade
$blade = new Blade(
    [
        APP_PATH.'Views',
        APP_PATH.'Views/components',
    ],
    WRITEPATH . 'cache/blade',
    $app
);

// This is important - bind the ViewFactory interface to our Blade instance
$app->singleton(ViewFactoryContract::class, function() use ($blade) {
    return $blade;
});

// Also bind the 'view' alias to our Blade instance
$app->instance('view', $blade);

// Register x-components
$blade->compiler()->components([
    'button' => 'button',
    'component-wrapper' => 'component-wrapper',
    'edit-button' => 'edit-button'
]);