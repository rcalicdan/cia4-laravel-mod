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
 * Manages the setup and configuration of Laravel's Eloquent ORM in a CodeIgniter 4 application.
 *
 * This class initializes the Eloquent database connection, configures pagination, and registers
 * necessary services such as configuration and hashing. It integrates Laravel's features like
 * Eloquent, pagination, and facades with CodeIgniter's environment, supporting development
 * query logging and flexible configuration through environment variables or config files.
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
     * Pagination configuration values.
     *
     * @var Pagination
     */
    protected $paginationConfig;

    /**
     * Eloquent configuration values.
     *
     * @var Eloquent
     */
    protected $eloquentConfig;

    /**
     * Initializes the Eloquent database setup.
     *
     * Loads configuration, sets up the database connection, initializes the container,
     * and registers required services.
     */
    public function __construct()
    {
        $this->paginationConfig = config('Pagination');
        $this->eloquentConfig = config('Eloquent');
        $this->setupDatabaseConnection();
        $this->setupContainer();
        $this->registerServices();
    }

    /**
     * Configures and initializes the Eloquent database connection.
     *
     * Sets up the database connection using Capsule, makes it globally available,
     * and boots Eloquent. Enables query logging in development mode.
     *
     * @return void
     */
    protected function setupDatabaseConnection(): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($this->getDatabaseInformation());
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        // $this->getDatabaseLog();
    }

    /**
     * Enables query logging in development mode.
     *
     * Configures the database connection to log queries and sets PDO attributes for
     * emulated prepares when in development environment.
     *
     * @return void
     */
    // public function getDatabaseLog(): void
    // {
    //     if (ENVIRONMENT === 'development') {
    //         $connection = $this->capsule->connection();
    //         $connection->enableQueryLog();
    //         $connection->getPdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
    //     }
    // }

    /**
     * Retrieves database connection information.
     *
     * Gathers database configuration from environment variables or Eloquent config,
     * including host, driver, database name, credentials, and other settings.
     *
     * @return array Database configuration array.
     */
    public function getDatabaseInformation(): array
    {
        return [
            'host' => env('database.default.hostname', $this->eloquentConfig->databaseHost),
            'driver' => env('database.default.DBDriver', $this->eloquentConfig->databaseDriver),
            'database' => env('database.default.database', $this->eloquentConfig->databaseName),
            'username' => env('database.default.username', $this->eloquentConfig->databaseUsername),
            'password' => env('database.default.password', $this->eloquentConfig->databasePassword),
            'charset' => env('database.default.DBCharset', $this->eloquentConfig->databaseCharset),
            'collation' => env('database.default.DBCollat', $this->eloquentConfig->databaseCollation),
            'prefix' => env('database.default.DBPrefix', $this->eloquentConfig->databasePrefix),
            'port' => env('database.default.port', $this->eloquentConfig->databasePort),
        ];
    }

    /**
     * Configures pagination settings for the application.
     *
     * Sets up pagination resolvers for current page, path, query string, and cursor,
     * and configures the view factory and default views for pagination rendering.
     *
     * @return void
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
     * Initializes the IoC container and sets it as the Facade application root.
     *
     * Creates a new Container instance and configures it for use with Laravel's Facade system.
     *
     * @return void
     */
    protected function setupContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    /**
     * Registers required services in the container.
     *
     * Registers configuration and hash services, and configures pagination settings.
     *
     * @return void
     */
    protected function registerServices(): void
    {
        $this->registerConfigService();
        $this->registerHashService();
        $this->configurePagination();
    }

    /**
     * Registers the configuration repository service.
     *
     * Sets up a singleton instance of the configuration repository with default
     * hashing settings for bcrypt.
     *
     * @return void
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
     * Registers the hash manager service.
     *
     * Sets up a singleton instance of the HashManager for use in the application.
     *
     * @return void
     */
    protected function registerHashService(): void
    {
        $this->container->singleton('hash', function ($app) {
            return new HashManager($app);
        });
    }
}
