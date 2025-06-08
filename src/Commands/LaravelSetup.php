<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\AuthHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\ConfigHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\MigrationHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\SystemHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\ToolbarHandler;

/**
 * Command to perform the initial setup for the CodeIgniter 4 Laravel Module.
 *
 * This command orchestrates the setup process by initializing handlers and
 * executing steps to configure the Laravel module within a CodeIgniter 4
 * application. It publishes configuration files, sets up helpers, copies migration
 * files, configures system events and filters, and prepares authentication components.
 */
class LaravelSetup extends BaseCommand
{
    /**
     * The group this command belongs to.
     *
     * @var string
     */
    protected $group = 'Laravel Setup';

    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'laravel:setup';

    /**
     * A brief description of the command's purpose.
     *
     * @var string
     */
    protected $description = 'Initial setup for CodeIgniter 4 Laravel Module.';

    /**
     * The command's usage instructions.
     *
     * @var string
     */
    protected $usage = 'laravel:setup';

    /**
     * Available arguments for the command.
     *
     * @var array<string, string>
     */
    protected $arguments = [];

    /**
     * Available options for the command.
     *
     * @var array<string, string>
     */
    protected $options = [
        '-f' => 'Force overwrite ALL existing files in destination.',
    ];

    /**
     * The path to the source directory of the Ci4Larabridge module.
     *
     * @var string
     */
    protected $sourcePath;

    /**
     * The path to the application directory.
     *
     * @var string
     */
    protected $distPath = APPPATH;

    /**
     * Executes the setup process for the Laravel module.
     *
     * Initializes handlers for configuration, helpers, migrations, system components,
     * and authentication, then executes their respective setup steps. Supports a force
     * overwrite option for existing files.
     *
     * @param  array  $params  Command parameters, including options.
     */
    public function run(array $params): void
    {
        $this->sourcePath = __DIR__.'/../';

        $configHandler = new ConfigHandler($this->sourcePath, $this->distPath);
        $migrationHandler = new MigrationHandler($this->sourcePath, $this->distPath);
        $systemHandler = new SystemHandler($this->sourcePath, $this->distPath);
        $authHandler = new AuthHandler($this->sourcePath, $this->distPath);
        $toolbarHandler = new ToolbarHandler($this->sourcePath, $this->distPath);

        $configHandler->publishConfig();
        $migrationHandler->copyMigrationFiles();
        $systemHandler->setupEvents();
        $systemHandler->setupFilters();
        $authHandler->copyUserModel();
        $toolbarHandler->setupToolbar();
    }
}
