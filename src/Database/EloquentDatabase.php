<?php

namespace Rcalicdan\Ci4Larabridge\Database;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Hashing\HashManager;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Facade;
use Rcalicdan\Ci4Larabridge\Blade\PaginationRenderer;
use Rcalicdan\Ci4Larabridge\Config\Eloquent;
use Rcalicdan\Ci4Larabridge\Config\Pagination;

/**
 * Manages the setup and configuration of Laravel's Eloquent ORM
 * in a CodeIgniter 4 application.
 */
class EloquentDatabase
{
    /** @var Container */
    protected $container;

    /** @var Capsule */
    protected $capsule;

    /** @var Pagination */
    protected $paginationConfig;

    /** @var Eloquent */
    protected $eloquentConfig;

    public function __construct()
    {
        $this->paginationConfig = config('Pagination');
        $this->eloquentConfig   = config('Eloquent');

        $this->setupDatabaseConnection();
        $this->setupContainer();
        $this->registerServices();
    }

    protected function setupDatabaseConnection(): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($this->getDatabaseInformation());
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        $this->enableQueryLogging();
    }

    protected function enableQueryLogging(): void
    {
        if (ENVIRONMENT !== 'production') {
            $conn = $this->capsule->connection();
            $conn->enableQueryLog();
            $conn->getPdo()->setAttribute(
                \PDO::ATTR_EMULATE_PREPARES,
                true
            );
        }
    }

    public function getDatabaseInformation(): array
    {
        return [
            'host'      => env('database.default.hostname', $this->eloquentConfig->databaseHost),
            'driver'    => env('database.default.DBDriver',   $this->eloquentConfig->databaseDriver),
            'database'  => env('database.default.database',   $this->eloquentConfig->databaseName),
            'username'  => env('database.default.username',   $this->eloquentConfig->databaseUsername),
            'password'  => env('database.default.password',   $this->eloquentConfig->databasePassword),
            'charset'   => env('database.default.DBCharset',  $this->eloquentConfig->databaseCharset),
            'collation' => env('database.default.DBCollat',   $this->eloquentConfig->databaseCollation),
            'prefix'    => env('database.default.DBPrefix',   $this->eloquentConfig->databasePrefix),
            'port'      => env('database.default.port',       $this->eloquentConfig->databasePort),
        ];
    }

    protected function setupContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    /**
     * Register core services and pagination setup.
     */
    protected function registerServices(): void
    {
        $this->registerConfigService();
        $this->registerHashService();

        // Bind PaginationRenderer as a shared singleton
        $this->container->singleton(
            PaginationRenderer::class,
            function () {
                return new PaginationRenderer;
            }
        );
        // Alias for Laravel's paginator
        $this->container->alias(
            PaginationRenderer::class,
            'paginator.renderer'
        );

        $this->configurePagination();
    }

    /**
     * Configure pagination resolvers only once per process.
     */
    protected function configurePagination(): void
    {
        static $configured = false;
        if ($configured) {
            return;
        }
        $configured = true;

        $request    = service('request');
        $uri        = service('uri');
        $currentUrl = current_url();

        // Set default views
        Paginator::$defaultView       = $this->paginationConfig->defaultView;
        Paginator::$defaultSimpleView = $this->paginationConfig->defaultSimpleView;

        // Resolvers
        Paginator::viewFactoryResolver(
            fn() =>
            $this->container->get(PaginationRenderer::class)
        );

        Paginator::currentPageResolver(function ($pageName = 'page') use ($request) {
            $page = $request->getVar($pageName);
            return (filter_var($page, FILTER_VALIDATE_INT) !== false && (int)$page >= 1)
                ? (int)$page
                : 1;
        });

        Paginator::currentPathResolver(fn() => $currentUrl);
        Paginator::queryStringResolver(fn() => $uri->getQuery());

        CursorPaginator::currentCursorResolver(
            fn($cursorName = 'cursor') =>
            Cursor::fromEncoded($request->getVar($cursorName))
        );
    }

    protected function registerConfigService(): void
    {
        $this->container->singleton('config', function () {
            return new Repository([
                'hashing' => [
                    'driver' => 'bcrypt',
                    'bcrypt' => ['rounds' => 10],
                ],
            ]);
        });
    }

    protected function registerHashService(): void
    {
        $this->container->singleton('hash', function ($app) {
            return new HashManager($app);
        });
    }
}
