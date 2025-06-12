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
 * Optimized Eloquent Database Manager with performance enhancements
 */
class EloquentDatabase
{
    protected Container $container;
    protected Capsule $capsule;
    
    // Static flags for singleton behavior
    protected static bool $observersRegistered = false;
    protected static bool $paginationConfigured = false;
    protected static bool $servicesInitialized = false;

    // Cached configurations
    private static ?array $databaseConfig = null;
    private static ?array $eloquentModels = null;
    private static ?PaginationConfig $paginationConfig = null;
    private static ?Eloquent $eloquentConfig = null;
    private static ?array $pdoOptions = null;
    private static ?Container $sharedContainer = null;

    public function __construct()
    {
        $this->initializeDatabase();
        $this->initializeContainer();
        $this->initializeServices();
    }

    /**
     * Optimized config loading with better caching
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
        $this->configureQueryLogging();
        $this->bootEloquent();
    }

    protected function initCapsule(array $config): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config);
    }

    /**
     * Cached PDO options for better performance
     */
    protected function getPdoOptions(): array
    {
        if (self::$pdoOptions !== null) {
            return self::$pdoOptions;
        }

        return self::$pdoOptions = [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => ENVIRONMENT === 'development',
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => (bool) env('DB_PERSISTENT', env('database.default.persistent', true)),
        ];
    }

    /**
     * Optimized query logging configuration
     */
    protected function configureQueryLogging(): void
    {
        if (ENVIRONMENT !== 'production' && env('DB_DEBUG', true)) {
            $this->capsule->getConnection()->enableQueryLog();
        }
    }

    protected function bootEloquent(): void
    {
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    /**
     * Optimized database configuration with better caching strategy
     */
    public function getDatabaseInformation(?string $connection = null): array
    {
        $cacheKey = $connection ?? 'default';
        static $connectionConfigs = [];

        if (isset($connectionConfigs[$cacheKey])) {
            return $connectionConfigs[$cacheKey];
        }

        $cfg = $this->getEloquentConfig();
        $connectionName = $connection ?? $this->getDefaultConnection();
        $connections = $cfg->connections();
        $config = $connections[$connectionName] ?? $connections['mysql'];

        if ($config['driver'] === 'sqlite') {
            $config = $this->resolveSqliteConfig($config);
        }

        $config['options'] = array_merge(
            $config['options'] ?? [],
            $this->getPdoOptions()
        );

        $connectionConfigs[$cacheKey] = $config;

        if ($connection === null && self::$databaseConfig === null) {
            self::$databaseConfig = $config;
        }

        return $config;
    }

    /**
     * Optimized SQLite path resolution with better error handling
     */
    protected function resolveSqliteConfig(array $config): array
    {
        $dbPath = $config['database'];

        if (str_contains($dbPath, 'database_path(')) {
            $dbPath = str_replace(['database_path(\'', '\')'], '', $dbPath);
            $config['database'] = WRITEPATH . $dbPath;
        } elseif (!$this->isAbsolutePath($dbPath)) {
            $config['database'] = WRITEPATH . $dbPath;
        }

        $dir = dirname($config['database']);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create SQLite database directory: {$dir}");
        }

        return $config;
    }

    /**
     * Optimized path checking
     */
    private function isAbsolutePath(string $path): bool
    {
        return $path[0] === '/' || (strlen($path) > 1 && $path[1] === ':');
    }

    /**
     * Cached default connection resolution
     */
    protected function getDefaultConnection(): string
    {
        static $defaultConnection = null;
        
        if ($defaultConnection === null) {
            $cfg = $this->getEloquentConfig();
            $defaultConnection = env('DB_CONNECTION', env('database.default.connection', $cfg->default()));
        }
        
        return $defaultConnection;
    }

    /**
     * Optimized connection management
     */
    public function addConnection(string $name, ?array $config = null): void
    {
        if ($config === null) {
            $config = $this->getDatabaseInformation($name);
        }

        $this->capsule->addConnection($config, $name);
    }

    public function getConnectionConfig(string $connection): array
    {
        return $this->getDatabaseInformation($connection);
    }

    /**
     * Shared container for better memory usage
     */
    protected function initializeContainer(): void
    {
        if (self::$sharedContainer !== null) {
            $this->container = self::$sharedContainer;
            return;
        }

        $this->container = self::$sharedContainer = new Container;
        Facade::setFacadeApplication($this->container);
    }

    /**
     * Optimized service initialization with singleton pattern
     */
    protected function initializeServices(): void
    {
        if (self::$servicesInitialized) {
            return;
        }

        $this->registerConfigService();
        $this->registerDatabaseService();
        $this->registerEventDispatcher();
        $this->registerHashService();
        $this->registerObservers();
        $this->registerPaginationRenderer();
        $this->configurePagination();

        self::$servicesInitialized = true;
    }

    /**
     * Optimized observer registration
     */
    protected function registerObservers(): void
    {
        if (self::$observersRegistered) {
            return;
        }

        try {
            $observersConfig = config('Observers');
            if ($observersConfig) {
                $this->registerManualObservers($observersConfig);
                $this->registerAttributeObservers($observersConfig);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to register observers: ' . $e->getMessage());
        }

        self::$observersRegistered = true;
    }

    protected function registerManualObservers(object $config): void
    {
        if (method_exists($config, 'boot')) {
            $config->boot();
        }
    }

    protected function registerAttributeObservers(object $config): void
    {
        if (!property_exists($config, 'useAttributes') || !$config->useAttributes) {
            return;
        }

        foreach ($this->getEloquentModels() as $modelClass) {
            $this->processModelAttributes($modelClass);
        }
    }

    /**
     * Optimized model discovery with better caching and error handling
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

        try {
            $models = [];
            $files = new \FilesystemIterator($modelPath, \FilesystemIterator::SKIP_DOTS);
            
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $modelClass = $this->getModelClassFromFile($file->getPathname());
                if ($this->isValidEloquentModel($modelClass)) {
                    $models[] = $modelClass;
                }
            }

            return self::$eloquentModels = $models;
        } catch (\Throwable $e) {
            log_message('error', 'Failed to discover Eloquent models: ' . $e->getMessage());
            return self::$eloquentModels = [];
        }
    }

    protected function getModelClassFromFile(string $file): string
    {
        $modelName = basename($file, '.php');
        return "App\\Models\\{$modelName}";
    }

    /**
     * Optimized model validation
     */
    protected function isValidEloquentModel(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        static $modelCache = [];
        
        if (!isset($modelCache[$class])) {
            $modelCache[$class] = is_subclass_of($class, Model::class);
        }
        
        return $modelCache[$class];
    }

    /**
     * Optimized attribute processing with reflection caching
     */
    protected function processModelAttributes(string $modelClass): void
    {
        static $reflectionCache = [];
        
        if (!isset($reflectionCache[$modelClass])) {
            $reflectionCache[$modelClass] = new \ReflectionClass($modelClass);
        }
        
        $reflection = $reflectionCache[$modelClass];
        $attributes = $reflection->getAttributes(\Rcalicdan\Ci4Larabridge\Attributes\ObservedBy::class);

        foreach ($attributes as $attribute) {
            $this->registerObserversFromAttribute($modelClass, $attribute);
        }
    }

    protected function registerObserversFromAttribute(string $modelClass, \ReflectionAttribute $attribute): void
    {
        $observedBy = $attribute->newInstance();

        foreach ($observedBy->observers as $observer) {
            if (class_exists($observer)) {
                $modelClass::observe($observer);
            }
        }
    }

    /**
     * Optimized service registration with lazy loading
     */
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
        $this->container->singleton('config', function () {
            return new Repository([
                'hashing' => [
                    'driver' => 'bcrypt',
                    'bcrypt' => ['rounds' => env('HASH_ROUNDS', 10)],
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

    /**
     * Optimized pagination configuration
     */
    protected function configurePagination(): void
    {
        if (self::$paginationConfigured) {
            return;
        }

        $paginationConfig = $this->getPaginationConfig();
        
        Paginator::$defaultView = $paginationConfig->defaultView;
        Paginator::$defaultSimpleView = $paginationConfig->defaultSimpleView;

        Paginator::viewFactoryResolver(
            fn() => $this->container->get('paginator.renderer')
        );

        Paginator::currentPageResolver(function ($pageName = 'page') {
            static $pageCache = [];
            $cacheKey = $pageName;
            
            if (!isset($pageCache[$cacheKey])) {
                $request = service('request');
                $page = $request->getVar($pageName);
                $pageCache[$cacheKey] = $page && filter_var($page, FILTER_VALIDATE_INT) && (int) $page >= 1 
                    ? (int) $page 
                    : 1;
            }
            
            return $pageCache[$cacheKey];
        });

        Paginator::currentPathResolver(fn() => current_url());
        
        Paginator::queryStringResolver(function () {
            static $queryString = null;
            return $queryString ??= service('uri')->getQuery();
        });

        CursorPaginator::currentCursorResolver(function ($cursorName = 'cursor') {
            static $cursorCache = [];
            
            if (!isset($cursorCache[$cursorName])) {
                $request = service('request');
                $cursorData = $request->getVar($cursorName);
                $cursorCache[$cursorName] = $cursorData ? Cursor::fromEncoded($cursorData) : null;
            }
            
            return $cursorCache[$cursorName];
        });

        self::$paginationConfigured = true;
    }

    /**
     * Optimized cleanup
     */
    public function __destruct()
    {
        unset($this->capsule);
    }

    /**
     * Clear static caches for testing or memory management
     */
    public static function clearCaches(): void
    {
        self::$databaseConfig = null;
        self::$eloquentModels = null;
        self::$paginationConfig = null;
        self::$eloquentConfig = null;
        self::$pdoOptions = null;
        self::$observersRegistered = false;
        self::$paginationConfigured = false;
        self::$servicesInitialized = false;
    }
}