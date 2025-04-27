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

class EloquentDatabase
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Capsule
     */
    protected $capsule;

    /**
     * @var Pagination Configuration values for Eloquent Pagination
     */
    protected $paginationConfig;

    /**
     * @var Eloquent Configuration values for Eloquent
     */
    protected $eloquentConfig;

    public function __construct()
    {
        $this->paginationConfig = config('Pagination');
        $this->setupDatabaseConnection();
        $this->setupContainer();
        $this->registerServices();
    }

    /**
     * Configure and initialize the database connection
     */
    protected function setupDatabaseConnection(): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($this->getDatabaseInformation());
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        $this->capsule->getConnection()->enableQueryLog();
       
    }

    public function getDatabaseInformation(): array
    {
        return [
            'host' => $this->eloquentConfig->databaseHost ?? env('database.default.hostname', 'localhost'),
            'driver' => $this->eloquentConfig->databaseDriver ?? env('database.default.DBDriver', 'sqlite'),
            'database' => $this->eloquentConfig->databaseName ?? env('database.default.database', ''),
            'username' => $this->eloquentConfig->databaseUsername ?? env('database.default.username', 'root'),
            'password' => $this->eloquentConfig->databasePassword ?? env('database.default.password', ''),
            'charset' => $this->eloquentConfig->databaseCharset ?? env('database.default.DBCharset', 'utf8'),
            'collation' => $this->eloquentConfig->databaseCollation ?? env('database.default.DBCollat', 'utf8_general_ci'),
            'prefix' => $this->eloquentConfig->databasePrefix ?? env('database.default.DBPrefix', ''),
            'port' => $this->eloquentConfig->databasePort ?? env('database.default.port', ''),
        ];
    }

    /**
     * Configure pagination once for the entire application
     */
    protected function configurePagination(): void
    {
        $request = service('request');
        $uri = service('uri');
        $currentUrl = current_url();

        $this->container->singleton('paginator.currentPage', function () {
            return $_GET['page'] ?? 1;
        });

        $this->container->singleton('paginator.currentPath', function () use ($currentUrl) {
            return $currentUrl;
        });

        $this->container->singleton('paginator.renderer', function () {
            return new PaginationRenderer;
        });

        Paginator::$defaultView = $this->paginationConfig->defaultView;
        Paginator::$defaultSimpleView = $this->paginationConfig->defaultSimpleView;

        Paginator::viewFactoryResolver(function () {
            return $this->container->get('paginator.renderer');
        });

        Paginator::currentPathResolver(function () use ($currentUrl) {
            return $currentUrl;
        });

        Paginator::currentPageResolver(function ($pageName = 'page') use ($request) {
            $page = $request->getVar($pageName);

            return (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) ? (int) $page : 1;
        });

        Paginator::queryStringResolver(function () use ($uri) {
            return $uri->getQuery();
        });

        CursorPaginator::currentCursorResolver(function ($cursorName = 'cursor') use ($request) {
            return Cursor::fromEncoded($request->getVar($cursorName));
        });
    }

    /**
     * Initialize the container and set it as the Facade application root
     */
    protected function setupContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    /**
     * Register required services in the container
     */
    protected function registerServices(): void
    {
        $this->registerConfigService();
        $this->registerHashService();
        $this->configurePagination();
    }

    /**
     * Register the configuration repository
     */
    protected function registerConfigService(): void
    {
        $this->container->singleton('config', function () {
            return new Repository([
                'hashing' => [
                    'driver' => 'bcrypt',
                    'bcrypt' => [
                        'rounds' => 10,
                    ],
                ],
            ]);
        });
    }

    /**
     * Register the hash manager service
     */
    protected function registerHashService(): void
    {
        $this->container->singleton('hash', function ($app) {
            return new HashManager($app);
        });
    }
}
