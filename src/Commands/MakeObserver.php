<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MakeObserver extends BaseCommand
{
    protected $group = 'Generators';
    protected $name = 'make:observer';
    protected $description = 'Creates a new Eloquent model observer class.';
    protected $usage = 'make:observer <name> [options]';

    protected $arguments = [
        'name' => 'The observer class name.',
    ];

    protected $options = [
        '--model' => 'The model class that the observer applies to.',
        '--force' => 'Force overwrite existing file.',
    ];

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

    protected function getObserverName(array $params): ?string
    {
        $name = array_shift($params) ?: CLI::prompt('Observer name');

        if (empty($name)) {
            CLI::error('You must provide an observer name.');

            return null;
        }

        return $name;
    }

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

    protected function hasForceFlag(): bool
    {
        global $argv;

        return in_array('--force', $argv);
    }

    protected function normalizeObserverName(string $name): string
    {
        return str_ends_with($name, 'Observer') ? $name : $name.'Observer';
    }

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

    protected function getObserverFilePath(string $name): string
    {
        return APPPATH.'Observers/'.$name.'.php';
    }

    protected function canCreateFile(string $filePath, bool $force): bool
    {
        if (! file_exists($filePath) || $force) {
            return true;
        }

        CLI::error('Observer already exists. Use --force to overwrite.');

        return false;
    }

    protected function ensureObserverDirectory(): void
    {
        $observerPath = APPPATH.'Observers/';
        if (! is_dir($observerPath)) {
            mkdir($observerPath, 0755, true);
        }
    }

    protected function writeObserverFile(string $filePath, string $content): bool
    {
        if (file_put_contents($filePath, $content)) {
            return true;
        }

        CLI::error("Failed to create observer: {$filePath}");

        return false;
    }

    protected function generateObserverContent(string $observerName, ?string $model): string
    {
        if (! $model) {
            return $this->getGenericObserverTemplate($observerName);
        }

        return $this->getModelSpecificObserverTemplate($observerName, $model);
    }

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

    protected function getGenericObserverTemplate(string $observerName): string
    {
        return $this->buildObserverClass($observerName, 'Model', []);
    }

    protected function extractModelName(string $model): string
    {
        return str_ends_with($model, 'Model') ? substr($model, 0, -5) : $model;
    }

    protected function getObserverEvents(): array
    {
        return [
            'retrieved', 'creating', 'created', 'updating', 'updated',
            'saving', 'saved', 'deleting', 'deleted', 'restoring',
            'restored', 'forceDeleted',
        ];
    }

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

    protected function showSuccessMessage(string $filePath, string $observerName, ?string $model): void
    {
        CLI::write('Observer created: '.CLI::color($filePath, 'green'));
        CLI::newLine();
        CLI::write(CLI::color('Next steps:', 'yellow'));

        $this->showRegistrationInstructions($observerName, $model);
        CLI::newLine();
    }

    protected function showRegistrationInstructions(string $observerName, ?string $model): void
    {
        if ($model) {
            $instruction = "\\App\\Models\\{$model}::class => \\App\\Observers\\{$observerName}::class,";
        } else {
            $instruction = "\\App\\Models\\YourModel::class => \\App\\Observers\\{$observerName}::class,";
        }

        CLI::write('Add to app/Config/Observers.php:');
        CLI::write(CLI::color("   {$instruction}", 'cyan'));
    }
}
