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
        $this->configureQueryLogging();
        $this->bootEloquent();
    }

    protected function initCapsule(array $config): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($config);
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
     * Get database configuration with backward compatibility
     */
    public function getDatabaseInformation(?string $connection = null): array
    {
        if (self::$databaseConfig !== null && $connection === null) {
            return self::$databaseConfig;
        }

        $cfg = $this->getEloquentConfig();
        $connectionName = $connection ?? $this->getDefaultConnection();
        $baseConfig = $cfg->connections[$connectionName] ?? $cfg->connections['mysql'];
        $config = $this->applyEnvironmentOverrides($baseConfig, $connectionName);
        $config['options'] = $this->getPdoOptions();

        if ($config['driver'] === 'sqlite') {
            $config['database'] = $this->resolveSqlitePath($config['database']);
        }

        if ($connection === null) {
            self::$databaseConfig = $config;
        }

        return $config;
    }

    /**
     * Get the default connection name
     */
    protected function getDefaultConnection(): string
    {
        return env('DB_CONNECTION', env('database.default.connection', $this->getEloquentConfig()->default));
    }

    /**
     * Apply environment variable overrides with CodeIgniter compatibility
     */
    protected function applyEnvironmentOverrides(array $config, string $connectionName): array
    {
        $cfg = $this->getEloquentConfig();
        $isDefaultConnection = ($connectionName === $this->getDefaultConnection());

        if ($isDefaultConnection) {
            return [
                'driver' => env('DB_DRIVER', env('DB_DBDRIVER', env('database.default.DBDriver', $config['driver']))),
                'host' => env('DB_HOST', env('database.default.hostname', $config['host'])),
                'port' => env('DB_PORT', env('database.default.port', $config['port'])),
                'database' => env('DB_DATABASE', env('database.default.database', $config['database'])),
                'username' => env('DB_USERNAME', env('database.default.username', $config['username'])),
                'password' => env('DB_PASSWORD', env('database.default.password', $config['password'])),
                'charset' => env('DB_CHARSET', env('database.default.DBCharset', $config['charset'])),
                'collation' => env('DB_COLLATION', env('database.default.DBCollat', $config['collation'])),
                'prefix' => env('DB_PREFIX', env('database.default.DBPrefix', $config['prefix'])),
                'unix_socket' => env('DB_SOCKET', env('database.default.socket', $config['unix_socket'] ?? '')),
                'url' => env('DB_URL', $config['url'] ?? null),
                'strict' => env('DB_STRICT', $config['strict'] ?? true),
                'engine' => env('DB_ENGINE', $config['engine'] ?? null),
                'prefix_indexes' => $config['prefix_indexes'] ?? true,
                'search_path' => $config['search_path'] ?? 'public',
                'sslmode' => $config['sslmode'] ?? 'prefer',
                'foreign_key_constraints' => env('DB_FOREIGN_KEYS', $config['foreign_key_constraints'] ?? true),
                'busy_timeout' => $config['busy_timeout'] ?? null,
                'journal_mode' => $config['journal_mode'] ?? null,
                'synchronous' => $config['synchronous'] ?? null,
            ];
        }

        // For named connections, use connection-specific env variables
        $upperConnection = strtoupper($connectionName);
        $config['host'] = env("DB_{$upperConnection}_HOST", env("database.{$connectionName}.hostname", $config['host']));
        $config['port'] = env("DB_{$upperConnection}_PORT", env("database.{$connectionName}.port", $config['port']));
        $config['database'] = env("DB_{$upperConnection}_DATABASE", env("database.{$connectionName}.database", $config['database']));
        $config['username'] = env("DB_{$upperConnection}_USERNAME", env("database.{$connectionName}.username", $config['username']));
        $config['password'] = env("DB_{$upperConnection}_PASSWORD", env("database.{$connectionName}.password", $config['password']));
        $config['charset'] = env("DB_{$upperConnection}_CHARSET", env("database.{$connectionName}.DBCharset", $config['charset']));
        $config['prefix'] = env("DB_{$upperConnection}_PREFIX", env("database.{$connectionName}.DBPrefix", $config['prefix']));

        return $config;
    }

    /**
     * Resolve SQLite database path
     */
    protected function resolveSqlitePath(string $database): string
    {
        if (empty($database)) {
            return WRITEPATH . 'database.sqlite';
        }

        if (strpos($database, '/') === 0 || strpos($database, ':\\') === 1) {
            return $database;
        }

        if (str_contains($database, 'database_path')) {
            return WRITEPATH . str_replace('database_path(\'', '', str_replace('\')', '', $database));
        }

        return WRITEPATH . $database;
    }

    /**
     * Add support for multiple database connections
     */
    public function addConnection(string $name, ?array $config = null): void
    {
        if ($config === null) {
            $config = $this->getDatabaseInformation($name);
        }
        
        $this->capsule->addConnection($config, $name);
    }

    /**
     * Get connection configuration for a specific connection
     */
    public function getConnectionConfig(string $connection): array
    {
        return $this->getDatabaseInformation($connection);
    }

    // ... rest of your existing methods remain the same
    protected function initializeContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

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

    protected function registerObservers(): void
    {
        if (self::$observersRegistered) {
            return;
        }

        $observersConfig = config('Observers');
        if ($observersConfig) {
            $this->registerManualObservers($observersConfig);
            $this->registerAttributeObservers($observersConfig);
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

    protected function getEloquentModels(): array
    {
        if (self::$eloquentModels !== null) {
            return self::$eloquentModels;
        }

        $modelPath = APPPATH . 'Models/';
        if (!is_dir($modelPath)) {
            return self::$eloquentModels = [];
        }

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
        return class_exists($class, false) &&
            is_subclass_of($class, Model::class, false);
    }

    protected function processModelAttributes(string $modelClass): void
    {
        $reflection = new \ReflectionClass($modelClass);
        $attributes = $reflection->getAttributes(\Rcalicdan\Ci4Larabridge\Attributes\ObservedBy::class);

        foreach ($attributes as $attribute) {
            $this->registerObserversFromAttribute($modelClass, $attribute);
        }

        unset($reflection);
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

    public function __destruct()
    {
        unset($this->container, $this->capsule);
    }
}