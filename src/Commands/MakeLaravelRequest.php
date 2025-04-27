<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class MakeLaravelRequest extends BaseCommand
{
    /**
     * The group the command is lumped under when listing commands.
     *
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The command's name.
     *
     * @var string
     */
    protected $name = 'make:request';

    /**
     * The command's short description.
     *
     * @var string
     */
    protected $description = 'Creates a new Laravel-style Form Request class file.';

    /**
     * The command's usage.
     *
     * @var string
     */
    protected $usage = 'make:request [class_name]';

    /**
     * The command's arguments.
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'class_name' => 'The name of the class file to create, including any namespaces.',
    ];

    /**
     * Execute the command.
     *
     * @param  array  $params  Command parameters
     * @return void
     */
    public function run(array $params)
    {
        $className = $this->getClassName($params);

        if (empty($className)) {
            return;
        }

        [$namespace, $className, $path] = $this->parseClassDetails($className);

        if ($this->fileExists($path)) {
            return;
        }

        $template = $this->buildTemplate($namespace, $className);
        $this->createFile($path, $template);
    }

    /**
     * Get the class name from parameters or prompt for it.
     *
     * @param  array  $params  Command parameters
     * @return string The class name or empty string if invalid
     */
    protected function getClassName(array $params): string
    {
        $className = array_shift($params);

        if (empty($className)) {
            $className = CLI::prompt('Enter the request class name');

            if (empty($className)) {
                CLI::error('Request class name cannot be empty.');

                return '';
            }
        }

        return $className;
    }

    /**
     * Parse the input class name into namespace, class name, and file path.
     *
     * @param  string  $input  The raw class name input
     * @return array Array containing [namespace, className, filePath]
     */
    protected function parseClassDetails(string $input): array
    {
        $segments = explode('/', $input);
        $className = array_pop($segments);

        if (! str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }

        $namespace = 'App\Requests';
        if (! empty($segments)) {
            $namespace .= '\\'.implode('\\', $segments);
        }

        $directory = $this->createDirectoryStructure($segments);
        $path = $directory.'/'.$className.'.php';

        return [$namespace, $className, $path];
    }

    /**
     * Create directory structure for the request class.
     *
     * @param  array  $segments  Directory segments
     * @return string The final directory path
     */
    protected function createDirectoryStructure(array $segments): string
    {
        $directory = APPPATH.'Requests';

        foreach ($segments as $segment) {
            $directory .= '/'.$segment;
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        return $directory;
    }

    /**
     * Check if the file already exists.
     *
     * @param  string  $path  File path to check
     * @return bool True if file exists, false otherwise
     */
    protected function fileExists(string $path): bool
    {
        if (file_exists($path)) {
            CLI::error(basename($path, '.php').' already exists!');

            return true;
        }

        return false;
    }

    /**
     * Build the request class template.
     *
     * @param  string  $namespace  The namespace for the class
     * @param  string  $className  The class name
     * @return string The complete class template
     */
    protected function buildTemplate(string $namespace, string $className): string
    {
        return <<<EOD
<?php

namespace {$namespace};

use Rcalicdan\Ci4Larabridge\Validation\FormRequest;

class {$className} extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // Define your validation rules here
        ];
    }
    
    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            // Define your custom messages here (optional)
        ];
    }
    
    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            // Define your custom attribute names here (optional)
        ];
    }
}
EOD;
    }

    /**
     * Create the file with the given content.
     *
     * @param  string  $path  The file path
     * @param  string  $content  The file content
     */
    protected function createFile(string $path, string $content): void
    {
        if (write_file($path, $content)) {
            CLI::write('Created: '.CLI::color($path, 'green'));
        } else {
            CLI::error('Error creating file: '.$path);
        }
    }
}
