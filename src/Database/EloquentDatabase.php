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

/**
 * Manages the setup and configuration of Laravel's Eloquent ORM in a CodeIgniter 4 application.
 */
class EloquentDatabase
{
    /**
     * The IoC container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The Eloquent database capsule instance.
     *
     * @var Capsule
     */
    protected $capsule;

    /**
     * Tracks whether services have been registered
     * 
     * @var bool
     */
    protected $servicesRegistered = false;

    /**
     * Tracks if database connection is initialized
     * 
     * @var bool
     */
    protected $connectionInitialized = false;

    /**
     * Configuration cache
     * 
     * @var array
     */
    protected $databaseConfig = null;

    /**
     * Initialize basic container setup but defer heavy operations
     */
    public function __construct()
    {
        // Only set up the container, defer the rest until needed
        $this->setupContainer();
    }

    /**
     * Initializes the IoC container and sets it as the Facade application root.
     */
    protected function setupContainer(): void
    {
        // Check if container exists in a static/shared property to avoid duplicate containers
        static $sharedContainer = null;

        if ($sharedContainer === null) {
            $sharedContainer = new Container;
            Facade::setFacadeApplication($sharedContainer);
        }

        $this->container = $sharedContainer;
    }

    /**
     * Get the database capsule instance, initializing if necessary
     */
    public function getCapsule(): Capsule
    {
        if ($this->capsule === null) {
            $this->setupDatabaseConnection();
        }

        return $this->capsule;
    }

    /**
     * Configures and initializes the Eloquent database connection.
     */
    protected function setupDatabaseConnection(): void
    {
        if ($this->connectionInitialized) {
            return;
        }

        $this->capsule = new Capsule;
        $this->capsule->addConnection($this->getDatabaseInformation());
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        // Only enable query logging in development when needed
        if (ENVIRONMENT === 'development' && config('Toolbar.enabled', false)) {
            $connection = $this->capsule->connection();
            $connection->enableQueryLog();
            $connection->getPdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
        }

        $this->connectionInitialized = true;
    }

    /**
     * Retrieves database connection information with lazy loading.
     */
    public function getDatabaseInformation(): array
    {
        if ($this->databaseConfig !== null) {
            return $this->databaseConfig;
        }

        // Lazy load the config
        $eloquentConfig = config('Eloquent');

        $this->databaseConfig = [
            'host' => env('database.default.hostname', $eloquentConfig->databaseHost),
            'driver' => env('database.default.DBDriver', $eloquentConfig->databaseDriver),
            'database' => env('database.default.database', $eloquentConfig->databaseName),
            'username' => env('database.default.username', $eloquentConfig->databaseUsername),
            'password' => env('database.default.password', $eloquentConfig->databasePassword),
            'charset' => env('database.default.DBCharset', $eloquentConfig->databaseCharset),
            'collation' => env('database.default.DBCollat', $eloquentConfig->databaseCollation),
            'prefix' => env('database.default.DBPrefix', $eloquentConfig->databasePrefix),
            'port' => env('database.default.port', $eloquentConfig->databasePort),
        ];

        return $this->databaseConfig;
    }

    /**
     * Registers required services in the container, only when needed.
     */
    public function registerServices(): void
    {
        if ($this->servicesRegistered) {
            return;
        }

        $this->registerConfigService();
        $this->registerHashService();

        $this->servicesRegistered = true;
    }

    /**
     * Configures pagination settings for the application.
     */
    public function configurePagination(): void
    {
        // Ensure services are registered first
        $this->registerServices();

        // Don't run this more than once
        static $paginationConfigured = false;
        if ($paginationConfigured) {
            return;
        }

        $request = service('request');
        $uri = service('uri');
        $currentUrl = current_url();
        $paginationConfig = config('Pagination');

        $this->container->singleton('paginator.currentPage', function () {
            return $_GET['page'] ?? 1;
        });

        $this->container->singleton('paginator.currentPath', function () use ($currentUrl) {
            return $currentUrl;
        });

        $this->container->singleton('paginator.renderer', function () {
            return new PaginationRenderer;
        });

        Paginator::$defaultView = $paginationConfig->defaultView;
        Paginator::$defaultSimpleView = $paginationConfig->defaultSimpleView;

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

        $paginationConfigured = true;
    }

    /**
     * Registers the configuration repository service.
     */
    protected function registerConfigService(): void
    {
        // Only register if not already defined
        if (!$this->container->bound('config')) {
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
    }

    /**
     * Registers the hash manager service.
     */
    protected function registerHashService(): void
    {
        // Only register if not already defined
        if (!$this->container->bound('hash')) {
            $this->container->singleton('hash', function ($app) {
                return new HashManager($app);
            });
        }
    }
}
