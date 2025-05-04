<?php

use Rcalicdan\Ci4Larabridge\Blade\Application;
use Rcalicdan\Ci4Larabridge\Blade\YoyoServiceProvider;
use Clickfwd\Yoyo\ViewProviders\BladeViewProvider;
use Clickfwd\Yoyo\Yoyo;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Fluent;
use Rcalicdan\Blade\Blade;

define('APP_PATH', __DIR__);

$app = Application::getInstance();

$app->bind(ApplicationContract::class, Application::class);

// Needed for anonymous components
$app->alias('view', ViewFactory::class);

$app->extend('config', function (array $config) {
    return new Fluent($config);
    // return new Repository($config);
});

$blade = new Blade(
    [
        APP_PATH.'/resources/views',
        APP_PATH.'/resources/views/yoyo',
        APP_PATH.'/resources/views/components',
    ],
    APP_PATH.'/../cache',
    $app
);

$app->bind('view', function () use ($blade) {
    return $blade;
});

(new YoyoServiceProvider($app))->boot();

$blade->compiler()->components([
    'button' => 'button',
    'component-wrapper' => 'component-wrapper',
    'counter-alpine-event' => 'counter-alpine-event',
]);

$yoyo = new Yoyo($app);

$yoyo->configure([
    'url' => 'yoyo',
    'scriptsPath' => 'assets/js/',
    'namespace' => 'App\\Yoyo\\',
  ]);

$yoyo->registerViewProvider(function() use ($blade) {
    return new BladeViewProvider($blade);
});

$yoyo->registerComponents([
    // If anonymous component name matches template name, no need to register

    // 'dynamic-content' => 'dynamic-content',
    // 'wishlist-counter' => 'wishlist-counter',
    // 'wishlist' => 'wishlist',
    // 'nesting-parent' => 'nesting-parent',
    // 'polling' => 'polling',

    // If dynamic component classes are autoloaded with the right namespace, no need to register

    // 'counter' => \App\Yoyo\Counter::class,
    // 'load-more' => \App\Yoyo\LoadMore::class,
    // 'live-search' => \App\Yoyo\LiveSearch::class,
    // 'form' => \App\Yoyo\Form::class,
    // 'pagination' => \App\Yoyo\Pagination::class,
]);