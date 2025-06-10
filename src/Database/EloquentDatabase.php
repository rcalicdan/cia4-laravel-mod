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
    protected static bool $observersRegistered = false;

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
        $this->registerDatabaseService();
        $this->registerEventDispatcher();
        $this->registerHashService();
        $this->registerObservers();
        $this->registerPaginationRenderer();
        $this->configurePagination();
    }

    /**
     * Register observers for Eloquent models
     */
    protected function registerObservers(): void
    {
        if (self::$observersRegistered) {
            return;
        }

        $observersConfig = config('Observers');

        $this->registerManualObservers($observersConfig);
        $this->registerAttributeObservers($observersConfig);

        self::$observersRegistered = true;
    }

    /**
     * Register observers manually defined in boot() method
     */
    protected function registerManualObservers(object $config): void
    {
        $config->boot();
    }

    /**
     * Register observers using PHP 8 attributes
     */
    protected function registerAttributeObservers(object $config): void
    {
        if (!$config->useAttributes) {
            return;
        }

        $models = $this->getEloquentModels();

        foreach ($models as $modelClass) {
            $this->processModelAttributes($modelClass);
        }
    }

    /**
     * Get all Eloquent models from the Models directory
     */
    protected function getEloquentModels(): array
    {
        $modelPath = APPPATH . 'Models/';

        if (!is_dir($modelPath)) {
            return [];
        }

        $modelFiles = glob($modelPath . '*.php');
        $models = [];

        foreach ($modelFiles as $file) {
            $modelClass = $this->getModelClassFromFile($file);

            if ($this->isValidEloquentModel($modelClass)) {
                $models[] = $modelClass;
            }
        }

        return $models;
    }

    /**
     * Extract model class name from file path
     */
    protected function getModelClassFromFile(string $file): string
    {
        $modelName = basename($file, '.php');
        return "App\\Models\\{$modelName}";
    }

    /**
     * Check if class is a valid Eloquent model
     */
    protected function isValidEloquentModel(string $class): bool
    {
        return class_exists($class) &&
            is_subclass_of($class, Model::class);
    }

    /**
     * Process attributes for a specific model
     */
    protected function processModelAttributes(string $modelClass): void
    {
        $reflection = new \ReflectionClass($modelClass);
        $attributes = $reflection->getAttributes(\Rcalicdan\Ci4Larabridge\Attributes\ObservedBy::class);

        foreach ($attributes as $attribute) {
            $this->registerObserversFromAttribute($modelClass, $attribute);
        }
    }

    /**
     * Register observers from a single attribute instance
     */
    protected function registerObserversFromAttribute(string $modelClass, \ReflectionAttribute $attribute): void
    {
        $observedBy = $attribute->newInstance();

        foreach ($observedBy->observers as $observer) {
            if (class_exists($observer)) {
                $modelClass::observe($observer);
            }
        }
    }

    protected function registerEventDispatcher(): void
    {
        $this->container->singleton('events', function ($app) {
            return new Dispatcher($app);
        });

        $this->capsule->setEventDispatcher($this->container['events']);

        Model::setEventDispatcher($this->container['events']);
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
