<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Eloquent Database Configuration
 *
 * Laravel-style database configuration for integrating Eloquent ORM
 * with CodeIgniter 4. Supports multiple database connections and
 * maintains backward compatibility with CodeIgniter's database format.
 */
class Eloquent extends BaseConfig
{
    /**
     * Default Database Connection Name
     */
    public string $default = 'mysql';

    /**
     * Database Connections
     */
    public array $connections = [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => 'database.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => null,
            'host' => 'localhost',
            'port' => '3306',
            'database' => '',
            'username' => 'root',
            'password' => '',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => null,
            'host' => 'localhost',
            'port' => '3306',
            'database' => '',
            'username' => 'root',
            'password' => '',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => null,
            'host' => 'localhost',
            'port' => '5432',
            'database' => '',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => null,
            'host' => 'localhost',
            'port' => '1433',
            'database' => '',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
    ];

    /**
     * Migration Repository Table
     */
    public array $migrations = [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ];

    /**
     * Redis Configuration (optional)
     */
    public array $redis = [
        'client' => 'phpredis',
        'options' => [
            'cluster' => 'redis',
            'prefix' => 'ci4_larabridge_',
        ],
        'default' => [
            'url' => null,
            'host' => '127.0.0.1',
            'username' => null,
            'password' => null,
            'port' => '6379',
            'database' => '0',
        ],
        'cache' => [
            'url' => null,
            'host' => '127.0.0.1',
            'username' => null,
            'password' => null,
            'port' => '6379',
            'database' => '1',
        ],
    ];
}
