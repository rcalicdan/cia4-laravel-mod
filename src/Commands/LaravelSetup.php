<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\AuthHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\ConfigHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\HelpersHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\MigrationHandler;
use Rcalicdan\Ci4Larabridge\Commands\Handlers\LaravelSetup\SystemHandler;

class LaravelSetup extends BaseCommand
{
    protected $group = 'Laravel Setup';
    /**
     * The Command's name
     *
     * @var string
     */
    protected $name = 'laravel:setup';

    /**
     * The Command's short description
     *
     * @var string
     */
    protected $description = 'Initial setup for CodeIgniter 4 Laravel Module.';

    /**
     * The Command's usage
     *
     * @var string
     */
    protected $usage = 'laravel:setup';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '-f' => 'Force overwrite ALL existing files in destination.',
    ];

    /**
     * The path to `Rcalicdan\Ci4Larabridge\` src directory.
     *
     * @var string
     */
    protected $sourcePath;

    /**
     * The path to the application directory
     *
     * @var string
     */
    protected $distPath = APPPATH;

    /**
     * Execute the setup process
     */
    public function run(array $params): void
    {
        $this->sourcePath = __DIR__.'/../';

        // Initialize handlers
        $configHandler = new ConfigHandler($this->sourcePath, $this->distPath);
        $helpersHandler = new HelpersHandler($this->sourcePath, $this->distPath);
        $migrationHandler = new MigrationHandler($this->sourcePath, $this->distPath);
        $systemHandler = new SystemHandler($this->sourcePath, $this->distPath);
        $authHandler = new AuthHandler($this->sourcePath, $this->distPath);

        // Execute setup steps
        $configHandler->publishConfig();
        $helpersHandler->setupHelpers();
        $migrationHandler->copyMigrationFiles();
        $systemHandler->setupEvents();
        $systemHandler->setupFilters();
        $authHandler->copyUserModel();
        $authHandler->copyAuthServiceProvider();
    }
}
