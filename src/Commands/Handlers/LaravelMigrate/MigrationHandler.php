<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelMigrate;

use CodeIgniter\CLI\CLI;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Facade;

class MigrationHandler
{
    protected $capsule;
    protected $repository;
    protected $migrator;
    protected $migrationPath;

    /**
     * Setup the Laravel environment
     */
    public function setupEnvironment(array $dbConfig)
    {
        $this->setupDatabase($dbConfig);
        $this->setupRepository();
        $this->setupMigrator();

        return $this;
    }

    /**
     * Initialize database connection
     */
    private function setupDatabase(array $dbConfig)
    {
        $this->capsule = new Capsule;
        $this->capsule->addConnection($dbConfig);

        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->setUpFacadeContainer();
    }

    private function setUpFacadeContainer()
    {
        $container = $this->capsule->getContainer();
        Facade::setFacadeApplication($container);

        $container->instance('db', $this->capsule->getDatabaseManager());

        $container->bind('db.schema', function ($container) {
            return $container['db']->connection()->getSchemaBuilder();
        });
    }

    /**
     * Initialize migration repository
     */
    private function setupRepository()
    {
        $this->repository = new DatabaseMigrationRepository(
            $this->capsule->getDatabaseManager(),
            'migrations'
        );

        if (! $this->repository->repositoryExists()) {
            $this->repository->createRepository();
            CLI::write('Laravel migration repository created.', 'green');
        }
    }

    /**
     * Initialize migration manager
     */
    private function setupMigrator()
    {
        $this->migrationPath = APPPATH.'Database/Eloquent-Migrations';
        $filesystem = new Filesystem;
        $this->migrator = new Migrator(
            $this->repository,
            $this->capsule->getDatabaseManager(),
            $filesystem
        );
        $this->migrator->setConnection('default');
    }

    /**
     * Run migrations up
     */
    public function runMigrations()
    {
        $before = $this->repository->getRan();
        $this->migrator->run($this->migrationPath);
        $after = $this->repository->getRan();

        return array_diff($after, $before);
    }

    /**
     * Rollback migrations
     */
    public function rollbackMigrations()
    {
        $before = $this->repository->getRan();
        $this->migrator->rollback($this->migrationPath);
        $after = $this->repository->getRan();

        return array_diff($before, $after);
    }

    /**
     * Reset and rerun migrations
     */
    public function refreshMigrations()
    {
        $this->migrator->reset($this->migrationPath);
        $this->migrator->run($this->migrationPath);
    }

    /**
     * Get migration status
     */
    public function getMigrationStatus(): array
    {
        $ran = $this->repository->getRan();
        $files = $this->migrator->getMigrationFiles($this->migrationPath);

        $status = [];
        foreach ($files as $file => $name) {
            $status[$name] = in_array($name, $ran) ? 'Ran' : 'Pending';
        }

        return $status;
    }

    /**
     * Get database connection
     */
    public function getConnection()
    {
        return $this->capsule->getConnection();
    }
}
