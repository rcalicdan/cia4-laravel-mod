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
        // 1) Load your config classes by FQCN (avoids CI4's own Pagination class)
        $this->paginationConfig = config(PaginationConfig::class);
        $this->eloquentConfig   = config(Eloquent::class);

        // 2) Always boot the DB connection and register services *up front*
        $this->setupDatabaseConnection();
        $this->setupContainer();
        $this->registerServices();
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

        $cached = [
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

        return $cached;
    }

    /**
     * Boot Eloquent by manually creating a PDO from our Eloquent config
     * (avoiding CI4’s DBDriver name conflicts).
     */
    protected function setupDatabaseConnection(): void
    {
        // 1) Grab & cache our DB config
        $config = $this->getDatabaseInformation();

        // 2) Initialize Capsule
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config);

        // 3) Build a native PDO
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_PERSISTENT         => true,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => ENVIRONMENT !== 'production',
        ];

        $pdo = new PDO($dsn, $config['username'], $config['password'], $options);

        // 4) Swap in that PDO
        $conn = $this->capsule->getConnection();
        $conn->setPdo($pdo);
        $conn->setReadPdo($pdo);

        // 5) Enable query logging only outside production
        if (ENVIRONMENT !== 'production') {
            $conn->enableQueryLog();
        }

        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * Initialize the IoC container and set it for Facades.
     */
    protected function setupContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    /**
     * Register config, hash, and pagination services **immediately**.
     */
    protected function registerServices(): void
    {
        // 1) Config repository (for HashManager, etc.)
        $this->container->singleton('config', fn() => new Repository([
            'hashing' => [
                'driver' => 'bcrypt',
                'bcrypt' => ['rounds' => 10],
            ],
        ]));

        // 2) Hash manager
        $this->container->singleton('hash', fn($app) => new HashManager($app));

        // 3) PaginationRenderer as a singleton
        $this->container->singleton(
            PaginationRenderer::class,
            fn() => new PaginationRenderer
        );
        $this->container->alias(
            PaginationRenderer::class,
            'paginator.renderer'
        );

        // 4) Configure Laravel’s Paginator *once*, now that the container is ready
        $this->configurePagination();
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
}
