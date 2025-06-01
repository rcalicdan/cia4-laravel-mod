<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Rcalicdan\Ci4Larabridge\Traits\FilePublisherTrait;

class PublishAuthenticationEmails extends BaseCommand
{
    use FilePublisherTrait;

    /**
     * @var string The group the command is lumped under
     */
    protected $group = 'Email';

    /**
     * @var string The Command's name
     */
    protected $name = 'publish:authentication-emails';

    /**
     * @var string The Command's description
     */
    protected $description = 'Publishes authentication emails to the application directory';

    /**
     * @var string The Command's usage
     */
    protected $usage = 'publish:authentication-emails';

    /**
     * @var array The Command's arguments
     */
    protected $arguments = [];

    /**
     * @var array The Command's options
     */
    protected $options = [];

    /**
     * Executes the command to publish email templates
     *
     * @param  array  $params  Command parameters
     */
    public function run(array $params): void
    {
        $sourcePath = $this->getSourcePath();
        $destinationPath = $this->getDestinationPath();

        if ($this->publishFiles($sourcePath, $destinationPath)) {
            CLI::write('Authentication Email templates published successfully!', 'green');
        }
    }

    /**
     * Gets the source path for email templates
     */
    private function getSourcePath(): string
    {
        return __DIR__.'/../Views/emails';
    }

    /**
     * Gets the destination path for email templates
     */
    private function getDestinationPath(): string
    {
        return APPPATH.'Views/emails';
    }
}
