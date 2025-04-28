<?php

namespace Rcalicdan\Ci4Larabridge\Database;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Hashing\HashManager;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Facade;
use Rcalicdan\Ci4Larabridge\Blade\PaginationRenderer;
use Rcalicdan\Ci4Larabridge\Config\Eloquent;
use Rcalicdan\Ci4Larabridge\Config\Pagination;

/**
 * Optimized Eloquent Database Manager
 */
class EloquentDatabase
{
    /**
     * The IoC container instance.
     *
     * @var Container|null
     */
    protected $container = null;

    /**
     * The Eloquent database capsule instance.
     *
     * @var Capsule
     */
    protected $capsule;

    /**
     * Pagination configuration values.
     *
     * @var Pagination|null
     */
    protected $paginationConfig = null;

    /**
     * Eloquent configuration values.
     *
     * @var Eloquent
     */
    protected $eloquentConfig;

    /**
     * Track if services have been initialized
     * 
     * @var array
     */
    protected $initialized = [
        'container' => false,
        'config' => false,
        'hash' => false,
        'pagination' => false
    ];

    /**
     * Initializes only the essential Eloquent database connection.
     */
    public function __construct()
    {
        $this->eloquentConfig = config('Eloquent');
        $this->setupDatabaseConnection();
        
        // Only initialize facade system if needed by the app
        if ($this->shouldInitializeFacades()) {
            $this->setupContainer();
        }
    }

    /**
     * Check if the application needs facades
     * This can be controlled via config or detected dynamically
     */
    protected function shouldInitializeFacades(): bool
    {
        // Check if any code in the stack trace is using Facades
        // This is a simple way to detect if Facades are needed
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $call) {
            if (isset($call['class']) && strpos($call['class'], 'Illuminate\\Support\\Facades\\') === 0) {
                return true;
            }
        }
        
        // Or check a config value
        return config('Larabridge')->enableFacades ?? false;
    }

    /**
     * Configures and initializes the Eloquent database connection.
     *
     * Sets up the database connection using Capsule and boots Eloquent.
     * Only enables query logging if explicitly configured.
     */
    protected function setupDatabaseConnection(): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($this->getDatabaseInformation());
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
        
        // Only enable query logging when explicitly requested
        if ($this->shouldEnableQueryLogging()) {
            $this->enableQueryLog();
        }
    }

    /**
     * Determines if query logging should be enabled
     */
    protected function shouldEnableQueryLogging(): bool
    {
        // Check config first, then fall back to environment
        $config = config('Larabridge');
        if (isset($config->enableQueryLog)) {
            return $config->enableQueryLog;
        }
        
        // Default to only enabling in development
        return ENVIRONMENT === 'development';
    }

    /**
     * Enables query logging
     */
    protected function enableQueryLog(): void
    {
        $connection = $this->capsule->connection();
        $connection->enableQueryLog();
        $connection->getPdo()->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
    }

    /**
     * Retrieves database connection information.
     */
    public function getDatabaseInformation(): array
    {
        return [
            'host' => env('database.default.hostname', $this->eloquentConfig->databaseHost),
            'driver' => env('database.default.DBDriver', $this->eloquentConfig->databaseDriver),
            'database' => env('database.default.database', $this->eloquentConfig->databaseName),
            'username' => env('database.default.username', $this->eloquentConfig->databaseUsername),
            'password' => env('database.default.password', $this->eloquentConfig->databasePassword),
            'charset' => env('database.default.DBCharset', $this->eloquentConfig->databaseCharset),
            'collation' => env('database.default.DBCollat', $this->eloquentConfig->databaseCollation),
            'prefix' => env('database.default.DBPrefix', $this->eloquentConfig->databasePrefix),
            'port' => env('database.default.port', $this->eloquentConfig->databasePort),
            // Add performance options
            'options' => [
                \PDO::ATTR_PERSISTENT => true, // Use persistent connections
                \PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC, // Fetch as associative array by default
            ],
        ];
    }

    /**
     * Configures pagination settings only when needed.
     */
    public function configurePagination(): void
    {
        if ($this->initialized['pagination']) {
            return;
        }
        
        $this->paginationConfig = config('Pagination');
        $request = service('request');
        $uri = service('uri');
        $currentUrl = current_url();

        // Make sure container is initialized
        $this->setupContainer();

        $this->container->singleton('paginator.currentPage', function () {
            return $_GET['page'] ?? 1;
        });

        $this->container->singleton('paginator.currentPath', function () use ($currentUrl) {
            return $currentUrl;
        });

        $this->container->singleton('paginator.renderer', function () {
            return new PaginationRenderer;
        });

        Paginator::$defaultView = $this->paginationConfig->defaultView;
        Paginator::$defaultSimpleView = $this->paginationConfig->defaultSimpleView;

        Paginator::viewFactoryResolver(function () {
            return $this->container->get('paginator.renderer');
        });

        Paginator::currentPathResolver(function () use ($currentUrl) {
            return $currentUrl;
        });

        Paginator::currentPageResolver(function ($pageName = 'page') use ($request) {
            $page = $request->getVar($pageName);
            return (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) ? (int) $page : 1;
        });

        Paginator::queryStringResolver(function () use ($uri) {
            return $uri->getQuery();
        });

        CursorPaginator::currentCursorResolver(function ($cursorName = 'cursor') use ($request) {
            return Cursor::fromEncoded($request->getVar($cursorName));
        });
        
        $this->initialized['pagination'] = true;
    }

    /**
     * Initializes the IoC container and sets it as the Facade application root.
     */
    public function setupContainer(): void
    {
        if ($this->initialized['container']) {
            return;
        }
        
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
        
        $this->initialized['container'] = true;
    }

    /**
     * Registers the configuration repository service.
     */
    public function registerConfigService(): void
    {
        if ($this->initialized['config']) {
            return;
        }
        
        // Make sure container is initialized
        $this->setupContainer();
        
        $this->container->singleton('config', function () {
            return new Repository([
                'hashing' => [
                    'driver' => 'bcrypt',
                    'bcrypt' => [
                        'rounds' => 10,
                    ],
                ],
            ]);
        });
        
        $this->initialized['config'] = true;
    }

    /**
     * Registers the hash manager service only when needed.
     */
    public function registerHashService(): void
    {
        if ($this->initialized['hash']) {
            return;
        }
        
        // Make sure container and config are initialized
        $this->setupContainer();
        $this->registerConfigService();
        
        $this->container->singleton('hash', function ($app) {
            return new HashManager($app);
        });
        
        $this->initialized['hash'] = true;
    }
    
    /**
     * Get the Capsule instance
     */
    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }
    
    /**
     * Get the Container instance
     */
    public function getContainer(): ?Container
    {
        return $this->container;
    }
    
    /**
     * Get query logs if enabled
     */
    public function getQueryLog(): array
    {
        if ($this->shouldEnableQueryLogging()) {
            try {
                return $this->capsule->connection()->getQueryLog();
            } catch (\Exception $e) {
                // Silently fail
            }
        }
        
        return [];
    }
}