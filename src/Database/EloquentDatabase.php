<?php

namespace Rcalicdan\Ci4Larabridge\Database;

use Config\Eloquent;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Hashing\HashManager;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Facade;
use PDO;
use Rcalicdan\Ci4Larabridge\Blade\PaginationRenderer;
use Rcalicdan\Ci4Larabridge\Config\Pagination as PaginationConfig;

/**
 * Manages the setup and configuration of Laravel's Eloquent ORM in a CodeIgniter 4 app.
 */
class EloquentDatabase
{
    protected Container $container;
    protected Capsule $capsule;
    protected PaginationConfig $paginationConfig;
    protected Eloquent $eloquentConfig;

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
        $this->eloquentConfig = config(Eloquent::class);
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
        $dsn = $this->buildDsn($config);
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
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => (ENVIRONMENT === 'development'),
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', env('database.default.persistent', true)),
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
                ->enableQueryLog()
            ;
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

        $cfg = $this->eloquentConfig;
        $cached = [
            'driver' => env('database.default.DBDriver',   env('DB_DBDRIVER',   $cfg->databaseDriver)),
            'host' => env('database.default.hostname',   env('DB_HOST',       $cfg->databaseHost)),
            'database' => env('database.default.database',   env('DB_DATABASE',   $cfg->databaseName)),
            'username' => env('database.default.username',   env('DB_USERNAME',   $cfg->databaseUsername)),
            'password' => env('database.default.password',   env('DB_PASSWORD',   $cfg->databasePassword)),
            'charset' => env('database.default.DBCharset',  env('DB_CHARSET',    $cfg->databaseCharset)),
            'collation' => env('database.default.DBCollat',   env('DB_COLLATION',  $cfg->databaseCollation)),
            'prefix' => env('database.default.DBPrefix',   env('DB_PREFIX',     $cfg->databasePrefix)),
            'port' => env('database.default.port',       env('DB_PORT',       $cfg->databasePort)),
        ];

        return $cached;
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
        $this->registerDatabaseService();
        $this->registerEventDispatcher();
        $this->bootEloquentModels();
    }

    /**
     * Register the event dispatcher for Eloquent events
     */
    protected function registerEventDispatcher(): void
    {
        $this->container->singleton('events', function ($app) {
            return new Dispatcher($app);
        });

        $this->capsule->setEventDispatcher($this->container['events']);
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
     * Register the database manager service.
     */
    protected function registerDatabaseService(): void
    {
        $this->container->singleton('db', fn() => $this->capsule->getDatabaseManager());
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
     * Boot all Eloquent models in the application
     */
    protected function bootEloquentModels(): void
    {
        $this->discoverAndBootModels();
    }

    /**
     * Discover and boot all Eloquent models
     */
    protected function discoverAndBootModels(): void
    {
        $modelPath = APPPATH . 'Models/';

        if (!is_dir($modelPath)) {
            return;
        }

        $files = glob($modelPath . '*.php');
        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file, $modelPath);

            if ($className && $this->isEloquentModel($className)) {
                $className::boot();
            }
        }
    }

    /**
     * Get class name from file path
     */
    protected function getClassNameFromFile(string $file, string $basePath): ?string
    {
        $relativePath = str_replace($basePath, '', $file);
        $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
        $fullClassName = 'App\\Models\\' . $className;

        return class_exists($fullClassName) ? $fullClassName : null;
    }

    /**
     * Check if class is an Eloquent model
     */
    protected function isEloquentModel(string $className): bool
    {
        try {
            return is_subclass_of($className, Model::class);
        } catch (\Throwable $e) {
            return false;
        }
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

        $request = service('request');
        $uri = service('uri');
        $currentUrl = current_url();

        Paginator::$defaultView = $this->paginationConfig->defaultView;
        Paginator::$defaultSimpleView = $this->paginationConfig->defaultSimpleView;

        Paginator::viewFactoryResolver(
            fn() => $this->container->get('paginator.renderer')
        );

        Paginator::currentPageResolver(
            fn($pageName = 'page') => ($page = $request->getVar($pageName))
                && filter_var($page, FILTER_VALIDATE_INT)
                && (int) $page >= 1
                ? (int) $page
                : 1
        );

        Paginator::currentPathResolver(fn() => $currentUrl);
        Paginator::queryStringResolver(fn() => $uri->getQuery());

        CursorPaginator::currentCursorResolver(
            fn($cursorName = 'cursor') => Cursor::fromEncoded($request->getVar($cursorName))
        );
    }
}
