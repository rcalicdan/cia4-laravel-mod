<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Queue\QueueService;

class QueueWork extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:work';
    protected $description = 'Start processing jobs on the queue';

    protected $usage = 'queue:work [connection] [options]';
    protected $arguments = [
        'connection' => 'The name of the queue connection to work',
    ];
    protected $options = [
        '--queue' => 'The name of the queue to work',
        '--sleep' => 'Number of seconds to sleep when no job is available',
        '--tries' => 'Number of times to attempt a job before logging it failed',
        '--timeout' => 'The number of seconds a child process can run',
        '--memory' => 'The memory limit in megabytes',
        '--max-jobs' => 'The number of jobs to process before stopping',
        '--max-time' => 'The maximum number of seconds the worker should run',
        '--force' => 'Force the worker to run even in maintenance mode',
        '--stop-when-empty' => 'Stop when the queue is empty',
    ];

    public function run(array $params)
    {
        $connection = $params[0] ?? null;
        $queueService = QueueService::getInstance();
        
        // Display current configuration
        $this->displayConfiguration($queueService, $connection);
        
        $worker = $queueService->createWorker();
        $options = $queueService->getWorkerOptions();

        // Override options from CLI
        $this->applyCliOptions($options);

        $queue = $this->option('queue') ?? env('QUEUE_NAME', 'default');

        CLI::write("Processing jobs from queue: {$queue}", 'green');
        CLI::write("Connection: " . ($connection ?: env('QUEUE_CONNECTION', 'database')), 'cyan');
        CLI::write('Press Ctrl+C to stop', 'yellow');

        $worker->daemon($connection ?: env('QUEUE_CONNECTION', 'database'), $queue, $options);
    }

    protected function displayConfiguration(QueueService $queueService, ?string $connection): void
    {
        CLI::write('Queue Worker Configuration:', 'yellow');
        CLI::write('=========================', 'yellow');
        
        $config = $queueService->getConnectionConfig($connection ?: env('QUEUE_CONNECTION', 'database'));
        $workerConfig = $queueService->getWorkerConfig();
        
        CLI::write("Driver: {$config['driver']}", 'cyan');
        CLI::write("Host: " . ($config['host'] ?? 'N/A'), 'cyan');
        CLI::write("Queue: " . ($config['queue'] ?? 'default'), 'cyan');
        CLI::write("Retry After: {$config['retry_after']} seconds", 'cyan');
        CLI::write("Worker Memory: {$workerConfig['memory']} MB", 'cyan');
        CLI::write("Worker Timeout: {$workerConfig['timeout']} seconds", 'cyan');
        CLI::write("Worker Sleep: {$workerConfig['sleep']} seconds", 'cyan');
        CLI::write('');
    }

    protected function applyCliOptions($options): void
    {
        if ($this->option('sleep')) {
            $options->sleep = (int) $this->option('sleep');
        }
        if ($this->option('tries')) {
            $options->maxTries = (int) $this->option('tries');
        }
        if ($this->option('timeout')) {
            $options->timeout = (int) $this->option('timeout');
        }
        if ($this->option('memory')) {
            $options->memory = (int) $this->option('memory');
        }
        if ($this->option('max-jobs')) {
            $options->maxJobs = (int) $this->option('max-jobs');
        }
        if ($this->option('max-time')) {
            $options->maxTime = (int) $this->option('max-time');
        }
        if ($this->option('stop-when-empty')) {
            $options->stopWhenEmpty = true;
        }
    }
}