<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class QueueRestart extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:restart';
    protected $description = 'Restart queue worker daemons after their current job';
    protected $usage = 'queue:restart [options]';

    protected $options = [
        '--clear' => 'Clear the restart signal without setting a new one',
    ];

    public function run(array $params)
    {
        $cacheKey = 'illuminate_queue_restart';

        if (CLI::getOption('clear')) {
            return $this->clearRestartSignal($cacheKey);
        }

        return $this->setRestartSignal($cacheKey);
    }

    protected function setRestartSignal(string $cacheKey): int
    {
        try {
            cache()->save($cacheKey, time(), 300);

            CLI::write('Broadcasting queue restart signal.', 'green');
            CLI::write('All queue workers will restart after processing their current job.', 'yellow');
            CLI::write('Restart signal will expire in 5 minutes.', 'light_gray');

            return EXIT_SUCCESS;
        } catch (\Exception $e) {
            CLI::error('Failed to set restart signal: ' . $e->getMessage());
            return EXIT_ERROR;
        }
    }

    protected function clearRestartSignal(string $cacheKey): int
    {
        try {
            cache()->delete($cacheKey);
            CLI::write('Queue restart signal cleared.', 'green');
            return EXIT_SUCCESS;
        } catch (\Exception $e) {
            CLI::error('Failed to clear restart signal: ' . $e->getMessage());
            return EXIT_ERROR;
        }
    }
}
