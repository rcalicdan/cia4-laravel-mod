<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MakeObserver extends BaseCommand
{
    /**
     * The command's group.
     *
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The command's name.
     *
     * @var string
     */
    protected $name = 'make:observer';
    /**
     * The command's description.
     *
     * @var string
     */

    protected $description = 'Creates a new Eloquent model observer class.';
    /**
     * The command's usage.
     *
     * @var string
     */
    
    protected $usage = 'make:observer <name> [options]';

    /**
     * The command's arguments.
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The observer class name.',
    ];

    /**
     * The command's options.
     *
     * @var array<string, string>
     */
    protected $options = [
        '--model' => 'The model class that the observer applies to.',
        '--force' => 'Force overwrite existing file.',
    ];

    /**
     * Execute the console command.
     *
     * @param  array  $params  Command parameters
     * @return void
     */
    public function run(array $params)
    {
        $name = $this->getObserverName($params);
        if (! $name) {
            return;
        }

        $model = $this->getModelFromArguments();
        $force = $this->hasForceFlag();

        $observerName = $this->normalizeObserverName($name);
        $this->createObserver($observerName, $model, $force);
    }

    /**
     * Get the observer name from command parameters.
     *
     * @param  array  $params  Command parameters
     * @return string|null The observer name or null if not provided
     */
    protected function getObserverName(array $params): ?string
    {
        $name = array_shift($params) ?: CLI::prompt('Observer name');

        if (empty($name)) {
            CLI::error('You must provide an observer name.');

            return null;
        }

        return $name;
    }

    /**
     * Extract the model name from command arguments.
     *
     * @return string|null The model name if specified, otherwise null
     */
    protected function getModelFromArguments(): ?string
    {
        global $argv;

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--model=')) {
                return substr($arg, 8);
            }
        }

        return null;
    }

    /**
     * Check if the force flag is present in command arguments.
     *
     * @return bool True if force flag is present, false otherwise
     */
    protected function hasForceFlag(): bool
    {
        global $argv;

        return in_array('--force', $argv);
    }

    /**
     * Normalize the observer name to ensure it has the 'Observer' suffix.
     *
     * @param  string  $name  The raw observer name
     * @return string The normalized observer name
     */
    protected function normalizeObserverName(string $name): string
    {
        return str_ends_with($name, 'Observer') ? $name : $name.'Observer';
    }

    /**
     * Create the observer file with the given name and model.
     *
     * @param  string  $name  The observer class name
     * @param  string|null  $model  The associated model name
     * @param  bool  $force  Whether to force overwrite existing file
     */
    protected function createObserver(string $name, ?string $model, bool $force): void
    {
        $filePath = $this->getObserverFilePath($name);

        if (! $this->canCreateFile($filePath, $force)) {
            return;
        }

        $this->ensureObserverDirectory();
        $content = $this->generateObserverContent($name, $model);

        if ($this->writeObserverFile($filePath, $content)) {
            $this->showSuccessMessage($filePath, $name, $model);
        }
    }

    /**
     * Get the full file path for the observer.
     *
     * @param  string  $name  The observer class name
     * @return string The full file path
     */
    protected function getObserverFilePath(string $name): string
    {
        return APPPATH.'Observers/'.$name.'.php';
    }

    /**
     * Check if the file can be created or overwritten.
     *
     * @param  string  $filePath  The target file path
     * @param  bool  $force  Whether to force overwrite
     * @return bool True if file can be created, false otherwise
     */
    protected function canCreateFile(string $filePath, bool $force): bool
    {
        if (! file_exists($filePath) || $force) {
            return true;
        }

        CLI::error('Observer already exists. Use --force to overwrite.');

        return false;
    }

    /**
     * Ensure the observers directory exists.
     */
    protected function ensureObserverDirectory(): void
    {
        $observerPath = APPPATH.'Observers/';
        if (! is_dir($observerPath)) {
            mkdir($observerPath, 0755, true);
        }
    }

    /**
     * Write the observer file contents.
     *
     * @param  string  $filePath  The target file path
     * @param  string  $content  The file contents
     * @return bool True if write was successful, false otherwise
     */
    protected function writeObserverFile(string $filePath, string $content): bool
    {
        if (file_put_contents($filePath, $content)) {
            return true;
        }

        CLI::error("Failed to create observer: {$filePath}");

        return false;
    }

    /**
     * Generate the appropriate observer content based on whether a model is specified.
     *
     * @param  string  $observerName  The observer class name
     * @param  string|null  $model  The associated model name
     * @return string The generated file content
     */
    protected function generateObserverContent(string $observerName, ?string $model): string
    {
        if (! $model) {
            return $this->getGenericObserverTemplate($observerName);
        }

        return $this->getModelSpecificObserverTemplate($observerName, $model);
    }

    /**
     * Generate a model-specific observer template.
     *
     * @param  string  $observerName  The observer class name
     * @param  string  $model  The associated model name
     * @return string The generated template content
     */
    protected function getModelSpecificObserverTemplate(string $observerName, string $model): string
    {
        $modelName = $this->extractModelName($model);
        $modelVariable = strtolower($modelName);
        $events = $this->getObserverEvents();

        $methods = array_map(
            fn ($event) => $this->generateObserverMethod($event, $modelName, $modelVariable),
            $events
        );

        return $this->buildObserverClass($observerName, $modelName, $methods);
    }

    /**
     * Generate a generic observer template.
     *
     * @param  string  $observerName  The observer class name
     * @return string The generated template content
     */
    protected function getGenericObserverTemplate(string $observerName): string
    {
        return $this->buildObserverClass($observerName, 'Model', []);
    }

    /**
     * Extract the base model name from a fully qualified name.
     *
     * @param  string  $model  The model name
     * @return string The base model name
     */
    protected function extractModelName(string $model): string
    {
        return str_ends_with($model, 'Model') ? substr($model, 0, -5) : $model;
    }

    /**
     * Get the list of observer events to generate methods for.
     *
     * @return array<string> The list of observer events
     */
    protected function getObserverEvents(): array
    {
        return [
            'retrieved',
            'creating',
            'created',
            'updating',
            'updated',
            'saving',
            'saved',
            'deleting',
            'deleted',
            'restoring',
            'restored',
            'forceDeleted',
        ];
    }

    /**
     * Generate an observer method for a specific event.
     *
     * @param  string  $event  The event name
     * @param  string  $modelName  The model class name
     * @param  string  $modelVariable  The model variable name
     * @return string The generated method code
     */
    protected function generateObserverMethod(string $event, string $modelName, string $modelVariable): string
    {
        $eventTitle = ucfirst($event);

        return <<<METHOD
    public function {$event}({$modelName} \${$modelVariable}): void
    {
        //
    }
METHOD;
    }

    /**
     * Build the complete observer class content.
     *
     * @param  string  $observerName  The observer class name
     * @param  string  $modelName  The model class name
     * @param  array<string>  $methods  The generated observer methods
     * @return string The complete class content
     */
    protected function buildObserverClass(string $observerName, string $modelName, array $methods): string
    {
        $useStatement = $modelName !== 'Model' ? "use App\\Models\\{$modelName};" : '';
        $methodsString = implode("\n\n", $methods);

        return <<<TEMPLATE
<?php

namespace App\Observers;

{$useStatement}

class {$observerName}
{
{$methodsString}
}
TEMPLATE;
    }

    /**
     * Display a success message after creating the observer.
     *
     * @param  string  $filePath  The created file path
     * @param  string  $observerName  The observer class name
     * @param  string|null  $model  The associated model name
     */
    protected function showSuccessMessage(string $filePath, string $observerName, ?string $model): void
    {
        CLI::write('Observer created: '.CLI::color($filePath, 'green'));
    }
}
