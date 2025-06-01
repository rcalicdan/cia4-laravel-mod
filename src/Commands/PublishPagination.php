<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Traits\FilePublisherTrait;

/**
 * Command to publish pagination templates to application directory
 */
class PublishPagination extends BaseCommand
{
    use FilePublisherTrait;

    /**
     * @var string The group the command is lumped under
     */
    protected $group = 'Pagination';

    /**
     * @var string The Command's name
     */
    protected $name = 'publish:pagination';

    /**
     * @var string The Command's description
     */
    protected $description = 'Publishes eloquent pagination templates to the application directory';

    /**
     * @var string The Command's usage
     */
    protected $usage = 'publish:pagination';

    /**
     * @var array The Command's arguments
     */
    protected $arguments = [];

    /**
     * @var array The Command's options
     */
    protected $options = [];

    /**
     * Executes the command to publish pagination templates
     *
     * @param array $params Command parameters
     * @return void
     */
    public function run(array $params): void
    {
        $sourcePath = $this->getSourcePath();
        $destinationPath = $this->getDestinationPath();

        if ($this->publishFiles($sourcePath, $destinationPath)) {
            CLI::write('Pagination templates published successfully!', 'green');
        }
    }

    /**
     * Gets the source path for pagination templates
     *
     * @return string
     */
    private function getSourcePath(): string
    {
        return __DIR__ . '/../Views/pagination';
    }

    /**
     * Gets the destination path for pagination templates
     *
     * @return string
     */
    private function getDestinationPath(): string
    {
        return APPPATH . 'Views/pagination';
    }
}
