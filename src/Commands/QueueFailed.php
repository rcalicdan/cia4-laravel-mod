<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Queue\QueueService;

class QueueFailed extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:failed';
    protected $description = 'List all failed queue jobs';
    protected $usage = 'queue:failed';

    public function run(array $params)
    {
        $queueService = QueueService::getInstance();
        $failer = $queueService->getQueueManager()->failer();

        if (! $failer) {
            CLI::error('No failed job provider configured.');

            return EXIT_ERROR;
        }

        $failed = $failer->all();

        if (empty($failed)) {
            CLI::write('No failed jobs found.', 'green');

            return;
        }

        $table = [];
        foreach ($failed as $job) {
            $table[] = [
                $job->id ?? 'N/A',
                $job->queue ?? 'default',
                $job->connection ?? 'default',
                substr($job->payload ?? '', 0, 50).'...',
                $job->failed_at ?? 'N/A',
            ];
        }

        CLI::table($table, ['ID', 'Queue', 'Connection', 'Class', 'Failed At']);
    }
}
