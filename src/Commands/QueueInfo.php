<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Queue\QueueService;

class QueueInfo extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:info';
    protected $description = 'Display queue worker and system information';
    protected $usage = 'queue:info [connection] [options]';

    protected $arguments = [
        'connection' => 'The name of the queue connection to inspect',
    ];

    protected $options = [
        '--queue' => 'The names of the queues to inspect (comma-separated)',
        '--memory' => 'Show memory usage information',
        '--config' => 'Show configuration details',
    ];

    protected QueueService $queueService;

    public function run(array $params)
    {
        try {
            $this->queueService = QueueService::getInstance();
            $connection = $params[0] ?? $this->queueService->getQueueManager()->getDefaultDriver();
            $queues = $this->getQueues();

            $this->showSystemInfo();
            $this->showQueueInfo($connection, $queues);

            if (CLI::getOption('memory')) {
                $this->showMemoryInfo();
            }

            if (CLI::getOption('config')) {
                $this->showConfigInfo();
            }

            $this->showRestartStatus();

            return EXIT_SUCCESS;
        } catch (\Exception $e) {
            CLI::error('Error getting queue info: ' . $e->getMessage());
            return EXIT_ERROR;
        }
    }

    protected function showSystemInfo(): void
    {
        CLI::write('System Information', 'green');
        CLI::write('==================', 'green');
        CLI::write('PHP Version: ' . PHP_VERSION, 'yellow');
        CLI::write('CodeIgniter Version: ' . \CodeIgniter\CodeIgniter::CI_VERSION, 'yellow');
        CLI::write('Process ID: ' . getmypid(), 'yellow');
        CLI::write('Current Time: ' . date('Y-m-d H:i:s'), 'yellow');
        CLI::write('Environment: ' . ENVIRONMENT, 'yellow');
        CLI::newLine();
    }

    protected function showQueueInfo(string $connection, array $queues): void
    {
        CLI::write('Queue Configuration', 'green');
        CLI::write('===================', 'green');
        CLI::write('Default Connection: ' . $connection, 'yellow');
        CLI::write('Queues: ' . implode(', ', $queues), 'yellow');

        $baseOptions = $this->queueService->getWorkerOptions();
        CLI::write('Default Memory Limit: ' . $baseOptions->memory . 'MB', 'yellow');
        CLI::write('Default Timeout: ' . $baseOptions->timeout . 's', 'yellow');
        CLI::write('Default Sleep: ' . $baseOptions->sleep . 's', 'yellow');
        CLI::write('Default Max Tries: ' . $baseOptions->maxTries, 'yellow');
        CLI::write('Default Max Jobs: ' . ($baseOptions->maxJobs ?: 'unlimited'), 'yellow');
        CLI::write('Default Max Time: ' . ($baseOptions->maxTime ?: 'unlimited') . 's', 'yellow');
        CLI::newLine();
    }

    protected function showMemoryInfo(): void
    {
        CLI::write('Memory Information', 'green');
        CLI::write('==================', 'green');

        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        $memoryLimit = ini_get('memory_limit');

        CLI::write('Current Memory Usage: ' . round($currentMemory, 2) . 'MB', 'yellow');
        CLI::write('Peak Memory Usage: ' . round($peakMemory, 2) . 'MB', 'yellow');
        CLI::write('PHP Memory Limit: ' . $memoryLimit, 'yellow');

        // Test worker creation memory impact
        CLI::write('Testing worker creation...', 'cyan');
        $beforeMemory = memory_get_usage(true) / 1024 / 1024;
        $worker = $this->queueService->createWorker();
        $afterMemory = memory_get_usage(true) / 1024 / 1024;

        CLI::write('Memory before worker: ' . round($beforeMemory, 2) . 'MB', 'light_gray');
        CLI::write('Memory after worker: ' . round($afterMemory, 2) . 'MB', 'light_gray');
        CLI::write('Worker creation cost: ' . round($afterMemory - $beforeMemory, 2) . 'MB', 'light_gray');
        CLI::newLine();
    }

    protected function showConfigInfo(): void
    {
        CLI::write('Configuration Details', 'green');
        CLI::write('=====================', 'green');

        $queueManager = $this->queueService->getQueueManager();
        $connections = $queueManager->getConnections();

        CLI::write('Available Connections:', 'yellow');
        foreach ($connections as $name => $connection) {
            CLI::write('  - ' . $name . ' (' . get_class($connection) . ')', 'light_gray');
        }
        CLI::newLine();
    }

    protected function showRestartStatus(): void
    {
        CLI::write('Worker Status', 'green');
        CLI::write('=============', 'green');

        $cacheKey = 'illuminate_queue_restart';
        try {
            $restartSignal = cache()->get($cacheKey);

            if ($restartSignal !== null) {
                $signalTime = date('Y-m-d H:i:s', $restartSignal);
                CLI::write('Restart Signal: ACTIVE (set at ' . $signalTime . ')', 'red');
                CLI::write('Workers will restart after their current job', 'yellow');
            } else {
                CLI::write('Restart Signal: NONE', 'green');
            }
        } catch (\Exception $e) {
            CLI::write('Restart Signal: ERROR (' . $e->getMessage() . ')', 'red');
        }

        CLI::newLine();
    }

    protected function getQueues(): array
    {
        $queues = CLI::getOption('queue');

        if ($queues) {
            return array_map('trim', explode(',', $queues));
        }

        return ['default'];
    }
}
