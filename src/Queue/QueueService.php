<?php

namespace Rcalicdan\Ci4Larabridge\Queue;

use Config\LarabridgeQueue;
use Illuminate\Container\Container;
use Illuminate\Queue\Connectors\BeanstalkdConnector;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Queue\Connectors\SyncConnector;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
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
        $this->config = config('LarabridgeQueue');
        $this->container = $this->getContainer();
        $this->setupContainerBindings();
        $this->setupQueueManager();
        $this->registerConnectors();
        $this->setupFailedJobProvider();
        $this->setupBusBindings();
    }

    /**
     * Setup Bus bindings after queue manager is ready
     */
    protected function setupBusBindings(): void
    {
        if (!$this->container->bound(\Illuminate\Contracts\Bus\Dispatcher::class)) {
            $this->container->singleton(\Illuminate\Contracts\Bus\Dispatcher::class, function ($container) {
                $busDispatcher = new \Illuminate\Bus\Dispatcher($container, function ($connection = null) {
                    return $this->queueManager->connection($connection);
                });

                $busDispatcher->pipeThrough([]);
                return $busDispatcher;
            });

            // Also bind other Bus interfaces
            $this->container->bind(\Illuminate\Contracts\Bus\QueueingDispatcher::class, function ($container) {
                return $container[\Illuminate\Contracts\Bus\Dispatcher::class];
            });

            $this->container->bind(\Illuminate\Bus\Dispatcher::class, function ($container) {
                return $container[\Illuminate\Contracts\Bus\Dispatcher::class];
            });

            $this->container->alias(\Illuminate\Contracts\Bus\Dispatcher::class, 'bus');
        }
    }

    /**
     * Setup container bindings - FIXED VERSION WITHOUT CIRCULAR REFERENCE
     */
    protected function setupContainerBindings(): void
    {
        // CRITICAL: Container must bind itself FIRST
        $this->container->instance(\Illuminate\Container\Container::class, $this->container);
        $this->container->instance(\Illuminate\Contracts\Container\Container::class, $this->container);
        $this->container->instance('app', $this->container);

        // Bind database manager
        if (!$this->container->bound('db')) {
            $this->container->singleton('db', function () {
                $eloquent = EloquentDatabase::getInstance();
                return $eloquent->capsule->getDatabaseManager();
            });
        }

        // Ensure config is properly bound
        if (!$this->container->bound('config')) {
            $this->container->singleton('config', function () {
                $config = new \Illuminate\Config\Repository;

                // Add queue configuration
                $queueConfig = config('LarabridgeQueue');
                $config->set('queue', [
                    'default' => $queueConfig->default,
                    'connections' => $queueConfig->connections,
                    'failed' => $queueConfig->failed,
                    'batching' => $queueConfig->batching,
                ]);

                return $config;
            });
        }

        // CRITICAL: Fix events dispatcher binding - COMPLETELY UPDATED
        if (!$this->container->bound('events')) {
            $this->container->singleton('events', function ($container) {
                return new \Illuminate\Events\Dispatcher($container);
            });

            // Bind ALL the event dispatcher interfaces - THIS IS CRITICAL
            $this->container->singleton(\Illuminate\Contracts\Events\Dispatcher::class, function ($container) {
                return $container['events'];
            });

            $this->container->singleton(\Illuminate\Events\Dispatcher::class, function ($container) {
                return $container['events'];
            });

            // Add this missing alias - CRITICAL FOR SERIALIZATION
            $this->container->alias('events', \Illuminate\Contracts\Events\Dispatcher::class);
        }

        // Add encrypter binding (required for job serialization) - COMPLETE IMPLEMENTATION
        if (!$this->container->bound('encrypter')) {
            $this->container->singleton('encrypter', function () {
                return new class implements \Illuminate\Contracts\Encryption\Encrypter {
                    private string $key = 'dummy-key-for-ci4-larabridge';
                    private array $previousKeys = [];

                    public function encrypt($payload, $serialize = true)
                    {
                        $value = $serialize ? serialize($payload) : $payload;
                        return base64_encode($value);
                    }

                    public function decrypt($payload, $unserialize = true)
                    {
                        $decoded = base64_decode($payload);
                        return $unserialize ? unserialize($decoded) : $decoded;
                    }

                    public function getKey()
                    {
                        return $this->key;
                    }

                    public function getAllKeys(): array
                    {
                        return array_merge([$this->key], $this->previousKeys);
                    }

                    public function getPreviousKeys(): array
                    {
                        return $this->previousKeys;
                    }

                    public function previousKeys(array $keys): self
                    {
                        $this->previousKeys = $keys;
                        return $this;
                    }
                };
            });

            $this->container->bind(\Illuminate\Contracts\Encryption\Encrypter::class, function ($container) {
                return $container['encrypter'];
            });
        }

        // Add UUID generator binding - NEW
        if (!$this->container->bound('uuid')) {
            $this->container->singleton('uuid', function () {
                return new class {
                    public function uuid4(): string
                    {
                        return sprintf(
                            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0x0fff) | 0x4000,
                            mt_rand(0, 0x3fff) | 0x8000,
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff),
                            mt_rand(0, 0xffff)
                        );
                    }
                };
            });
        }

        // Bind log for error handling
        if (!$this->container->bound('log')) {
            $this->container->singleton('log', function () {
                return new class {
                    public function error($message, array $context = [])
                    {
                        log_message('error', $message);
                    }

                    public function info($message, array $context = [])
                    {
                        log_message('info', $message);
                    }

                    public function debug($message, array $context = [])
                    {
                        log_message('debug', $message);
                    }

                    public function warning($message, array $context = [])
                    {
                        log_message('warning', $message);
                    }
                };
            });
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self;
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
        if (! isset(self::$envCache['default_connection'])) {
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

            if ($driver === 'database') {
                // For database connections, ensure connection is a string or null
                $mergeConfig['connection'] = $this->env('QUEUE_DB_CONNECTION', $config['connection'] ?? null);
            } elseif ($driver === 'redis') {
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

        $baseOverrides = [
            'host' => $this->env("QUEUE_{$upperConnection}_HOST", $config['host'] ?? 'localhost'),
            'port' => $this->env("QUEUE_{$upperConnection}_PORT", $config['port'] ?? null),
            'queue' => $this->env("QUEUE_{$upperConnection}_NAME", $config['queue'] ?? 'default'),
            'table' => $this->env("QUEUE_{$upperConnection}_TABLE", $config['table'] ?? 'jobs'),
            'retry_after' => (int) $this->env("QUEUE_{$upperConnection}_RETRY_AFTER", $config['retry_after'] ?? 90),
        ];

        // Add driver-specific overrides
        if ($config['driver'] === 'database') {
            $baseOverrides['connection'] = $this->env("QUEUE_{$upperConnection}_DB_CONNECTION", $config['connection'] ?? null);
        } elseif ($config['driver'] === 'redis') {
            $baseOverrides['connection'] = $this->env("QUEUE_{$upperConnection}_REDIS_CONNECTION", $config['connection'] ?? 'default');
        }

        return array_merge($config, $baseOverrides);
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

        return $eloquent->container ?? new Container;
    }

    protected function setupQueueManager(): void
    {
        $this->queueManager = new QueueManager($this->container);

        $this->container->singleton('queue', function () {
            return $this->queueManager;
        });

        // IMPORTANT: Bind queue contracts
        $this->container->bind(\Illuminate\Contracts\Queue\Factory::class, function () {
            return $this->queueManager;
        });

        $this->container->bind(\Illuminate\Queue\QueueManager::class, function () {
            return $this->queueManager;
        });

        $this->queueManager->setDefaultDriver($this->getDefaultConnection());
        $this->registerQueueConnections();

        // CORRECTED: Set up queue event handlers for failed jobs
        $this->queueManager->failing(function ($connectionName, $job, $data) {
            try {
                log_message('error', 'Queue failing event triggered for connection: ' . $connectionName);

                $failer = $this->container->bound('queue.failer') ? $this->container['queue.failer'] : null;
                if ($failer && method_exists($failer, 'log')) {

                    // Get the queue name from the job
                    $queueName = method_exists($job, 'getQueue') ? $job->getQueue() : 'default';

                    // Get the raw job payload
                    $jobPayload = method_exists($job, 'getRawBody') ? $job->getRawBody() : json_encode([
                        'displayName' => get_class($job),
                        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                        'maxTries' => null,
                        'delay' => null,
                        'timeout' => null,
                        'data' => [
                            'commandName' => get_class($job),
                            'command' => serialize($job)
                        ]
                    ]);

                    // Log the failed job
                    $failer->log($connectionName, $queueName, $jobPayload, $data);
                    log_message('error', 'Failed job logged to database successfully');
                } else {
                    $failedProvider = $failer ? get_class($failer) : 'null';
                    log_message('error', 'No failed job provider available or no log method. Provider: ' . $failedProvider);
                }
            } catch (\Exception $e) {
                log_message('error', 'Failed to log failed job: ' . $e->getMessage());
                log_message('error', 'Exception trace: ' . $e->getTraceAsString());
            }
        });
    }

    /**
     * Register all queue connections with the manager - UPDATED
     */
    protected function registerQueueConnections(): void
    {
        foreach ($this->config->connections as $name => $config) {
            $connectionConfig = $this->getConnectionConfig($name);

            // Ensure database connections have proper string connection names
            if ($connectionConfig['driver'] === 'database') {
                // Make sure connection is a string or null, not an object
                if (isset($connectionConfig['connection']) && !is_string($connectionConfig['connection']) && $connectionConfig['connection'] !== null) {
                    $connectionConfig['connection'] = null; // Reset to default
                }
            }

            // Set the connection configuration in the container
            $this->container['config']->set("queue.connections.{$name}", $connectionConfig);
        }
    }

    /**
     * Register queue connectors - UPDATED
     */
    protected function registerConnectors(): void
    {
        // Database connector - pass the database manager from Eloquent
        $this->queueManager->addConnector('database', function () {
            $eloquent = EloquentDatabase::getInstance();
            return new DatabaseConnector($eloquent->capsule->getDatabaseManager());
        });

        // Sync connector
        $this->queueManager->addConnector('sync', function () {
            return new SyncConnector;
        });

        // Redis connector
        $this->queueManager->addConnector('redis', function () {
            return new RedisConnector($this->getRedisManager());
        });

        // Beanstalkd connector
        $this->queueManager->addConnector('beanstalkd', function () {
            return new BeanstalkdConnector;
        });

        // SQS connector
        $this->queueManager->addConnector('sqs', function () {
            return new SqsConnector;
        });
    }

    protected function getRedisManager(): RedisManager
    {
        if (! $this->container->bound('redis')) {
            $this->container->singleton('redis', function () {
                $eloquentConfig = config(\Config\Eloquent::class);

                return new RedisManager($this->container, 'phpredis', $eloquentConfig->redis);
            });
        }

        return $this->container['redis'];
    }

    /**
     * Setup failed job provider - UPDATED
     */
    protected function setupFailedJobProvider(): void
    {
        $this->container->singleton('queue.failer', function ($container) {
            $failedConfig = $this->getFailedConfig();

            if ($failedConfig['driver'] === 'database') {
                $eloquent = EloquentDatabase::getInstance();

                return new DatabaseFailedJobProvider(
                    $eloquent->capsule->getDatabaseManager(),
                    $failedConfig['database'] ?? 'default',
                    $failedConfig['table']
                );
            }

            return new \Illuminate\Queue\Failed\NullFailedJobProvider();
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
            new QueueExceptionHandler,
            function () {
                return false;
            }
        );
    }

    public function getWorkerOptions(): WorkerOptions
    {
        $workerConfig = $this->getWorkerConfig();

        return new WorkerOptions(
            'default',
            $workerConfig['backoff'],
            $workerConfig['memory'],
            $workerConfig['timeout'],
            $workerConfig['sleep'],
            $workerConfig['max_tries'],
            true,
            $workerConfig['stop_when_empty'],
            $workerConfig['max_jobs'],
            $workerConfig['max_time'],
            0
        );
    }

    /**
     * Get failed job provider
     */
    public function getFailedJobProvider()
    {
        return $this->container->bound('queue.failer') ? $this->container['queue.failer'] : null;
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
