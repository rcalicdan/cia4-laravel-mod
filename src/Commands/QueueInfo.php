<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Queue\QueueService;

class QueueInfo extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:info';
    protected $description = 'Display queue configuration information';

    public function run(array $params)
    {
        $queueService = QueueService::getInstance();
        
        CLI::write('Queue Configuration Information', 'yellow');
        CLI::write('================================', 'yellow');
        CLI::write('');

        // Default connection info
        $defaultConnection = env('QUEUE_CONNECTION', 'database');
        CLI::write("Default Connection: {$defaultConnection}", 'green');
        CLI::write('');

        // Connection details
        $config = $queueService->getConnectionConfig($defaultConnection);
        CLI::write('Connection Details:', 'cyan');
        foreach ($config as $key => $value) {
            if (is_scalar($value)) {
                CLI::write("  {$key}: " . ($value ?: 'N/A'), 'white');
            }
        }
        CLI::write('');

        // Worker configuration
        $workerConfig = $queueService->getWorkerConfig();
        CLI::write('Worker Configuration:', 'cyan');
        foreach ($workerConfig as $key => $value) {
            CLI::write("  {$key}: {$value}", 'white');
        }
        CLI::write('');

        // Failed jobs configuration
        $failedConfig = $queueService->getFailedConfig();
        CLI::write('Failed Jobs Configuration:', 'cyan');
        foreach ($failedConfig as $key => $value) {
            CLI::write("  {$key}: {$value}", 'white');
        }
        CLI::write('');

        // Cache status
        $cacheStatus = QueueService::getCacheStatus();
        CLI::write('Cache Status:', 'cyan');
        foreach ($cacheStatus as $key => $value) {
            CLI::write("  {$key}: {$value}", 'white');
        }
    }
}