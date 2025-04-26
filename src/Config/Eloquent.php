<?php

namespace Rcalicdan\Ci4Larabridge\Config;

use CodeIgniter\Config\BaseConfig;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Hashing\HashManager;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Facade;
use Rcalicdan\Ci4Larabridge\Blade\PaginationRenderer;

class Eloquent extends BaseConfig
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Capsule
     */
    protected $capsule;

    /**
     * @var Pagination  Configuration values for Eloquent Pagination
     */
    protected $paginationConfig;

    public function __construct()
    {
        $this->paginationConfig = config('Pagination');
        $this->setupDatabaseConnection();
        $this->setupContainer();
        $this->registerServices();
    }

    /**
     * Configure and initialize the database connection
     */
    protected function setupDatabaseConnection(): void
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($this->getDatabaseInformation());
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    public function getDatabaseInformation(): array
    {
        return [
            'host' => env('database.default.hostname', 'localhost'),
            'driver' => env('database.default.DBDriver', 'sqlite'),
            'database' => env('database.default.database', ''),
            'username' => env('database.default.username', 'root'),
            'password' => env('database.default.password', ''),
            'charset' => env('database.default.DBCharset', 'utf8'),
            'collation' => env('database.default.DBCollat', 'utf8_general_ci'),
            'prefix' => env('database.default.DBPrefix', ''),
            'port' => env('database.default.port', ''),
        ];
    }

    /**
     * Configure pagination once for the entire application
     */
    protected function configurePagination(): void
    {
        // Register a singleton instance of some pagination components
        $this->container->singleton('paginator.currentPage', function () {
            return isset($_GET['page']) ? (int) $_GET['page'] : 1;
        });

        $this->container->singleton('paginator.currentPath', function () {
            return current_url();
        });

        Paginator::$defaultView = $this->paginationConfig->defaultView;

        Paginator::$defaultSimpleView = $this->paginationConfig->defaultSimpleView;

        Paginator::viewFactoryResolver(function () {
            return new PaginationRenderer();
        });

        Paginator::currentPathResolver(function () {
            return current_url();
        });

        Paginator::currentPageResolver(function ($pageName = 'page') {
            $page = service('request')->getVar($pageName);

            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return (int) $page;
            }

            return 1;
        });

        Paginator::queryStringResolver(function () {
            return Services::uri()->getQuery();
        });

        CursorPaginator::currentCursorResolver(static function ($cursorName = 'cursor') {
            return Cursor::fromEncoded(service('request')->getVar($cursorName));
        });
    }

    /**
     * Initialize the container and set it as the Facade application root
     */
    protected function setupContainer(): void
    {
        $this->container = new Container;
        Facade::setFacadeApplication($this->container);
    }

    /**
     * Register required services in the container
     */
    protected function registerServices(): void
    {
        $this->registerConfigService();
        $this->registerHashService();
        $this->configurePagination();
    }

    /**
     * Register the configuration repository
     */
    protected function registerConfigService(): void
    {
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
    }

    /**
     * Register the hash manager service
     */
    protected function registerHashService(): void
    {
        $this->container->singleton('hash', function ($app) {
            return new HashManager($app);
        });
    }
}
