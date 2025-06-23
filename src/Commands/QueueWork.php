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
    protected $description = 'Start processing jobs on the queue as a daemon';

    /**
     * The Command's Usage
     */
    protected $usage = 'queue:work [connection] [options]';

    /**
     * The Command's Arguments
     */
    protected $arguments = [
        'connection' => 'The name of the queue connection to work'
    ];

    /**
     * The Command's Options
     */
    protected $options = [
        '--queue'         => 'The names of the queues to work (comma-separated)',
        '--daemon'        => 'Run the worker in daemon mode (stays alive)',
        '--once'          => 'Only process the next job on the queue',
        '--stop-when-empty' => 'Stop when the queue is empty',
        '--delay'         => 'The number of seconds to delay failed jobs (default: 0)',
        '--backoff'       => 'The number of seconds to wait before retrying (default: 0)',
        '--max-jobs'      => 'The number of jobs to process before stopping (default: 1000)',
        '--max-time'      => 'The maximum number of seconds the worker should run (default: 3600)',
        '--force'         => 'Force the worker to run even in maintenance mode',
        '--memory'        => 'The memory limit in megabytes (default: 128)',
        '--sleep'         => 'Number of seconds to sleep when no job is available (default: 3)',
        '--timeout'       => 'The number of seconds a job can run before timing out (default: 60)',
        '--tries'         => 'Number of times to attempt a job before logging it failed (default: 3)',
        '--rest'          => 'Number of seconds to rest between jobs in daemon mode (default: 0)',
        '--name'          => 'The name of the worker',
        '--env'           => 'The environment the command should run under',
    ];

    /**
     * Worker instance
     */
    protected $worker;

    /**
     * Queue service instance
     */
    protected QueueService $queueService;

    /**
     * Track if worker should stop
     */
    protected bool $shouldStop = false;

    /**
     * Track if PCNTL is available
     */
    protected bool $pcntlAvailable = false;

    public function run(array $params)
    {
        try {
            $this->checkEnvironment();
            $this->setupWorker();
            $this->showWorkerInfo($params);

            $connection = $params[0] ?? null;
            $queues = $this->getQueues();
            $options = $this->getWorkerOptions();

            if (CLI::getOption('once')) {
                $this->runNextJob($connection, $queues, $options);
            } else {
                $this->runWorker($connection, $queues, $options);
            }
        } catch (Throwable $e) {
            CLI::error('Queue worker error: ' . $e->getMessage());
            CLI::error('File: ' . $e->getFile() . ':' . $e->getLine());
            if (ENVIRONMENT === 'development') {
                CLI::error('Trace: ' . $e->getTraceAsString());
            }
            return EXIT_ERROR;
        }
    }

    /**
     * Check environment and show warnings
     */
    protected function checkEnvironment(): void
    {
        $this->pcntlAvailable = extension_loaded('pcntl');

        if (!$this->pcntlAvailable) {
            CLI::write('Warning: PCNTL extension not available', 'red');
            CLI::write('- Signal handling disabled', 'yellow');
            CLI::write('- Use Ctrl+C or kill process to stop worker', 'yellow');
            CLI::write('- Consider installing PCNTL for production use', 'yellow');
            CLI::newLine();
        }

        // Check if running on Windows
        if (PHP_OS_FAMILY === 'Windows' && CLI::getOption('daemon')) {
            CLI::write('Warning: Daemon mode on Windows may not work as expected', 'yellow');
            CLI::write('Consider using a process manager like Supervisor on Linux', 'yellow');
            CLI::newLine();
        }
    }

    /**
     * Setup the worker instance
     */
    protected function setupWorker(): void
    {
        $this->queueService = QueueService::getInstance();
        $this->worker = $this->queueService->createWorker();

        // Register signal handlers only if PCNTL is available
        if ($this->pcntlAvailable) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGQUIT, [$this, 'handleSignal']);
            CLI::write('Signal handlers registered', 'green');
        }

        // Alternative: Set up file-based stop mechanism
        $this->setupStopFile();
    }

    /**
     * Setup file-based stop mechanism as PCNTL alternative
     */
    protected function setupStopFile(): void
    {
        $stopFile = WRITEPATH . 'queue_stop_' . getmypid();

        // Clean up any existing stop file
        if (file_exists($stopFile)) {
            unlink($stopFile);
        }

        // Register shutdown function to clean up
        register_shutdown_function(function () use ($stopFile) {
            if (file_exists($stopFile)) {
                unlink($stopFile);
            }
        });

        CLI::write('Stop file: ' . $stopFile, 'light_gray');
        CLI::write('Create this file to gracefully stop the worker', 'light_gray');
    }

    /**
     * Check if worker should stop (file-based alternative to signals)
     */
    protected function shouldStop(): bool
    {
        if ($this->shouldStop) {
            return true;
        }

        // Check for stop file
        $stopFile = WRITEPATH . 'queue_stop_' . getmypid();
        if (file_exists($stopFile)) {
            CLI::write('Stop file detected. Shutting down gracefully...', 'yellow');
            unlink($stopFile);
            return true;
        }

        // Check for restart signal
        if (cache()->get('illuminate:queue:restart')) {
            CLI::write('Restart signal received. Shutting down...', 'yellow');
            return true;
        }

        return false;
    }

    /**
     * Run worker in daemon mode with improved stop handling
     */
    protected function runDaemon(string $connection, array $queues, WorkerOptions $options): void
    {
        $startTime = time();
        $jobsProcessed = 0;
        $restTime = (int) CLI::getOption('rest', 0);
        $lastMemoryCheck = time();

        CLI::write('Press Ctrl+C to stop (may require force kill without PCNTL)', 'yellow');
        CLI::newLine();

        while (true) {
            if ($this->shouldStop()) {
                CLI::write('Gracefully stopping worker...', 'green');
                break;
            }

            // Check memory limit (every 30 seconds to avoid overhead)
            if (time() - $lastMemoryCheck >= 30) {
                if ($this->memoryExceeded($options->memory)) {
                    CLI::write('Memory limit exceeded. Restarting...', 'red');
                    break;
                }
                $lastMemoryCheck = time();
            }

            // Check time limit
            if ($options->maxTime > 0 && (time() - $startTime) >= $options->maxTime) {
                CLI::write('Max time reached. Stopping...', 'yellow');
                break;
            }

            // Check job limit
            if ($options->maxJobs > 0 && $jobsProcessed >= $options->maxJobs) {
                CLI::write('Max jobs reached. Stopping...', 'yellow');
                break;
            }

            try {
                $response = $this->worker->runNextJob($connection, implode(',', $queues), $options);

                if ($response) {
                    $jobsProcessed++;
                    $this->handleJobResponse($response);

                    if ($restTime > 0) {
                        sleep($restTime);
                    }
                }
            } catch (Throwable $e) {
                CLI::error('Error processing job: ' . $e->getMessage());
                sleep($options->sleep);
            }

            // Handle signals if PCNTL is available
            if ($this->pcntlAvailable) {
                pcntl_signal_dispatch();
            }
        }

        CLI::write("Worker stopped. Processed {$jobsProcessed} jobs.", 'green');
    }

    /**
     * Handle shutdown signals (only called if PCNTL is available)
     */
    public function handleSignal(int $signal): void
    {
        $signalNames = [
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            SIGQUIT => 'SIGQUIT'
        ];

        CLI::newLine();
        CLI::write('Received signal: ' . ($signalNames[$signal] ?? $signal), 'yellow');
        CLI::write('Gracefully shutting down worker...', 'yellow');

        $this->shouldStop = true;
    }

    /**
     * Show worker information with environment details
     */
    protected function showWorkerInfo(array $params): void
    {
        $connection = $params[0] ?? $this->queueService->getQueueManager()->getDefaultDriver();
        $queues = $this->getQueues();

        CLI::write('Queue Worker started', 'green');
        CLI::write('Connection: ' . $connection, 'yellow');
        CLI::write('Queue(s): ' . implode(', ', $queues), 'yellow');
        CLI::write('Memory: ' . CLI::getOption('memory') ?? 128 . 'MB', 'yellow');
        CLI::write('Timeout: ' . CLI::getOption('timeout') ?? 60 . 's', 'yellow');
        CLI::write('Max tries: ' . CLI::getOption('tries') ?? 3, 'yellow');
        CLI::write('PID: ' . getmypid(), 'yellow');
        CLI::write('PCNTL Available: ' . ($this->pcntlAvailable ? 'Yes' : 'No'), 'yellow');
        CLI::write('OS: ' . PHP_OS_FAMILY, 'yellow');

        if (CLI::getOption('daemon')) {
            CLI::write('Mode: Daemon', 'yellow');
        } elseif (CLI::getOption('once')) {
            CLI::write('Mode: Single job', 'yellow');
        } else {
            CLI::write('Mode: Until empty', 'yellow');
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

        return new WorkerOptions(
            (int) (CLI::getOption('memory') ?? $baseOptions->memory),
            (int) (CLI::getOption('timeout') ?? $baseOptions->timeout),
            (int) (CLI::getOption('sleep') ?? $baseOptions->sleep),
            (int) (CLI::getOption('tries') ?? $baseOptions->maxTries),
            (bool) (CLI::getOption('force') ?? false),
            (bool) (CLI::getOption('stop-when-empty') ?? $baseOptions->stopWhenEmpty),
            (int) (CLI::getOption('max-jobs') ?? $baseOptions->maxJobs),
            (int) (CLI::getOption('max-time') ?? $baseOptions->maxTime),
            [],
            (int) (CLI::getOption('backoff') ?? $baseOptions->backoff)
        );
    }

    /**
     * Run a single job
     */
    protected function runNextJob(string $connection, array $queues, WorkerOptions $options): void
    {
        CLI::write('Processing next job...', 'cyan');

        $response = $this->worker->runNextJob($connection, implode(',', $queues), $options);

        $this->handleJobResponse($response);
    }

    /**
     * Run the worker continuously
     */
    protected function runWorker(string $connection, array $queues, WorkerOptions $options): void
    {
        if (CLI::getOption('daemon')) {
            CLI::write('Starting daemon worker...', 'cyan');
            $this->runDaemon($connection, $queues, $options);
        } else {
            CLI::write('Processing jobs until empty...', 'cyan');
            $this->runUntilEmpty($connection, $queues, $options);
        }
    }

    /**
     * Run worker until queue is empty
     */
    protected function runUntilEmpty(string $connection, array $queues, WorkerOptions $options): void
    {
        $jobsProcessed = 0;

        while (true) {
            $response = $this->worker->runNextJob($connection, implode(',', $queues), $options);

            if (!$response) {
                CLI::write('No more jobs available. Stopping...', 'green');
                break;
            }

            $jobsProcessed++;
            $this->handleJobResponse($response);

            if ($options->maxJobs > 0 && $jobsProcessed >= $options->maxJobs) {
                CLI::write('Max jobs reached. Stopping...', 'yellow');
                break;
            }
        }

        CLI::write("Processed {$jobsProcessed} jobs", 'green');
    }

    /**
     * Handle job response
     */
    protected function handleJobResponse($response): void
    {
        if (!$response) {
            return;
        }

        switch ($response) {
            case 0: // Job processed successfully
                CLI::write('[' . date('Y-m-d H:i:s') . '] Job processed successfully', 'green');
                break;
            case 1: // Job failed
                CLI::write('[' . date('Y-m-d H:i:s') . '] Job failed', 'red');
                break;
            case 2: // Job released back to queue
                CLI::write('[' . date('Y-m-d H:i:s') . '] Job released back to queue', 'yellow');
                break;
            default:
                CLI::write('[' . date('Y-m-d H:i:s') . '] Unknown job response: ' . $response, 'light_gray');
        }
    }

    /**
     * Check if memory limit is exceeded
     */
    protected function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $memoryLimit;
    }
}
