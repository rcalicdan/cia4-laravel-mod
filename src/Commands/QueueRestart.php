<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class QueueRestart extends BaseCommand
{
    protected $group = 'Queue';
    protected $name = 'queue:restart';
    protected $description = 'Restart queue worker daemons after their current job';
    protected $usage = 'queue:restart';

    public function run(array $params)
    {
        cache()->save('illuminate:queue:restart', time());

        CLI::write('Broadcasting queue restart signal.', 'green');
        CLI::write('All queue workers will restart after processing their current job.', 'yellow');
    }
}
