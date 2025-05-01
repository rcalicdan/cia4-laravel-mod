<?php

namespace Rcalicdan\Ci4Larabridge\Database;

use Config\Eloquent;
use PDO;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Hashing\HashManager;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Facade;
use Rcalicdan\Ci4Larabridge\Blade\PaginationRenderer;
use Rcalicdan\Ci4Larabridge\Config\Pagination as PaginationConfig;

/**
 * Integrates Laravel's Eloquent ORM into a CodeIgniter 4 application,
 * handling configuration, bootstrapping, and performance tuning.
 *
 * @package Rcalicdan\Ci4Larabridge\Database
 */
class EloquentDatabase
{
    /**
     * @var Container IoC container for managing services and facades.
     */
    protected Container $container;

    /**
     * @var Capsule Manager for database connections and Eloquent bootstrapping.
     */
    protected Capsule $capsule;

    /**
     * @var PaginationConfig Application pagination view configurations.
     */
    protected PaginationConfig $paginationConfig;

    /**
     * @var Eloquent Configuration settings for the Eloquent connection.
     */
    protected Eloquent $eloquentConfig;

    /**
     * EloquentDatabase constructor.
     * Loads configuration, initializes the database, container, and required services.
     */
    public function __construct()
    {
        $this->loadConfigs();
        $this->initializeDatabase();
        $this->initializeContainer();
        $this->initializeServices();
    }

    /**
     * Retrieves Eloquent and pagination configurations from CodeIgniter.
     *
     * @return void
     */
    protected function loadConfigs(): void
    {
        $this->paginationConfig = config(PaginationConfig::class);
        $this->eloquentConfig   = config(Eloquent::class);
    }

    /**
     * Configures and boots the database connection via Eloquent,
     * then applies PDO performance optimizations.
     *
     * @return void
     */
    protected function initializeDatabase(): void
    {
        $config = $this->getDatabaseInformation();

        $this->initCapsule($config);
        $this->configureQueryLogging();
        $this->bootEloquent();
        $this->optimizePdoConnection();
    }

    /**
     * Instantiates the Capsule manager and adds the database connection.
     *
     * @param  array  $config  Connection settings for Eloquent.
     * @return void
     */
    protected function initCapsule(array $config): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config);
    }

    /**
     * Enables query logging when not in production environment.
     *
     * @return void
     */
    protected function configureQueryLogging(): void
    {
        if (ENVIRONMENT !== 'production') {
            $this->capsule
                 ->getConnection()
                 ->enableQueryLog();
        }
    }

    /**
     * Makes the Capsule instance globally available and boots Eloquent ORM.
     *
     * @return void
     */
    protected function bootEloquent(): void
    {
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * Applies PDO attributes for performance and reliability.
     *
     * @return void
     */
    protected function optimizePdoConnection(): void
    {
        $pdo = $this->capsule->getConnection()->getPdo();

        $pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, ENVIRONMENT === 'development');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_PERSISTENT, true);
    }

    /**
     * Builds and caches the database configuration array for Eloquent.
     *
     * @return array Database connection parameters.
     */
    public function getDatabaseInformation(): array
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $cfg = $this->eloquentConfig;
        $cached = [
            'driver'    => env('database.default.DBDriver',   env('DB_DRIVER',     $cfg->databaseDriver)),
            'host'      => env('database.default.hostname',   env('DB_HOST',       $cfg->databaseHost)),
            'database'  => env('database.default.database',   env('DB_DATABASE',   $cfg->databaseName)),
            'username'  => env('database.default.username',   env('DB_USERNAME',   $cfg->databaseUsername)),
            'password'  => env('database.default.password',   env('DB_PASSWORD',   $cfg->databasePassword)),
            'charset'   => env('database.default.DBCharset',  env('DB_CHARSET',    $cfg->databaseCharset)),
            'collation' => env('database.default.DBCollat',   env('DB_COLLATION',  $cfg->databaseCollation)),
            'prefix'    => env('database.default.DBPrefix',   env('DB_PREFIX',     $cfg->databasePrefix)),
            'port'      => env('database.default.port',       env('DB_PORT',       $cfg->databasePort)),
        ];

        return $cached;
    }

    /**
     * Sets up the IoC container and links it to Laravel facades.
     *
     * @return void
     */
    protected function initializeContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    /**
     * Registers configuration, hashing, and pagination services.
     *
     * @return void
     */
    protected function initializeServices(): void
    {
        $this->registerConfigService();
        $this->registerHashService();
        $this->registerPaginationRenderer();
        $this->configurePagination();
    }

    /**
     * Binds the configuration repository into the container.
     *
     * @return void
     */
    protected function registerConfigService(): void
    {
        $this->container->singleton('config', fn () => new Repository([
            'hashing' => [
                'driver' => 'bcrypt',
                'bcrypt' => ['rounds' => 10],
            ],
        ]));
    }

    /**
     * Binds the HashManager into the container for password hashing.
     *
     * @return void
     */
    protected function registerHashService(): void
    {
        $this->container->singleton('hash', fn ($app) => new HashManager($app));
    }

    /**
     * Registers the custom pagination renderer for Blade views.
     *
     * @return void
     */
    protected function registerPaginationRenderer(): void
    {
        $this->container->singleton(PaginationRenderer::class, fn () => new PaginationRenderer);
        $this->container->alias(PaginationRenderer::class, 'paginator.renderer');
    }

    /**
     * Configures Laravel paginator resolvers and default views.
     *
     * @return void
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

        Paginator::viewFactoryResolver(fn () => $this->container->get('paginator.renderer'));
        Paginator::currentPageResolver(fn ($pageName = 'page') =>
            ($page = $request->getVar($pageName))
            && filter_var($page, FILTER_VALIDATE_INT)
            && (int)$page >= 1
                ? (int)$page
                : 1
        );
        Paginator::currentPathResolver(fn () => $currentUrl);
        Paginator::queryStringResolver(fn () => $uri->getQuery());

        CursorPaginator::currentCursorResolver(fn ($cursorName = 'cursor') =>
            Cursor::fromEncoded($request->getVar($cursorName))
        );
    }
}
