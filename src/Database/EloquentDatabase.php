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

    public function getDatabaseInformation(?string $connection = null): array
    {
        if (self::$databaseConfig !== null && $connection === null) {
            return self::$databaseConfig;
        }

        $cfg = $this->getEloquentConfig();
        $connectionName = $connection ?? $this->getDefaultConnection();
        $config = $cfg->connections[$connectionName] ?? $cfg->connections['mysql'];
        
        if ($config['driver'] === 'sqlite') {
            $config = $this->resolveSqliteConfig($config);
        }
        
        $config['options'] = array_merge(
            $config['options'] ?? [],
            $this->getPdoOptions()
        );

        if ($connection === null) {
            self::$databaseConfig = $config;
        }

        return $config;
    }

    /**
     * Resolve SQLite database path
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
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $config;
    }

    private function isAbsolutePath(string $path): bool
    {
        return strpos($path, '/') === 0 || strpos($path, ':\\') === 1;
    }

    /**
     * Get the default connection name
     */
    protected function getDefaultConnection(): string
    {
        return env('DB_CONNECTION', env('database.default.connection', $this->getEloquentConfig()->default));
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
        if (! property_exists($config, 'useAttributes') || ! $config->useAttributes) {
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
        if (! is_dir($modelPath)) {
            return self::$eloquentModels = [];
        }

        $iterator = new \DirectoryIterator($modelPath);
        $models = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
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