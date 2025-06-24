<?php

namespace Rcalicdan\Ci4Larabridge\Queue;

use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Container\Container;
use Rcalicdan\Ci4Larabridge\Database\EloquentDatabase;

class BusService
{
    protected Container $container;
    protected BusDispatcher $busDispatcher;
    protected QueueService $queueService;
    protected static ?self $instance = null;

    public function __construct()
    {
        $this->queueService = QueueService::getInstance();
        $this->container = $this->getContainer();
        $this->setupBusDispatcher();
        $this->setupBatchRepository();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

    protected function getContainer(): Container
    {
        $eloquent = EloquentDatabase::getInstance();

        return $eloquent->container ?? new Container;
    }

    protected function setupBusDispatcher(): void
    {
        $this->busDispatcher = new BusDispatcher($this->container, function ($connection = null) {
            return $this->queueService->getQueueManager()->connection($connection);
        });

        $this->container->singleton(BusDispatcher::class, function () {
            return $this->busDispatcher;
        });

        $this->container->alias(BusDispatcher::class, 'bus');

        $this->busDispatcher->pipeThrough([]);
    }

    protected function setupBatchRepository(): void
    {
        $this->container->singleton(BatchRepository::class, function () {
            $batchConfig = $this->queueService->getBatchingConfig();

            return new DatabaseBatchRepository(
                $this->container['db'],
                $batchConfig['database'],
                $batchConfig['table']
            );
        });
    }

    public function getBusDispatcher(): BusDispatcher
    {
        return $this->busDispatcher;
    }
}
