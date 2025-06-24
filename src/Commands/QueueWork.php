<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Illuminate\Queue\WorkerOptions;
use Rcalicdan\Ci4Larabridge\Queue\QueueService;
use Throwable;

class QueueWork extends BaseCommand
{
    /**
     * The Command's Group
     */
    protected $group = 'Queue';

    /**
     * The Command's Name
     */
    protected $name = 'queue:work';

    /**
     * The Command's Description
     */
    protected $description = 'Start processing jobs on the queue';

    /**
     * The Command's Usage
     */
    protected $usage = 'queue:work [connection] [options]';

    /**
     * The Command's Arguments
     */
    protected $arguments = [
        'connection' => 'The name of the queue connection to work',
    ];

    /**
     * The Command's Options
     */
    protected $options = [
        '--queue' => 'The names of the queues to work (comma-separated)',
        '--once' => 'Only process the next job on the queue',
        '--stop-when-empty' => 'Stop when the queue is empty',
        '--delay' => 'The number of seconds to delay failed jobs (default: 0)',
        '--max-jobs' => 'The number of jobs to process before stopping (default: 0 = unlimited)',
        '--max-time' => 'The maximum number of seconds the worker should run (default: 3600)',
        '--memory' => 'The memory limit in megabytes (default: 128)',
        '--sleep' => 'Number of seconds to sleep when no job is available (default: 3)',
        '--timeout' => 'The number of seconds a job can run before timing out (default: 60)',
        '--tries' => 'Number of times to attempt a job before logging it failed (default: 3)',
        '--rest' => 'Number of seconds to rest between jobs (default: 0)',
        '--verbose' => 'Display detailed output',
    ];

    /**
     * Worker instance
     */
    protected $worker;

    /**
     * Queue service instance
     */
    protected QueueService $queueService;

    public function run(array $params)
    {
        try {
            $this->setupWorker();
            
            if (CLI::getOption('verbose')) {
                $this->showWorkerInfo($params);
            }

            $connection = $params[0] ?? null;
            $queues = $this->getQueues();
            $options = $this->getWorkerOptions();

            if (CLI::getOption('once')) {
                $this->runNextJob($connection, $queues, $options);
            } else {
                $this->runWorker($connection, $queues, $options);
            }

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('Queue worker error: ' . $e->getMessage());
            
            if (ENVIRONMENT === 'development' || CLI::getOption('verbose')) {
                CLI::error('File: ' . $e->getFile() . ':' . $e->getLine());
                CLI::error('Stack trace:');
                CLI::error($e->getTraceAsString());
            }

            return EXIT_ERROR;
        }
    }

    /**
     * Setup the worker instance
     */
    protected function setupWorker(): void
    {
        $this->queueService = QueueService::getInstance();
        $this->worker = $this->queueService->createWorker();
    }

    /**
     * Show worker information (only when verbose)
     */
    protected function showWorkerInfo(array $params): void
    {
        $connection = $params[0] ?? $this->queueService->getQueueManager()->getDefaultDriver();
        $queues = $this->getQueues();

        CLI::write('Queue Worker Started', 'green');
        CLI::write('Connection: ' . $connection, 'yellow');
        CLI::write('Queue(s): ' . implode(', ', $queues), 'yellow');
        CLI::write('Process ID: ' . getmypid(), 'yellow');

        if (CLI::getOption('once')) {
            CLI::write('Mode: Single job', 'cyan');
        } elseif (CLI::getOption('stop-when-empty')) {
            CLI::write('Mode: Until empty', 'cyan');
        } else {
            CLI::write('Mode: Continuous (use Ctrl+C to stop)', 'cyan');
        }

        CLI::newLine();
    }

    /**
     * Get the queues to work on
     */
    protected function getQueues(): array
    {
        $queues = CLI::getOption('queue');

        if ($queues) {
            return array_map('trim', explode(',', $queues));
        }

        return ['default'];
    }

    /**
     * Get worker options
     */
    protected function getWorkerOptions(): WorkerOptions
    {
        $baseOptions = $this->queueService->getWorkerOptions();

        $workerOptions = new WorkerOptions(
            'default',
            (int) (CLI::getOption('delay') ?? 0),
            (int) (CLI::getOption('memory') ?? $baseOptions->memory),
            (int) (CLI::getOption('timeout') ?? $baseOptions->timeout),
            (int) (CLI::getOption('sleep') ?? $baseOptions->sleep),
            (int) (CLI::getOption('tries') ?? $baseOptions->maxTries),
            false,
            (bool) (CLI::getOption('stop-when-empty') ?? $baseOptions->stopWhenEmpty),
            (int) (CLI::getOption('max-jobs') ?? $baseOptions->maxJobs),
            (int) (CLI::getOption('max-time') ?? $baseOptions->maxTime),
            (int) (CLI::getOption('rest') ?? 0)
        );

        return $workerOptions;
    }

    /**
     * Run a single job
     */
    protected function runNextJob(?string $connection, array $queues, WorkerOptions $options): void
    {
        $response = $this->worker->runNextJob(
            $connection,
            implode(',', $queues),
            $options
        );

        if (CLI::getOption('verbose')) {
            $this->handleJobResponse($response);
            
            if (! $response) {
                CLI::write('No jobs available.', 'yellow');
            }
        }
    }

    /**
     * Run the worker continuously or until empty
     */
    protected function runWorker(?string $connection, array $queues, WorkerOptions $options): void
    {
        $startTime = time();
        $jobsProcessed = 0;
        $maxJobs = $options->maxJobs;
        $maxTime = $options->maxTime;
        $restTime = (int) CLI::getOption('rest', 0);
        $stopWhenEmpty = $options->stopWhenEmpty;

        while (true) {
            // Check time limit
            if ($maxTime > 0 && (time() - $startTime) >= $maxTime) {
                break;
            }

            // Check job limit
            if ($maxJobs > 0 && $jobsProcessed >= $maxJobs) {
                break;
            }

            // Check memory limit
            if ($this->memoryExceeded($options->memory)) {
                break;
            }

            // Check for restart signal
            if ($this->shouldRestart()) {
                break;
            }

            try {
                $response = $this->worker->runNextJob(
                    $connection,
                    implode(',', $queues),
                    $options
                );

                if ($response) {
                    $jobsProcessed++;
                    
                    if (CLI::getOption('verbose')) {
                        $this->handleJobResponse($response);
                    }

                    if ($restTime > 0) {
                        sleep($restTime);
                    }
                } else {
                    if ($stopWhenEmpty) {
                        break;
                    }

                    sleep($options->sleep);
                }
            } catch (Throwable $e) {
                log_message('error', 'Queue worker exception: ' . $e->getMessage());
                log_message('error', 'Exception trace: ' . $e->getTraceAsString());

                if (CLI::getOption('verbose')) {
                    CLI::error('Error processing job: ' . $e->getMessage());
                }

                sleep($options->sleep);
            }
        }
    }

    /**
     * Handle job response and display status (only in verbose mode)
     */
    protected function handleJobResponse($response): void
    {
        if (! $response) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        switch ($response) {
            case 0:
                CLI::write("[{$timestamp}] Job processed successfully", 'green');
                break;
            case 1:
                CLI::write("[{$timestamp}] Job failed", 'red');
                break;
            case 2:
                CLI::write("[{$timestamp}] Job released back to queue", 'yellow');
                break;
            default:
                CLI::write("[{$timestamp}] Job response: {$response}", 'light_gray');
        }
    }

    protected function memoryExceeded(int $memoryLimit): bool
    {
        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        $exceeded = $currentMemory >= $memoryLimit;

        if ($exceeded && CLI::getOption('verbose')) {
            CLI::write("Memory limit exceeded: " . round($currentMemory, 2) . "MB >= {$memoryLimit}MB", 'red');
        }

        return $exceeded;
    }

    /**
     * Check if worker should restart
     */
    protected function shouldRestart(): bool
    {
        $cacheKey = 'illuminate_queue_restart';

        try {
            $restartSignal = cache()->get($cacheKey);
            return $restartSignal !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
}