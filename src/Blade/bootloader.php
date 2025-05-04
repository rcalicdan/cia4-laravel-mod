<?php

use Rcalicdan\Ci4Larabridge\Blade\Application;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Fluent;
use Rcalicdan\Blade\Blade;
use Rcalicdan\Blade\Container;

define('APP_PATH', __DIR__);

$bladeConfig = config('Blade');

$app = Container::getInstance();

$app->bind(ApplicationContract::class, Application::class);

// Needed for anonymous components
$app->alias('view', ViewFactory::class);

$app->extend('config', function (array $config) {
    return new Fluent($config);
});

$blade = new Blade(
    [
       $bladeConfig->viewsPath,
       $bladeConfig->componentsPath,
    ],
    $bladeConfig->cachePath,
    $app
);

$app->bind('view', function () use ($blade) {
    return $blade;
});

// Register x-components
$blade->components([
    'button' => 'button',
    'component-wrapper' => 'component-wrapper',
]);