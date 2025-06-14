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
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate\SqliteHandler;
use Rcalicdan\Ci4Larabridge\Config\Pagination as PaginationConfig;

/**
 * Manages the setup and configuration of Laravel's Eloquent ORM in a CodeIgniter 4 app.
 * Optimized for performance with caching and lazy loading.
 */
final class EloquentDatabase
{
    protected Container $container;
    protected Capsule $capsule;

    /**
     * @var bool Flag indicating if observers are registered
     */
    protected static bool $observersRegistered = false;

    /**
     * @var bool Flag indicating if pagination is configured
     */
    protected static bool $paginationConfigured = false;

    /**
     * @var bool Flag indicating if global bootstrapping is done
     */
    protected static bool $globalBootstrapped = false;

    /**
     * @var array|null Cached configuration values
     */
    private static ?array $configCache = [];

    /**
     * @var array|null Cached environment variables
     */
    private static ?array $envCache = [];

    /**
     * @var array|null Cached database configurations
     */
    private static ?array $databaseConfigCache = [];

    /**
     * @var array|null Cached list of Eloquent models
     */
    private static ?array $eloquentModelsCache = null;

    /**
     * @var array|null Cached PDO options
     */
    private static ?array $pdoOptionsCache = null;

    /**
     * @var Container|null Shared container instance
     */
    private static ?Container $sharedContainer = null;

    /**
     * @var Capsule|null Shared database capsule instance
     */
    private static ?Capsule $sharedCapsule = null;

    /**
     * @var self|null Singleton instance
     */
    private static ?self $instance = null;

    /**
     * @var bool Flag indicating if services are initialized
     */
    private bool $servicesInitialized = false;

    /**
     * @var SqliteHandler|null SQLite handler instance
     */
    protected ?SqliteHandler $sqliteHandler = null;

    /**
     * @var array Pending observers to be registered
     */
    private array $pendingObservers = [];

    /**
     * @var array Models that have already been processed
     */
    private array $processedModels = [];

    public function __construct()
    {
        $this->initializeDatabase();
        $this->initializeContainer();
        $this->initializeServices();
    }

    /**
     * Get the singleton instance of EloquentDatabase
     * 
     * @return self The singleton instance
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Get a database connection instance
     * 
     * @param string|null $name Connection name
     * @return \Illuminate\Database\Connection The database connection
     */
    public static function getConnection(?string $name = null): \Illuminate\Database\Connection
    {
        return self::getInstance()->capsule->getConnection($name);
    }

    /**
     * Get the database manager instance
     * 
     * @return \Illuminate\Database\DatabaseManager The database manager
     */
    public static function getDatabaseManager(): \Illuminate\Database\DatabaseManager
    {
        return self::getInstance()->capsule->getDatabaseManager();
    }

    /**
     * Get the SQLite handler instance (lazy loaded)
     * 
     * @return SqliteHandler The SQLite handler
     */
    protected function getSqliteHandler(): SqliteHandler
    {
        return $this->sqliteHandler ??= new SqliteHandler;
    }

    /**
     * Get an environment variable with caching
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if not set
     * @return mixed The environment variable value
     */
    protected function env(string $key, $default = null)
    {
        return self::$envCache[$key] ??= env($key, $default);
    }

    /**
     * Get pagination configuration with caching
     * 
     * @return PaginationConfig The pagination configuration
     */
    protected function getPaginationConfig(): PaginationConfig
    {
        return self::$configCache['pagination'] ??= config(PaginationConfig::class);
    }

    protected function getEloquentConfig(): Eloquent
    {
        return self::$configCache['eloquent'] ??= config(Eloquent::class);
    }

    protected function getObserversConfig(): ?object
    {
        return self::$configCache['observers'] ??= config('Observers');
    }

    protected function initializeDatabase(): void
    {
        if (self::$sharedCapsule !== null) {
            $this->capsule = self::$sharedCapsule;

            return;
        }

        $config = $this->getDatabaseInformation();
        $this->initCapsule($config);
        $this->configureQueryLogging();
        $this->bootEloquent();

        self::$sharedCapsule = $this->capsule;
    }

    protected function initCapsule(array $config): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config);
    }

    protected function getPdoOptions(): array
    {
        if (self::$pdoOptionsCache !== null) {
            return self::$pdoOptionsCache;
        }

        return self::$pdoOptionsCache = [
            PDO::ATTR_CASE => PDO::CASE_NATURAL,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => (ENVIRONMENT === 'development'),
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => $this->env('DB_PERSISTENT', $this->env('database.default.persistent', true)),
        ];
    }

    protected function configureQueryLogging(): void
    {
        if (ENVIRONMENT !== 'production') {
            $this->capsule->getConnection()->enableQueryLog();
        }
    }

    protected function bootEloquent(): void
    {
        if (self::$globalBootstrapped) {
            return;
        }

        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        self::$globalBootstrapped = true;
    }

    public function getDatabaseInformation(?string $connection = null): array
    {
        $cacheKey = $connection ?? 'default';

        if (isset(self::$databaseConfigCache[$cacheKey])) {
            return self::$databaseConfigCache[$cacheKey];
        }

        $cfg = $this->getEloquentConfig();
        $connectionName = $connection ?? $this->getDefaultConnection();
        $baseConfig = $cfg->connections[$connectionName] ?? $cfg->connections['mysql'];
        $config = $this->applyEnvironmentOverrides($baseConfig, $connectionName);
        $config['options'] = $this->getPdoOptions();

        // Use SQLite handler for SQLite-specific path resolution
        if (strtolower($config['driver']) === 'sqlite') {
            $config = $this->getSqliteHandler()->prepareConfig($config);
        }

        return self::$databaseConfigCache[$cacheKey] = $config;
    }

    /**
     * Get the default connection name (cached)
     */
    protected function getDefaultConnection(): string
    {
        if (! isset(self::$envCache['default_connection'])) {
            self::$envCache['default_connection'] = $this->env(
                'DB_CONNECTION',
                $this->env('database.default.connection', $this->getEloquentConfig()->default)
            );
        }

        return self::$envCache['default_connection'];
    }

    /**
     * Apply environment variable overrides with CodeIgniter compatibility (optimized)
     */
    protected function applyEnvironmentOverrides(array $config, string $connectionName): array
    {
        $isDefaultConnection = $connectionName === $this->getDefaultConnection();

        if ($isDefaultConnection) {
            return [
                'driver' => $this->env('DB_DRIVER', $this->env('DB_DBDRIVER', $this->env('database.default.DBDriver', $config['driver']))),
                'host' => $this->env('DB_HOST', $this->env('database.default.hostname', $config['host'])),
                'port' => $this->env('DB_PORT', $this->env('database.default.port', $config['port'])),
                'database' => $this->env('DB_DATABASE', $this->env('database.default.database', $config['database'])),
                'username' => $this->env('DB_USERNAME', $this->env('database.default.username', $config['username'])),
                'password' => $this->env('DB_PASSWORD', $this->env('database.default.password', $config['password'])),
                'charset' => $this->env('DB_CHARSET', $this->env('database.default.DBCharset', $config['charset'])),
                'collation' => $this->env('DB_COLLATION', $this->env('database.default.DBCollat', $config['collation'])),
                'prefix' => $this->env('DB_PREFIX', $this->env('database.default.DBPrefix', $config['prefix'])),
                'unix_socket' => $this->env('DB_SOCKET', $this->env('database.default.socket', $config['unix_socket'] ?? '')),
                'url' => $this->env('DB_URL', $config['url'] ?? null),
                'strict' => $this->env('DB_STRICT', $config['strict'] ?? true),
                'engine' => $this->env('DB_ENGINE', $config['engine'] ?? null),
                'prefix_indexes' => $config['prefix_indexes'] ?? true,
                'search_path' => $config['search_path'] ?? 'public',
                'sslmode' => $config['sslmode'] ?? 'prefer',
                'foreign_key_constraints' => $this->env('DB_FOREIGN_KEYS', $config['foreign_key_constraints'] ?? true),
                'busy_timeout' => $config['busy_timeout'] ?? null,
                'journal_mode' => $config['journal_mode'] ?? null,
                'synchronous' => $config['synchronous'] ?? null,
            ];
        }

        $upperConnection = strtoupper($connectionName);
        $config['host'] = $this->env("DB_{$upperConnection}_HOST", $this->env("database.{$connectionName}.hostname", $config['host']));
        $config['port'] = $this->env("DB_{$upperConnection}_PORT", $this->env("database.{$connectionName}.port", $config['port']));
        $config['database'] = $this->env("DB_{$upperConnection}_DATABASE", $this->env("database.{$connectionName}.database", $config['database']));
        $config['username'] = $this->env("DB_{$upperConnection}_USERNAME", $this->env("database.{$connectionName}.username", $config['username']));
        $config['password'] = $this->env("DB_{$upperConnection}_PASSWORD", $this->env("database.{$connectionName}.password", $config['password']));
        $config['charset'] = $this->env("DB_{$upperConnection}_CHARSET", $this->env("database.{$connectionName}.DBCharset", $config['charset']));
        $config['prefix'] = $this->env("DB_{$upperConnection}_PREFIX", $this->env("database.{$connectionName}.DBPrefix", $config['prefix']));

        return $config;
    }

    /**
     * Add a new database connection
     * 
     * @param string $name Connection name
     * @param array|null $config Connection configuration
     * @return void
     */
    public function addConnection(string $name, ?array $config = null): void
    {
        if ($config === null) {
            $config = $this->getDatabaseInformation($name);
        }

        $this->capsule->addConnection($config, $name);
    }

    /**
     * Get configuration for a specific database connection
     * 
     * @param string $connection Connection name
     * @return array The connection configuration
     */
    public function getConnectionConfig(string $connection): array
    {
        return $this->getDatabaseInformation($connection);
    }

    protected function initializeContainer(): void
    {
        if (self::$sharedContainer !== null) {
            $this->container = self::$sharedContainer;

            return;
        }

        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
        self::$sharedContainer = $this->container;
    }

    /**
     * Ensure all services are initialized (lazy loading)
     * 
     * @return void
     */
    protected function ensureServicesInitialized(): void
    {
        if (! $this->servicesInitialized) {
            $this->initializeServices();
            $this->servicesInitialized = true;
        }
    }

    /**
     * Boot all services when needed
     * 
     * @return void
     */
    public function bootServices(): void
    {
        $this->ensureServicesInitialized();
    }

    protected function initializeServices(): void
    {
        $this->registerConfigService();
        $this->registerDatabaseService();
        $this->registerEventDispatcher();
        $this->registerHashService();
        $this->prepareObservers(); // Changed to prepare instead of register
        $this->registerPaginationRenderer();
        $this->configurePagination();
    }

    /**
     * Prepare observers for lazy registration
     * 
     * @return void
     */
    protected function prepareObservers(): void
    {
        if (self::$observersRegistered) {
            return;
        }

        $observersConfig = $this->getObserversConfig();
        if ($observersConfig) {
            $this->pendingObservers = (array) $observersConfig;
            $this->registerManualObservers($observersConfig);
        }

        self::$observersRegistered = true;
    }

    /**
     * Register observers for a specific model
     * 
     * @param string $modelClass The model class name
     * @return void
     */
    public function registerObserversForModel(string $modelClass): void
    {
        if (in_array($modelClass, $this->processedModels) || empty($this->pendingObservers)) {
            return;
        }

        $this->processModelAttributes($modelClass);
        $this->processedModels[] = $modelClass;
    }

    protected function registerManualObservers(object $config): void
    {
        if (method_exists($config, 'boot')) {
            $config->boot();
        }
    }

    /**
     * Register attribute observers (called when needed, not upfront)
     */
    protected function registerAttributeObservers(): void
    {
        $observersConfig = $this->getObserversConfig();
        if (! $observersConfig || ! property_exists($observersConfig, 'useAttributes') || ! $observersConfig->useAttributes) {
            return;
        }

        foreach ($this->getEloquentModels() as $modelClass) {
            $this->registerObserversForModel($modelClass);
        }
    }

    /**
     * Optimized model discovery with APCu caching
     */
    protected function getEloquentModels(): array
    {
        if (self::$eloquentModelsCache !== null) {
            return self::$eloquentModelsCache;
        }

        // Try APCu cache first (production only)
        $cacheKey = 'eloquent_models_'.md5(APPPATH.filemtime(APPPATH.'Models'));
        if (function_exists('apcu_fetch') && ENVIRONMENT === 'production') {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                return self::$eloquentModelsCache = $cached;
            }
        }

        $models = $this->discoverModels();

        // Cache the results in APCu (production only)
        if (function_exists('apcu_store') && ENVIRONMENT === 'production') {
            apcu_store($cacheKey, $models, 3600);
        }

        return self::$eloquentModelsCache = $models;
    }

    /**
     * Optimized model discovery using glob
     */
    private function discoverModels(): array
    {
        $modelPath = APPPATH.'Models/';
        if (! is_dir($modelPath)) {
            return [];
        }

        // Use glob for better performance than DirectoryIterator
        $files = glob($modelPath.'*.php');
        if ($files === false) {
            return [];
        }

        $models = [];
        foreach ($files as $file) {
            $modelClass = $this->getModelClassFromFile($file);
            if ($this->isValidEloquentModel($modelClass)) {
                $models[] = $modelClass;
            }
        }

        return $models;
    }

    protected function getModelClassFromFile(string $file): string
    {
        $modelName = basename($file, '.php');

        return "App\\Models\\{$modelName}";
    }

    protected function isValidEloquentModel(string $class): bool
    {
        return class_exists($class, false) && is_subclass_of($class, Model::class, false);
    }

    protected function processModelAttributes(string $modelClass): void
    {
        if (! class_exists($modelClass)) {
            return;
        }

        $reflection = new \ReflectionClass($modelClass);
        $attributes = $reflection->getAttributes(\Rcalicdan\Ci4Larabridge\Attributes\ObservedBy::class);

        foreach ($attributes as $attribute) {
            $this->registerObserversFromAttribute($modelClass, $attribute);
        }
    }

    protected function registerObserversFromAttribute(string $modelClass, \ReflectionAttribute $attribute): void
    {
        $observedBy = $attribute->newInstance();

        foreach ($observedBy->observers as $observer) {
            if (class_exists($observer, false)) {
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
        $this->container->singleton('config', function () {
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
        $this->container->singleton('db', fn () => $this->capsule->getDatabaseManager());
    }

    protected function registerHashService(): void
    {
        $this->container->singleton('hash', fn ($app) => new HashManager($app));
    }

    protected function registerPaginationRenderer(): void
    {
        $this->container->singleton(
            PaginationRenderer::class,
            fn () => new PaginationRenderer
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

        $container = $this->container;
        Paginator::viewFactoryResolver(
            fn () => $container->get('paginator.renderer')
        );

        Paginator::currentPageResolver(
            function ($pageName = 'page') use ($request) {
                $page = $request->getVar($pageName);

                return $page
                    && filter_var($page, FILTER_VALIDATE_INT) !== false
                    && (int) $page >= 1
                    ? (int) $page
                    : 1;
            }
        );

        Paginator::currentPathResolver(fn () => current_url());
        Paginator::queryStringResolver(fn () => $uri->getQuery());

        CursorPaginator::currentCursorResolver(
            function ($cursorName = 'cursor') use ($request) {
                $cursor = $request->getVar($cursorName);

                return $cursor ? Cursor::fromEncoded($cursor) : null;
            }
        );
    }

    /**
     * Force registration of all observers
     * 
     * @return void
     */
    public function forceRegisterAllObservers(): void
    {
        $this->ensureServicesInitialized();
        $this->registerAttributeObservers();
    }

    /**
     * Clear all cached data
     * 
     * @return void
     */
    public static function clearCaches(): void
    {
        self::$configCache = [];
        self::$envCache = [];
        self::$databaseConfigCache = [];
        self::$eloquentModelsCache = null;
        self::$pdoOptionsCache = null;

        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
    }

    /**
     * Get current cache status information
     * 
     * @return array Cache status information
     */
    public static function getCacheStatus(): array
    {
        return [
            'config_cache_count' => count(self::$configCache),
            'env_cache_count' => count(self::$envCache),
            'database_config_cache_count' => count(self::$databaseConfigCache),
            'models_cached' => self::$eloquentModelsCache !== null,
            'pdo_options_cached' => self::$pdoOptionsCache !== null,
            'shared_container' => self::$sharedContainer !== null,
            'shared_capsule' => self::$sharedCapsule !== null,
        ];
    }

    public function __destruct()
    {
        unset($this->sqliteHandler);
    }
}