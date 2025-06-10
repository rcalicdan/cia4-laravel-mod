<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Creates a new Eloquent model observer class.
 */
class MakeObserver extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'make:observer';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Creates a new Eloquent model observer class.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:observer <name> [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'name' => 'The observer class name.',
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--model' => 'The model class that the observer applies to.',
        '--force' => 'Force overwrite existing file.',
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $name = array_shift($params);

        if (empty($name)) {
            $name = CLI::prompt('Observer name');
        }

        if (empty($name)) {
            CLI::error('You must provide an observer name.');
            return;
        }

        $model = CLI::getOption('model');
        $force = CLI::getOption('force');

        // Ensure the name ends with 'Observer'
        if (!str_ends_with($name, 'Observer')) {
            $name .= 'Observer';
        }

        // Create the observer
        $this->createObserver($name, $model, $force);
    }

    /**
     * Create the observer file
     *
     * @param string $name
     * @param string|null $model
     * @param bool $force
     */
    protected function createObserver(string $name, ?string $model = null, bool $force = false): void
    {
        $observerPath = APPPATH . 'Observers/';
        $observerFile = $observerPath . $name . '.php';

        // Create directory if it doesn't exist
        if (!is_dir($observerPath)) {
            mkdir($observerPath, 0755, true);
        }

        // Check if file already exists
        if (file_exists($observerFile) && !$force) {
            CLI::error("Observer {$name} already exists. Use --force to overwrite.");
            return;
        }

        // Generate the observer content
        $content = $this->generateObserverContent($name, $model);

        // Write the file
        if (file_put_contents($observerFile, $content)) {
            CLI::write("Observer created: " . CLI::color($observerFile, 'green'));
            
            // Show instruction to register the observer
            $this->showRegistrationInstructions($name, $model);
        } else {
            CLI::error("Failed to create observer: {$observerFile}");
        }
    }

    /**
     * Generate the observer class content
     *
     * @param string $name
     * @param string|null $model
     * @return string
     */
    protected function generateObserverContent(string $name, ?string $model = null): string
    {
        $modelName = $model ? $this->getModelName($model) : 'Model';
        $modelClass = $model ? "\\App\\Models\\{$model}" : 'Model';
        $modelVariable = strtolower($modelName);

        $template = <<<EOT
<?php

namespace App\Observers;

/**
 * {$name}
 * 
 * Observer for {$modelClass}
 */
class {$name}
{
    /**
     * Handle the {$modelName} "retrieved" event.
     */
    public function retrieved({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "creating" event.
     */
    public function creating({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "created" event.
     */
    public function created({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "updating" event.
     */
    public function updating({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "updated" event.
     */
    public function updated({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "saving" event.
     */
    public function saving({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "saved" event.
     */
    public function saved({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "deleting" event.
     */
    public function deleting({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "deleted" event.
     */
    public function deleted({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "restoring" event.
     */
    public function restoring({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "restored" event.
     */
    public function restored({$modelName} \${$modelVariable}): void
    {
        //
    }

    /**
     * Handle the {$modelName} "force deleted" event.
     */
    public function forceDeleted({$modelName} \${$modelVariable}): void
    {
        //
    }
}
EOT;

        return $template;
    }

    /**
     * Get the model name from the model class
     *
     * @param string $model
     * @return string
     */
    protected function getModelName(string $model): string
    {
        if (str_ends_with($model, 'Model')) {
            return substr($model, 0, -5);
        }

        return $model;
    }

    /**
     * Show instructions for registering the observer
     *
     * @param string $name
     * @param string|null $model
     */
    protected function showRegistrationInstructions(string $name, ?string $model = null): void
    {
        CLI::newLine();
        CLI::write(CLI::color('Next steps:', 'yellow'));
        
        if ($model) {
            CLI::write('1. Add the following to your app/Config/Observers.php:');
            CLI::write(CLI::color("   \\App\\Models\\{$model}::class => \\App\\Observers\\{$name}::class,", 'cyan'));
        } else {
            CLI::write('1. Add your observer to app/Config/Observers.php');
            CLI::write(CLI::color("   \\App\\Models\\YourModel::class => \\App\\Observers\\{$name}::class,", 'cyan'));
        }
        
        CLI::write('2. The observer will be automatically registered when your application boots.');
        CLI::newLine();
    }
}