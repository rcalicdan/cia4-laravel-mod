<?php

namespace Rcalicdan\Ci4Larabridge\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Eloquent Configuration
 *
 * Contains database connection settings for integrating Eloquent ORM
 * with CodeIgniter 4. These settings map to the default database configuration
 * in CodeIgniter.
 */
class Eloquent extends BaseConfig
{
    /**
     * Database hostname or IP address
     *
     * @var string
     */
    public $databaseHost = env('database.default.hostname', 'localhost');
    /**
     * Database driver to use
     *
     * @var string
     */
    public $databaseDriver = env('database.default.DBDriver', 'sqlite');
    /**
     * Database name to connect to
     *
     * @var string
     */
    public $databaseName = env('database.default.database', '');
    /**
     * Database username for authentication
     *
     * @var string
     */
    public $databaseUsername = env('database.default.username', 'root');
    /**
     * Database password for authentication
     *
     * @var string
     */
    public $databasePassword = env('database.default.password', '');
    /**
     * Database connection character set
     *
     * @var string
     */
    public $databaseCharset = env('database.default.DBCharset', 'utf8');
    /**
     * Database collation setting
     *
     * @var string
     */
    public $databaseCollation = env('database.default.DBCollat', 'utf8_general_ci');
    /**
     * Table prefix for database connections
     *
     * @var string
     */
    public $databasePrefix = env('database.default.DBPrefix', '');
    /**
     * Database connection port
     *
     * @var string
     */
    public $databasePort = env('database.default.port', '');
}
