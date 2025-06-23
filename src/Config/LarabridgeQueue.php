<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Laravel Queue Configuration for CI4 Larabridge
 * Supports environment variable overrides for flexible deployment
 */
class LarabridgeQueue extends BaseConfig
{
    /**
     * Default Queue Driver
     */
    public string $default = 'database';

    /**
     * Queue Connections
     */
    public array $connections = [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => '',
            'secret' => '',
            'prefix' => '',
            'queue' => 'default',
            'suffix' => '',
            'region' => 'us-east-1',
            'after_commit' => false,
        ],
    ];

    /**
     * Failed Queue Jobs
     */
    public array $failed = [
        'driver' => 'database',
        'database' => 'mysql',
        'table' => 'failed_jobs',
    ];

    /**
     * Job batching configuration
     */
    public array $batching = [
        'database' => 'mysql',
        'table' => 'job_batches',
    ];

    /**
     * Queue Worker Configuration
     */
    public array $worker = [
        'sleep' => 3,
        'max_tries' => 3,
        'timeout' => 60,
        'memory' => 128,
        'stop_when_empty' => false,
        'max_jobs' => 1000,
        'max_time' => 3600,
        'backoff' => 0,
    ];

    /**
     * Queue Middleware Configuration
     */
    public array $middleware = [
        'throttle' => [
            'allow' => 10,
            'every' => 60,
        ],
        'rate_limit' => [
            'key' => null,
            'max_attempts' => 60,
            'decay_minutes' => 1,
        ],
    ];
}