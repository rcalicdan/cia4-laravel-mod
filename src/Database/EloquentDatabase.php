<?php

namespace Rcalicdan\Ci4Larabridge\Database;

use Config\Database as CIConfig;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Hashing\HashManager;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Facade;
use Rcalicdan\Ci4Larabridge\Blade\PaginationRenderer;


/**
 * Manages the setup and configuration of Laravel's Eloquent ORM in a CodeIgniter 4 app.
 */
class EloquentDatabase
{
    protected $container;
    protected $capsule;
    protected $paginationConfig;
    protected  $eloquentConfig;
    protected bool        $servicesInitialized = false;

    public function __construct()
    {
        $this->paginationConfig = config('Pagination');
        $this->eloquentConfig   = config('Eloquent');

        // Only set up the database connection at construction.
        $this->setupDatabaseConnection();
    }

    /**
     * Boot Eloquent using CI4's own PDO and persistent mode.
     */
    protected function setupDatabaseConnection(): void
    {
        static $ciPdo = null;

        if (! $ciPdo) {
            // Grab CI4's connection (and its underlying PDO) once per process
            $ciDb  = CIConfig::connect();
            $ciPdo = $ciDb->getConnection();
        }

        // Build—and cache—our DB config
        $dbConfig = $this->getDatabaseInformation();
        $dbConfig['options'] = [
            \PDO::ATTR_PERSISTENT => true,
        ];

        // Initialize Capsule
        $this->capsule = new Capsule;
        $this->capsule->addConnection($dbConfig);

        // Swap in CI4's PDO
        $conn = $this->capsule->getConnection();
        $conn->setPdo($ciPdo);

        // Enable query‐logging & emulated prepares only outside production
        if (ENVIRONMENT !== 'production') {
            $conn->enableQueryLog();
            $ciPdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        }

        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * Lazy‐initialize container & services so that routes
     * which never use facades/pagination incur zero cost.
     */
    protected function lazyInitServices(): void
    {
        if ($this->servicesInitialized) {
            return;
        }

        $this->setupContainer();
        $this->registerServices();

        $this->servicesInitialized = true;
    }

    /**
     * Return the fully‐booted Capsule instance (initializing
     * container/services on‐demand).
     */
    public function getCapsule(): Capsule
    {
        $this->lazyInitServices();
        return $this->capsule;
    }

    /**
     * Only build this array once per request.
     */
    protected function getDatabaseInformation(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = [
            'driver'    => env('database.default.DBDriver', $this->eloquentConfig->databaseDriver),
            'host'      => env('database.default.hostname', $this->eloquentConfig->databaseHost),
            'database'  => env('database.default.database', $this->eloquentConfig->databaseName),
            'username'  => env('database.default.username', $this->eloquentConfig->databaseUsername),
            'password'  => env('database.default.password', $this->eloquentConfig->databasePassword),
            'charset'   => env('database.default.DBCharset', $this->eloquentConfig->databaseCharset),
            'collation' => env('database.default.DBCollat', $this->eloquentConfig->databaseCollation),
            'prefix'    => env('database.default.DBPrefix', $this->eloquentConfig->databasePrefix),
            'port'      => env('database.default.port',  $this->eloquentConfig->databasePort),
        ];

        return $cached;
    }

    /**
     * One‐time registration of Blade pagination resolvers.
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

        Paginator::$defaultView       = $this->paginationConfig->defaultView;
        Paginator::$defaultSimpleView = $this->paginationConfig->defaultSimpleView;

        Paginator::viewFactoryResolver(
            fn() =>
            $this->container->get('paginator.renderer')
        );

        Paginator::currentPageResolver(
            fn($pageName = 'page') => ($page = $request->getVar($pageName))
                && filter_var($page, FILTER_VALIDATE_INT)
                && (int)$page >= 1
                ? (int)$page
                : 1
        );

        Paginator::currentPathResolver(fn() => $currentUrl);
        Paginator::queryStringResolver(fn() => $uri->getQuery());

        CursorPaginator::currentCursorResolver(
            fn($cursorName = 'cursor') =>
            Cursor::fromEncoded($request->getVar($cursorName))
        );
    }

    protected function setupContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    protected function registerServices(): void
    {
        // 1) Config repository
        $this->container->singleton('config', static fn() => new Repository([
            'hashing' => [
                'driver' => 'bcrypt',
                'bcrypt' => ['rounds' => 10],
            ],
        ]));

        // 2) Hash manager
        $this->container->singleton('hash', fn($app) => new HashManager($app));

        // 3) Pagination renderer as a singleton
        $this->container->singleton(
            PaginationRenderer::class,
            fn() => new PaginationRenderer
        );
        $this->container->alias(
            PaginationRenderer::class,
            'paginator.renderer'
        );

        // 4) Configure Blade‐based pagination resolvers
        $this->configurePagination();
    }
}
