<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use PDO;

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
     * 
     * Following Laravel's pattern with CodeIgniter fallbacks for compatibility
     */
    public array $connections = [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', env('database.default.database', 'database.sqlite')),
            'prefix' => env('DB_PREFIX', env('database.default.DBPrefix', '')),
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', env('database.default.foreignKeys', true)),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => env('DB_DRIVER', env('DB_DBDRIVER', env('database.default.DBDriver', 'mysql'))),
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', env('database.default.hostname', '127.0.0.1')),
            'port' => env('DB_PORT', env('database.default.port', '3306')),
            'database' => env('DB_DATABASE', env('database.default.database', 'laravel')),
            'username' => env('DB_USERNAME', env('database.default.username', 'root')),
            'password' => env('DB_PASSWORD', env('database.default.password', '')),
            'unix_socket' => env('DB_SOCKET', env('database.default.socket', '')),
            'charset' => env('DB_CHARSET', env('database.default.DBCharset', 'utf8mb4')),
            'collation' => env('DB_COLLATION', env('database.default.DBCollat', 'utf8mb4_unicode_ci')),
            'prefix' => env('DB_PREFIX', env('database.default.DBPrefix', '')),
            'prefix_indexes' => true,
            'strict' => env('DB_STRICT', true),
            'engine' => env('DB_ENGINE', null),
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', env('database.mariadb.hostname', '127.0.0.1')),
            'port' => env('DB_PORT', env('database.mariadb.port', '3306')),
            'database' => env('DB_DATABASE', env('database.mariadb.database', 'laravel')),
            'username' => env('DB_USERNAME', env('database.mariadb.username', 'root')),
            'password' => env('DB_PASSWORD', env('database.mariadb.password', '')),
            'unix_socket' => env('DB_SOCKET', env('database.mariadb.socket', '')),
            'charset' => env('DB_CHARSET', env('database.mariadb.DBCharset', 'utf8mb4')),
            'collation' => env('DB_COLLATION', env('database.mariadb.DBCollat', 'utf8mb4_unicode_ci')),
            'prefix' => env('DB_PREFIX', env('database.mariadb.DBPrefix', '')),
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', env('database.pgsql.hostname', '127.0.0.1')),
            'port' => env('DB_PORT', env('database.pgsql.port', '5432')),
            'database' => env('DB_DATABASE', env('database.pgsql.database', 'laravel')),
            'username' => env('DB_USERNAME', env('database.pgsql.username', 'root')),
            'password' => env('DB_PASSWORD', env('database.pgsql.password', '')),
            'charset' => env('DB_CHARSET', env('database.pgsql.DBCharset', 'utf8')),
            'prefix' => env('DB_PREFIX', env('database.pgsql.DBPrefix', '')),
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', env('database.sqlsrv.hostname', 'localhost')),
            'port' => env('DB_PORT', env('database.sqlsrv.port', '1433')),
            'database' => env('DB_DATABASE', env('database.sqlsrv.database', 'laravel')),
            'username' => env('DB_USERNAME', env('database.sqlsrv.username', 'root')),
            'password' => env('DB_PASSWORD', env('database.sqlsrv.password', '')),
            'charset' => env('DB_CHARSET', env('database.sqlsrv.DBCharset', 'utf8')),
            'prefix' => env('DB_PREFIX', env('database.sqlsrv.DBPrefix', '')),
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],
    ];

    /**
     * Migration Repository Table
     */
    public array $migrations = [
        'table' => env('DB_MIGRATIONS_TABLE', 'migrations'),
        'update_date_on_publish' => true,
    ];

    /**
     * Redis Configuration (optional)
     */
    public array $redis = [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'ci4_larabridge_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ];
}