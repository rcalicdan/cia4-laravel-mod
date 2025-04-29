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
 * Manages the setup and configuration of Laravel's Eloquent ORM in a CodeIgniter 4 app.
 */
class EloquentDatabase
{
    protected Container        $container;
    protected Capsule          $capsule;
    protected PaginationConfig $paginationConfig;
    protected Eloquent   $eloquentConfig;

    public function __construct()
    {
        $this->loadConfigs();
        $this->initializeDatabase();
        $this->initializeContainer();
        $this->initializeServices();
    }

    /**
     * Load our Eloquent and Pagination config instances.
     */
    protected function loadConfigs(): void
    {
        $this->paginationConfig = config(PaginationConfig::class);
        $this->eloquentConfig   = config(Eloquent::class);
    }

    /**
     * Boot Eloquent: set up Capsule, create and attach PDO, enable logging, then boot.
     */
    protected function initializeDatabase(): void
    {
        $config = $this->getDatabaseInformation();

        $this->initCapsule($config);

        $pdo = $this->createPdo($config);
        $this->attachPdo($pdo);

        $this->configureQueryLogging();
        $this->bootEloquent();
    }

    /**
     * Instantiate Capsule and add our connection config.
     */
    protected function initCapsule(array $config): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config);
    }

    /**
     * Build a native PDO instance according to our config.
     */
    protected function createPdo(array $config): PDO
    {
        $dsn     = $this->buildDsn($config);
        $options = $this->getPdoOptions();

        return new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $options
        );
    }

    /**
     * Assemble the DSN string for PDO.
     */
    protected function buildDsn(array $config): string
    {
        return sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );
    }

    /**
     * Return PDO options array, toggling emulation per environment.
     */
    protected function getPdoOptions(): array
    {
        return [
            PDO::ATTR_PERSISTENT         => true,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => ENVIRONMENT !== 'production',
        ];
    }

    /**
     * Swap our PDO into Eloquent's connection object.
     */
    protected function attachPdo(PDO $pdo): void
    {
        $conn = $this->capsule->getConnection();
        $conn->setPdo($pdo);
        $conn->setReadPdo($pdo);
    }

    /**
     * Enable query logging outside of production.
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
     * Make Capsule globally available and boot Eloquent.
     */
    protected function bootEloquent(): void
    {
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * Build (once) and return the DB config array Eloquent expects.
     */
    public function getDatabaseInformation(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        return $cached = [
            'driver'    => env('database.default.DBDriver',    $this->eloquentConfig->databaseDriver),
            'host'      => env('database.default.hostname',    $this->eloquentConfig->databaseHost),
            'database'  => env('database.default.database',    $this->eloquentConfig->databaseName),
            'username'  => env('database.default.username',    $this->eloquentConfig->databaseUsername),
            'password'  => env('database.default.password',    $this->eloquentConfig->databasePassword),
            'charset'   => env('database.default.DBCharset',   $this->eloquentConfig->databaseCharset),
            'collation' => env('database.default.DBCollat',    $this->eloquentConfig->databaseCollation),
            'prefix'    => env('database.default.DBPrefix',    $this->eloquentConfig->databasePrefix),
            'port'      => env('database.default.port',        $this->eloquentConfig->databasePort),
        ];
    }

    /**
     * Initialize the IoC container and set it for Facades.
     */
    protected function initializeContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    /**
     * Register config, hash, and pagination services.
     */
    protected function initializeServices(): void
    {
        $this->registerConfigService();
        $this->registerHashService();
        $this->registerPaginationRenderer();
        $this->configurePagination();
    }

    /**
     * Register the configuration repository.
     */
    protected function registerConfigService(): void
    {
        $this->container->singleton('config', fn() => new Repository([
            'hashing' => [
                'driver' => 'bcrypt',
                'bcrypt' => ['rounds' => 10],
            ],
        ]));
    }

    /**
     * Register the hash manager.
     */
    protected function registerHashService(): void
    {
        $this->container->singleton('hash', fn($app) => new HashManager($app));
    }

    /**
     * Bind the PaginationRenderer into the container.
     */
    protected function registerPaginationRenderer(): void
    {
        $this->container->singleton(
            PaginationRenderer::class,
            fn() => new PaginationRenderer
        );
        $this->container->alias(
            PaginationRenderer::class,
            'paginator.renderer'
        );
    }

    /**
     * One-time registration of pagination resolvers.
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

        Paginator::viewFactoryResolver(fn() =>
            $this->container->get('paginator.renderer')
        );

        Paginator::currentPageResolver(fn($pageName = 'page') =>
            ($page = $request->getVar($pageName))
                && filter_var($page, FILTER_VALIDATE_INT)
                && (int)$page >= 1
                ? (int)$page
                : 1
        );

        Paginator::currentPathResolver(fn() => $currentUrl);
        Paginator::queryStringResolver(fn() => $uri->getQuery());

        CursorPaginator::currentCursorResolver(fn($cursorName = 'cursor') =>
            Cursor::fromEncoded($request->getVar($cursorName))
        );
    }
}
