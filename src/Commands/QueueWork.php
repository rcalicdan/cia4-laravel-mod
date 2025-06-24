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
        '--quiet' => 'Suppress all output except errors',
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
     * Statistics tracking
     */
    protected array $stats = [
        'processed' => 0,
        'failed' => 0,
        'released' => 0,
        'start_time' => 0,
        'last_job_time' => 0,
        'total_processing_time' => 0,
    ];

    /**
     * Display configuration
     */
    protected bool $isVerbose = false;
    protected bool $isQuiet = false;
    protected int $displayWidth = 80;

    public function run(array $params)
    {
        try {
            $this->setupConfiguration();
            $this->setupWorker();
            $this->stats['start_time'] = microtime(true);
            
            $connection = $params[0] ?? null;
            $queues = $this->getQueues();
            $options = $this->getWorkerOptions();

            if (!$this->isQuiet) {
                $this->displayHeader($connection, $queues, $options);
            }

            if (CLI::getOption('once')) {
                $this->runNextJob($connection, $queues, $options);
            } else {
                $this->runWorker($connection, $queues, $options);
            }

            if (!$this->isQuiet) {
                $this->displayFooter();
            }

            return EXIT_SUCCESS;
        } catch (Throwable $e) {
            CLI::error('Queue worker error: ' . $e->getMessage());
            
            if (ENVIRONMENT === 'development' || $this->isVerbose) {
                CLI::error('File: ' . $e->getFile() . ':' . $e->getLine());
                CLI::error('Stack trace:');
                CLI::error($e->getTraceAsString());
            }

            return EXIT_ERROR;
        }
    }

    /**
     * Setup display configuration
     */
    protected function setupConfiguration(): void
    {
        $this->isVerbose = CLI::getOption('verbose') !== null;
        $this->isQuiet = CLI::getOption('quiet') !== null;
        
        // Try to determine terminal width
        $width = exec('tput cols 2>/dev/null');
        $this->displayWidth = is_numeric($width) ? (int)$width : 80;
        $this->displayWidth = max(60, min(120, $this->displayWidth)); // Clamp between 60-120
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
     * Display enhanced header with worker information
     */
    protected function displayHeader(?string $connection, array $queues, WorkerOptions $options): void
    {
        $connection = $connection ?? $this->queueService->getQueueManager()->getDefaultDriver();
        
        CLI::write($this->createSeparator('='), 'cyan');
        CLI::write($this->centerText('QUEUE WORKER STARTED'), 'green');
        CLI::write($this->createSeparator('='), 'cyan');
        
        CLI::newLine();
        
        // Worker configuration
        $this->displayConfigSection([
            'Connection' => $connection,
            'Queue(s)' => implode(', ', $queues),
            'Process ID' => getmypid(),
            'Memory Limit' => $options->memory . 'MB',
            'Timeout' => $options->timeout . 's',
            'Max Tries' => $options->maxTries,
            'Sleep Time' => $options->sleep . 's',
        ]);

        // Worker mode
        $mode = CLI::getOption('once') ? 'Single Job' : 
               (CLI::getOption('stop-when-empty') ? 'Until Empty' : 'Continuous');
        
        CLI::write('Mode: ' . CLI::color($mode, 'cyan'), 'white');
        
        if (!CLI::getOption('once')) {
            CLI::write('Press ' . CLI::color('Ctrl+C', 'yellow') . ' to stop gracefully', 'light_gray');
        }

        CLI::newLine();
        CLI::write($this->createSeparator('-'), 'dark_gray');
        CLI::write($this->formatLogHeader(), 'white');
        CLI::write($this->createSeparator('-'), 'dark_gray');
    }

    /**
     * Display configuration section
     */
    protected function displayConfigSection(array $config): void
    {
        foreach ($config as $key => $value) {
            CLI::write(sprintf('%-12s: %s', $key, CLI::color($value, 'yellow')), 'white');
        }
        CLI::newLine();
    }

    /**
     * Format log header
     */
    protected function formatLogHeader(): string
    {
        return sprintf(
            '%-19s %-8s %-30s %-10s %s',
            'TIMESTAMP',
            'STATUS',
            'JOB',
            'DURATION',
            'DETAILS'
        );
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
    }

    /**
     * Run a single job
     */
    protected function runNextJob(?string $connection, array $queues, WorkerOptions $options): void
    {
        $jobStartTime = microtime(true);
        
        // Check if there are jobs in the queue before processing
        if (!$this->hasJobsInQueue($connection, $queues)) {
            if (!$this->isQuiet) {
                $this->displayNoJobsMessage();
            }
            return;
        }

        $response = $this->worker->runNextJob(
            $connection,
            implode(',', $queues),
            $options
        );

        $processingTime = microtime(true) - $jobStartTime;
        $this->stats['total_processing_time'] += $processingTime;

        if (!$this->isQuiet) {
            $this->handleJobResponse($response, $processingTime);
        }
        
        $this->updateStats($response);
    }

    /**
     * Run the worker continuously or until empty
     */
    protected function runWorker(?string $connection, array $queues, WorkerOptions $options): void
    {
        $startTime = time();
        $maxJobs = $options->maxJobs;
        $maxTime = $options->maxTime;
        $restTime = (int) CLI::getOption('rest', 0);
        $stopWhenEmpty = $options->stopWhenEmpty;
        $emptyQueueCount = 0;
        $maxEmptyChecks = 3; // Check 3 times before showing "waiting" message

        while (true) {
            // Check time limit
            if ($maxTime > 0 && (time() - $startTime) >= $maxTime) {
                if (!$this->isQuiet) {
                    $this->displayStopReason('Maximum time limit reached');
                }
                break;
            }

            // Check job limit
            if ($maxJobs > 0 && $this->stats['processed'] >= $maxJobs) {
                if (!$this->isQuiet) {
                    $this->displayStopReason('Maximum job limit reached');
                }
                break;
            }

            // Check memory limit
            if ($this->memoryExceeded($options->memory)) {
                if (!$this->isQuiet) {
                    $this->displayStopReason('Memory limit exceeded');
                }
                break;
            }

            // Check for restart signal
            if ($this->shouldRestart()) {
                if (!$this->isQuiet) {
                    $this->displayStopReason('Restart signal received');
                }
                break;
            }

            try {
                $jobStartTime = microtime(true);
                
                // Check if there are jobs before processing
                $hasJobs = $this->hasJobsInQueue($connection, $queues);
                
                if (!$hasJobs) {
                    $emptyQueueCount++;
                    
                    if ($stopWhenEmpty) {
                        if (!$this->isQuiet) {
                            $this->displayStopReason('Queue is empty');
                        }
                        break;
                    }
                    
                    // Show waiting message only after several empty checks
                    if ($emptyQueueCount >= $maxEmptyChecks && !$this->isQuiet) {
                        $this->displayWaitingMessage($options->sleep);
                        $emptyQueueCount = 0; // Reset counter
                    }
                    
                    sleep($options->sleep);
                    continue;
                }

                // Reset empty queue counter when jobs are found
                $emptyQueueCount = 0;

                $response = $this->worker->runNextJob(
                    $connection,
                    implode(',', $queues),
                    $options
                );

                $processingTime = microtime(true) - $jobStartTime;
                $this->stats['total_processing_time'] += $processingTime;
                $this->stats['last_job_time'] = time();

                if (!$this->isQuiet) {
                    $this->handleJobResponse($response, $processingTime, $hasJobs);
                }
                
                $this->updateStats($response);

                if ($restTime > 0) {
                    sleep($restTime);
                }

            } catch (Throwable $e) {
                log_message('error', 'Queue worker exception: ' . $e->getMessage());
                log_message('error', 'Exception trace: ' . $e->getTraceAsString());

                if (!$this->isQuiet) {
                    $this->displayError($e->getMessage());
                }

                sleep($options->sleep);
            }
        }
    }

    /**
     * Check if there are jobs in the queue
     */
    protected function hasJobsInQueue(?string $connection, array $queues): bool
    {
        try {
            $queueManager = $this->queueService->getQueueManager();
            $queueConnection = $queueManager->connection($connection);
            
            foreach ($queues as $queue) {
                $size = $queueConnection->size($queue);
                if ($size > 0) {
                    return true;
                }
            }
            
            return false;
        } catch (Throwable $e) {
            // If we can't check queue size, assume there might be jobs
            log_message('warning', 'Could not check queue size: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Handle job response and display status
     */
    protected function handleJobResponse($response, float $processingTime, bool $hasJobs = true): void
    {
        if (!$response && !$hasJobs) {
            return; // Don't show anything for empty queue responses
        }

        $timestamp = date('H:i:s');
        $duration = $this->formatDuration($processingTime);
        
        $logLine = sprintf('%-19s ', "[{$timestamp}]");

        switch ($response) {
            case 0:
                $status = CLI::color('SUCCESS', 'green');
                $jobInfo = $this->getJobInfo();
                $details = 'Job completed successfully';
                break;
            case 1:
                $status = CLI::color('FAILED', 'red');
                $jobInfo = $this->getJobInfo();
                $details = 'Job failed and will be retried';
                break;
            case 2:
                $status = CLI::color('RELEASED', 'yellow');
                $jobInfo = $this->getJobInfo();
                $details = 'Job released back to queue';
                break;
            default:
                if (!$response) {
                    return; // Don't display anything for null responses when queue is empty
                }
                $status = CLI::color('UNKNOWN', 'light_gray');
                $jobInfo = 'Unknown Job';
                $details = "Response: {$response}";
        }

        $logLine .= sprintf('%-8s %-30s %-10s %s', 
            $status, 
            $this->truncateText($jobInfo, 30), 
            CLI::color($duration, 'cyan'),
            $details
        );

        CLI::write($logLine);
        
        // Show memory usage in verbose mode
        if ($this->isVerbose && $response !== null) {
            $this->displayMemoryUsage();
        }
    }

    /**
     * Get job information (placeholder - you might want to enhance this)
     */
    protected function getJobInfo(): string
    {
        // This is a placeholder. In a real implementation, you might want to
        // extract job class name from the worker or job payload
        return 'Job #' . ($this->stats['processed'] + $this->stats['failed'] + $this->stats['released'] + 1);
    }

    /**
     * Display memory usage
     */
    protected function displayMemoryUsage(): void
    {
        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        
        CLI::write(sprintf('    Memory: Current %sMB | Peak %sMB', 
            CLI::color(number_format($currentMemory, 1), 'yellow'),
            CLI::color(number_format($peakMemory, 1), 'yellow')
        ), 'dark_gray');
    }

    /**
     * Display waiting message
     */
    protected function displayWaitingMessage(int $sleepTime): void
    {
        $timestamp = date('H:i:s');
        CLI::write(sprintf('[%s] %s - waiting %ds for new jobs...', 
            $timestamp,
            CLI::color('WAITING', 'blue'),
            $sleepTime
        ), 'dark_gray');
    }

    /**
     * Display no jobs message for single job mode
     */
    protected function displayNoJobsMessage(): void
    {
        $timestamp = date('H:i:s');
        CLI::write(sprintf('[%s] %s - No jobs available in queue', 
            $timestamp,
            CLI::color('EMPTY', 'yellow')
        ), 'yellow');
    }

    /**
     * Display stop reason
     */
    protected function displayStopReason(string $reason): void
    {
        CLI::newLine();
        CLI::write(CLI::color("Stopping worker: {$reason}", 'yellow'));
    }

    /**
     * Display error message
     */
    protected function displayError(string $message): void
    {
        $timestamp = date('H:i:s');
        CLI::write(sprintf('[%s] %s - %s', 
            $timestamp,
            CLI::color('ERROR', 'red'),
            $message
        ), 'red');
    }

    /**
     * Update statistics
     */
    protected function updateStats($response): void
    {
        switch ($response) {
            case 0:
                $this->stats['processed']++;
                break;
            case 1:
                $this->stats['failed']++;
                break;
            case 2:
                $this->stats['released']++;
                break;
        }
    }

    /**
     * Display footer with statistics
     */
    protected function displayFooter(): void
    {
        $runtime = microtime(true) - $this->stats['start_time'];
        
        CLI::newLine();
        CLI::write($this->createSeparator('-'), 'dark_gray');
        CLI::write($this->centerText('WORKER STATISTICS'), 'cyan');
        CLI::write($this->createSeparator('-'), 'dark_gray');
        
        $totalJobs = $this->stats['processed'] + $this->stats['failed'] + $this->stats['released'];
        $avgProcessingTime = $totalJobs > 0 ? $this->stats['total_processing_time'] / $totalJobs : 0;
        
        $this->displayConfigSection([
            'Runtime' => $this->formatDuration($runtime),
            'Jobs Processed' => CLI::color($this->stats['processed'], 'green'),
            'Jobs Failed' => CLI::color($this->stats['failed'], 'red'),
            'Jobs Released' => CLI::color($this->stats['released'], 'yellow'),
            'Total Jobs' => $totalJobs,
            'Avg Process Time' => $this->formatDuration($avgProcessingTime),
            'Peak Memory' => number_format(memory_get_peak_usage(true) / 1024 / 1024, 1) . 'MB',
        ]);
        
        CLI::write($this->createSeparator('='), 'cyan');
    }

    /**
     * Format duration in human readable format
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return number_format($seconds * 1000, 0) . 'ms';
        } elseif ($seconds < 60) {
            return number_format($seconds, 2) . 's';
        } else {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return sprintf('%dm %ds', $minutes, $remainingSeconds);
        }
    }

    /**
     * Create separator line
     */
    protected function createSeparator(string $char): string
    {
        return str_repeat($char, $this->displayWidth);
    }

    /**
     * Center text within display width
     */
    protected function centerText(string $text): string
    {
        $padding = max(0, ($this->displayWidth - strlen($text)) / 2);
        return str_repeat(' ', (int)$padding) . $text;
    }

    /**
     * Truncate text to specified length
     */
    protected function truncateText(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return substr($text, 0, $length - 3) . '...';
    }

    /**
     * Check if memory limit is exceeded
     */
    protected function memoryExceeded(int $memoryLimit): bool
    {
        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        $exceeded = $currentMemory >= $memoryLimit;

        if ($exceeded && !$this->isQuiet) {
            CLI::write(sprintf("Memory limit exceeded: %sMB >= %dMB", 
                number_format($currentMemory, 2), 
                $memoryLimit
            ), 'red');
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