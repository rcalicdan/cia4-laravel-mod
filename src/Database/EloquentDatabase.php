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
    protected static bool $observersRegistered = false;
    protected static bool $paginationConfigured = false;
    
    // Cache for expensive operations
    private static ?array $databaseConfig = null;
    private static ?array $eloquentModels = null;
    private static ?PaginationConfig $paginationConfig = null;
    private static ?Eloquent $eloquentConfig = null;

    public function __construct()
    {
        $this->initializeDatabase();
        $this->initializeContainer();
        $this->initializeServices();
    }

    /**
     * Load configs with lazy loading and caching
     */
    protected function getPaginationConfig(): PaginationConfig
    {
        return self::$paginationConfig ??= config(PaginationConfig::class);
    }

    protected function getEloquentConfig(): Eloquent
    {
        return self::$eloquentConfig ??= config(Eloquent::class);
    }

    protected function initializeDatabase(): void
    {
        $config = $this->getDatabaseInformation();
        $this->initCapsule($config);
        
        // Use existing CI4 database connection if available to avoid duplicate PDO
        if ($this->canReuseExistingConnection()) {
            $this->reuseExistingConnection();
        } else {
            $pdo = $this->createPdo($config);
            $this->attachPdo($pdo);
        }

        $this->configureQueryLogging();
        $this->bootEloquent();
    }

    /**
     * Check if we can reuse CI4's existing database connection
     */
    protected function canReuseExistingConnection(): bool
    {
        try {
            $db = \Config\Database::connect();
            return $db->connID instanceof PDO;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Reuse CI4's existing PDO connection
     */
    protected function reuseExistingConnection(): void
    {
        $db = \Config\Database::connect();
        $this->attachPdo($db->connID);
    }

    protected function initCapsule(array $config): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config);
    }

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

    protected function attachPdo(PDO $pdo): void
    {
        $conn = $this->capsule->getConnection();
        $conn->setPdo($pdo);
        $conn->setReadPdo($pdo);
    }

    protected function configureQueryLogging(): void
    {
        if (ENVIRONMENT !== 'production') {
            $this->capsule->getConnection()->enableQueryLog();
        }
    }

    protected function bootEloquent(): void
    {
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * Optimized database config with better caching
     */
    public function getDatabaseInformation(): array
    {
        if (self::$databaseConfig !== null) {
            return self::$databaseConfig;
        }

        $cfg = $this->getEloquentConfig();
        
        return self::$databaseConfig = [
            'driver' => env('database.default.DBDriver', env('DB_DBDRIVER', $cfg->databaseDriver)),
            'host' => env('database.default.hostname', env('DB_HOST', $cfg->databaseHost)),
            'database' => env('database.default.database', env('DB_DATABASE', $cfg->databaseName)),
            'username' => env('database.default.username', env('DB_USERNAME', $cfg->databaseUsername)),
            'password' => env('database.default.password', env('DB_PASSWORD', $cfg->databasePassword)),
            'charset' => env('database.default.DBCharset', env('DB_CHARSET', $cfg->databaseCharset)),
            'collation' => env('database.default.DBCollat', env('DB_COLLATION', $cfg->databaseCollation)),
            'prefix' => env('database.default.DBPrefix', env('DB_PREFIX', $cfg->databasePrefix)),
            'port' => env('database.default.port', env('DB_PORT', $cfg->databasePort)),
        ];
    }

    protected function initializeContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    protected function initializeServices(): void
    {
        // Register services lazily to avoid unnecessary instantiation
        $this->registerConfigService();
        $this->registerDatabaseService();
        $this->registerEventDispatcher();
        $this->registerHashService();
        $this->registerObservers();
        $this->registerPaginationRenderer();
        $this->configurePagination();
    }

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

    protected function registerManualObservers(object $config): void
    {
        $config->boot();
    }

    protected function registerAttributeObservers(object $config): void
    {
        if (!$config->useAttributes) {
            return;
        }

        foreach ($this->getEloquentModels() as $modelClass) {
            $this->processModelAttributes($modelClass);
        }
    }

    /**
     * Optimized model discovery with caching and early filtering
     */
    protected function getEloquentModels(): array
    {
        if (self::$eloquentModels !== null) {
            return self::$eloquentModels;
        }

        $modelPath = APPPATH . 'Models/';
        if (!is_dir($modelPath)) {
            return self::$eloquentModels = [];
        }

        // Use iterator for memory efficiency with large directories
        $iterator = new \DirectoryIterator($modelPath);
        $models = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $modelClass = $this->getModelClassFromFile($file->getPathname());
            if ($this->isValidEloquentModel($modelClass)) {
                $models[] = $modelClass;
            }
        }

        return self::$eloquentModels = $models;
    }

    protected function getModelClassFromFile(string $file): string
    {
        $modelName = basename($file, '.php');
        return "App\\Models\\{$modelName}";
    }

    protected function isValidEloquentModel(string $class): bool
    {
        return class_exists($class, false) && // Don't autoload unnecessarily
            is_subclass_of($class, Model::class, false);
    }

    protected function processModelAttributes(string $modelClass): void
    {
        $reflection = new \ReflectionClass($modelClass);
        $attributes = $reflection->getAttributes(\Rcalicdan\Ci4Larabridge\Attributes\ObservedBy::class);

        foreach ($attributes as $attribute) {
            $this->registerObserversFromAttribute($modelClass, $attribute);
        }
        
        // Free reflection memory
        unset($reflection);
    }

    protected function registerObserversFromAttribute(string $modelClass, \ReflectionAttribute $attribute): void
    {
        $observedBy = $attribute->newInstance();

        foreach ($observedBy->observers as $observer) {
            if (class_exists($observer, false)) { // Don't autoload
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

    protected function registerConfigService(): void
    {
        $this->container->singleton('config', function() {
            return new Repository([
                'hashing' => [
                    'driver' => 'bcrypt',
                    'bcrypt' => ['rounds' => 10],
                ],
            ]);
        });
    }

    protected function registerDatabaseService(): void
    {
        $this->container->singleton('db', fn() => $this->capsule->getDatabaseManager());
    }

    protected function registerHashService(): void
    {
        $this->container->singleton('hash', fn($app) => new HashManager($app));
    }

    protected function registerPaginationRenderer(): void
    {
        $this->container->singleton(
            PaginationRenderer::class,
            fn() => new PaginationRenderer
        );
        $this->container->alias(PaginationRenderer::class, 'paginator.renderer');
    }

    protected function configurePagination(): void
    {
        if (self::$paginationConfigured) {
            return;
        }
        self::$paginationConfigured = true;

        $request = service('request');
        $uri = service('uri');
        $paginationConfig = $this->getPaginationConfig();

        Paginator::$defaultView = $paginationConfig->defaultView;
        Paginator::$defaultSimpleView = $paginationConfig->defaultSimpleView;

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

        Paginator::currentPathResolver(fn() => current_url());
        Paginator::queryStringResolver(fn() => $uri->getQuery());

        CursorPaginator::currentCursorResolver(
            fn($cursorName = 'cursor') => Cursor::fromEncoded($request->getVar($cursorName))
        );
    }
    
    /**
     * Clean up resources when needed
     */
    public function __destruct()
    {
        // Help GC by breaking circular references
        unset($this->container, $this->capsule);
    }
}