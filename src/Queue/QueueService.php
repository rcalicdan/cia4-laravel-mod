<?php

namespace Rcalicdan\Ci4Larabridge\Queue;

use Config\LarabridgeQueue;
use Illuminate\Container\Container;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Queue\Connectors\SyncConnector;
use Illuminate\Queue\Connectors\BeanstalkdConnector;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Redis\RedisManager;
use Rcalicdan\Ci4Larabridge\Database\EloquentDatabase;
use Rcalicdan\Ci4Larabridge\Exceptions\QueueExceptionHandler;

class QueueService
{
    protected Container $container;
    protected QueueManager $queueManager;
    protected LarabridgeQueue $config;
    protected static ?self $instance = null;

    /**
     * @var array|null Cached environment variables
     */
    private static ?array $envCache = [];

    /**
     * @var array|null Cached queue configurations
     */
    private static ?array $queueConfigCache = [];

    public function __construct()
    {
        $this->config = config(LarabridgeQueue::class);
        $this->container = $this->getContainer();
        $this->setupQueueManager();
        $this->registerConnectors();
        $this->setupFailedJobProvider();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Get an environment variable with caching
     */
    protected function env(string $key, $default = null)
    {
        return self::$envCache[$key] ??= env($key, $default);
    }

    /**
     * Get the default queue connection name (cached)
     */
    protected function getDefaultConnection(): string
    {
        if (!isset(self::$envCache['default_connection'])) {
            self::$envCache['default_connection'] = $this->env(
                'QUEUE_CONNECTION',
                $this->config->default
            );
        }

        return self::$envCache['default_connection'];
    }

    /**
     * Apply environment variable overrides to queue configuration
     */
    protected function applyEnvironmentOverrides(array $config, string $connectionName): array
    {
        $isDefaultConnection = $connectionName === $this->getDefaultConnection();

        if ($isDefaultConnection) {
            $mergeConfig = [
                'driver' => $this->env('QUEUE_CONNECTION', $config['driver']),
                'queue' => $this->env('QUEUE_NAME', $config['queue'] ?? 'default'),
                'table' => $this->env('QUEUE_TABLE', $config['table'] ?? 'jobs'),
                'retry_after' => (int) $this->env('QUEUE_RETRY_AFTER', $config['retry_after'] ?? 90),
                'block_for' => $this->env('QUEUE_BLOCK_FOR', $config['block_for'] ?? null),
                'after_commit' => filter_var($this->env('QUEUE_AFTER_COMMIT', $config['after_commit'] ?? false), FILTER_VALIDATE_BOOLEAN),
            ];

            $driver = $this->env('QUEUE_CONNECTION', $config['driver']);

            if ($driver === 'redis') {
                $mergeConfig['host'] = $this->env('QUEUE_REDIS_HOST', $this->env('QUEUE_HOST', $config['host'] ?? 'localhost'));
                $mergeConfig['port'] = $this->env('QUEUE_REDIS_PORT', $this->env('QUEUE_PORT', $config['port'] ?? 6379));
                $mergeConfig['connection'] = $this->env('QUEUE_REDIS_CONNECTION', $config['connection'] ?? 'default');
            } elseif ($driver === 'beanstalkd') {
                $mergeConfig['host'] = $this->env('QUEUE_BEANSTALKD_HOST', $this->env('QUEUE_HOST', $config['host'] ?? 'localhost'));
                $mergeConfig['port'] = $this->env('QUEUE_BEANSTALKD_PORT', $this->env('QUEUE_PORT', $config['port'] ?? 11300));
            } elseif ($driver === 'sqs') {
                $mergeConfig['key'] = $this->env('AWS_ACCESS_KEY_ID', $this->env('QUEUE_SQS_KEY', $config['key'] ?? ''));
                $mergeConfig['secret'] = $this->env('AWS_SECRET_ACCESS_KEY', $this->env('QUEUE_SQS_SECRET', $config['secret'] ?? ''));
                $mergeConfig['region'] = $this->env('AWS_DEFAULT_REGION', $this->env('QUEUE_SQS_REGION', $config['region'] ?? 'us-east-1'));
                $mergeConfig['prefix'] = $this->env('QUEUE_SQS_PREFIX', $config['prefix'] ?? '');
                $mergeConfig['suffix'] = $this->env('QUEUE_SQS_SUFFIX', $config['suffix'] ?? '');
            } else {
                $mergeConfig['host'] = $this->env('QUEUE_HOST', $config['host'] ?? 'localhost');
                $mergeConfig['port'] = $this->env('QUEUE_PORT', $config['port'] ?? null);
                if ($mergeConfig['port']) {
                    $mergeConfig['port'] = (int) $mergeConfig['port'];
                }
            }

            return array_merge($config, $mergeConfig);
        }

        $upperConnection = strtoupper($connectionName);
        return array_merge($config, [
            'host' => $this->env("QUEUE_{$upperConnection}_HOST", $config['host'] ?? 'localhost'),
            'port' => $this->env("QUEUE_{$upperConnection}_PORT", $config['port'] ?? null),
            'queue' => $this->env("QUEUE_{$upperConnection}_NAME", $config['queue'] ?? 'default'),
            'table' => $this->env("QUEUE_{$upperConnection}_TABLE", $config['table'] ?? 'jobs'),
            'retry_after' => (int) $this->env("QUEUE_{$upperConnection}_RETRY_AFTER", $config['retry_after'] ?? 90),
            'connection' => $this->env("QUEUE_{$upperConnection}_REDIS_CONNECTION", $config['connection'] ?? 'default'),
        ]);
    }

    /**
     * Get queue connection configuration with environment overrides
     */
    public function getConnectionConfig(string $connection): array
    {
        $cacheKey = $connection ?? 'default';

        if (isset(self::$queueConfigCache[$cacheKey])) {
            return self::$queueConfigCache[$cacheKey];
        }

        $connectionName = $connection ?? $this->getDefaultConnection();
        $baseConfig = $this->config->connections[$connectionName] ?? $this->config->connections['database'];
        $config = $this->applyEnvironmentOverrides($baseConfig, $connectionName);

        return self::$queueConfigCache[$cacheKey] = $config;
    }

    /**
     * Get worker configuration with environment overrides
     */
    public function getWorkerConfig(): array
    {
        if (isset(self::$queueConfigCache['worker'])) {
            return self::$queueConfigCache['worker'];
        }

        $baseConfig = $this->config->worker;
        $config = array_merge($baseConfig, [
            'sleep' => (int) $this->env('QUEUE_WORKER_SLEEP', $baseConfig['sleep']),
            'max_tries' => (int) $this->env('QUEUE_WORKER_MAX_TRIES', $baseConfig['max_tries']),
            'timeout' => (int) $this->env('QUEUE_WORKER_TIMEOUT', $baseConfig['timeout']),
            'memory' => (int) $this->env('QUEUE_WORKER_MEMORY', $baseConfig['memory']),
            'stop_when_empty' => filter_var($this->env('QUEUE_WORKER_STOP_WHEN_EMPTY', $baseConfig['stop_when_empty']), FILTER_VALIDATE_BOOLEAN),
            'max_jobs' => (int) $this->env('QUEUE_WORKER_MAX_JOBS', $baseConfig['max_jobs']),
            'max_time' => (int) $this->env('QUEUE_WORKER_MAX_TIME', $baseConfig['max_time']),
            'backoff' => (int) $this->env('QUEUE_WORKER_BACKOFF', $baseConfig['backoff']),
        ]);

        return self::$queueConfigCache['worker'] = $config;
    }

    /**
     * Get failed job configuration with environment overrides
     */
    public function getFailedConfig(): array
    {
        if (isset(self::$queueConfigCache['failed'])) {
            return self::$queueConfigCache['failed'];
        }

        $baseConfig = $this->config->failed;
        $config = array_merge($baseConfig, [
            'driver' => $this->env('QUEUE_FAILED_DRIVER', $baseConfig['driver']),
            'database' => $this->env('QUEUE_FAILED_DATABASE', $baseConfig['database']),
            'table' => $this->env('QUEUE_FAILED_TABLE', $baseConfig['table']),
        ]);

        return self::$queueConfigCache['failed'] = $config;
    }

    /**
     * Get batching configuration with environment overrides
     */
    public function getBatchingConfig(): array
    {
        if (isset(self::$queueConfigCache['batching'])) {
            return self::$queueConfigCache['batching'];
        }

        $baseConfig = $this->config->batching;
        $config = array_merge($baseConfig, [
            'database' => $this->env('QUEUE_BATCH_DATABASE', $baseConfig['database']),
            'table' => $this->env('QUEUE_BATCH_TABLE', $baseConfig['table']),
        ]);

        return self::$queueConfigCache['batching'] = $config;
    }

    protected function getContainer(): Container
    {
        $eloquent = EloquentDatabase::getInstance();
        return $eloquent->container ?? new Container();
    }

    protected function setupQueueManager(): void
    {
        $this->queueManager = new QueueManager($this->container);

        $this->container->singleton('queue', function () {
            return $this->queueManager;
        });

        $this->queueManager->setDefaultDriver($this->getDefaultConnection());
    }

    protected function registerConnectors(): void
    {
        // Database connector
        $this->queueManager->addConnector('database', function () {
            return new DatabaseConnector($this->container['db']);
        });

        // Sync connector
        $this->queueManager->addConnector('sync', function () {
            return new SyncConnector();
        });

        // Redis connector
        $this->queueManager->addConnector('redis', function () {
            return new RedisConnector($this->getRedisManager());
        });

        // Beanstalkd connector
        $this->queueManager->addConnector('beanstalkd', function () {
            return new BeanstalkdConnector();
        });

        // SQS connector
        $this->queueManager->addConnector('sqs', function () {
            return new SqsConnector();
        });
    }

    protected function getRedisManager(): RedisManager
    {
        if (!$this->container->bound('redis')) {
            $this->container->singleton('redis', function () {
                $eloquentConfig = config(\Config\Eloquent::class);
                return new RedisManager($this->container, 'phpredis', $eloquentConfig->redis);
            });
        }

        return $this->container['redis'];
    }

    protected function setupFailedJobProvider(): void
    {
        $this->container->singleton('queue.failer', function () {
            $failedConfig = $this->getFailedConfig();

            if ($failedConfig['driver'] === 'database') {
                return new DatabaseFailedJobProvider(
                    $this->container['db'],
                    $failedConfig['database'],
                    $failedConfig['table']
                );
            }

            return null;
        });
    }

    public function getQueueManager(): QueueManager
    {
        return $this->queueManager;
    }

    public function createWorker(): Worker
    {
        return new Worker(
            $this->queueManager,
            $this->container['events'],
            new QueueExceptionHandler(),
            function () {
                return false;
            }
        );
    }

    public function getWorkerOptions(): WorkerOptions
    {
        $workerConfig = $this->getWorkerConfig();

        return new WorkerOptions(
            'default',                          // name - position 0
            $workerConfig['backoff'],           // backoff - position 1
            $workerConfig['memory'],            // memory - position 2  
            $workerConfig['timeout'],           // timeout - position 3
            $workerConfig['sleep'],             // sleep - position 4
            $workerConfig['max_tries'],         // maxTries - position 5
            true,                               // force - position 6
            $workerConfig['stop_when_empty'],   // stopWhenEmpty - position 7
            $workerConfig['max_jobs'],          // maxJobs - position 8
            $workerConfig['max_time'],          // maxTime - position 9
            0                                   // rest - position 10
        );
    }

    /**
     * Clear all cached data
     */
    public static function clearCaches(): void
    {
        self::$envCache = [];
        self::$queueConfigCache = [];
    }

    /**
     * Get current cache status information
     */
    public static function getCacheStatus(): array
    {
        return [
            'env_cache_count' => count(self::$envCache),
            'queue_config_cache_count' => count(self::$queueConfigCache),
        ];
    }
}
