<?php

namespace Rcalicdan\Ci4Larabridge\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Command to generate a Laravel-style Form Request class in a CodeIgniter 4 application.
 *
 * This command creates a new Form Request class for handling validation, supporting
 * nested namespaces (e.g., Admin/UserRequest). It generates a class file with a predefined
 * template, including methods for validation rules, custom messages, and attributes.
 * The command ensures the necessary directory structure is created and checks for
 * existing files to prevent overwrites.
 */
class MakeLaravelRequest extends BaseCommand
{
    /**
     * The group this command belongs to.
     *
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The name of the command.
     *
     * @var string
     */
    protected $name = 'make:request';

    /**
     * A brief description of the command's purpose.
     *
     * @var string
     */
    protected $description = 'Creates a new Laravel-style Form Request class file.';

    /**
     * The command's usage instructions.
     *
     * @var string
     */
    protected $usage = 'make:request [class_name]';

    /**
     * Available arguments for the command.
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'class_name' => 'The name of the class file to create, including any namespaces.',
    ];

    /**
     * Executes the command to create a Form Request class.
     *
     * Retrieves or prompts for the class name, parses it into namespace and file path,
     * checks for existing files, generates the class template, and writes the file to
     * the appropriate directory. Provides feedback on success or failure.
     *
     * @param array $params Command parameters, including the class name.
     * @return void
     */
    public function run(array $params): void
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
     * Retrieves the class name from parameters or prompts the user.
     *
     * Ensures a valid class name is provided, displaying an error if the input is empty.
     *
     * @param array $params Command parameters containing the class name.
     * @return string The class name, or empty string if invalid.
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
     * Parses the class name into namespace, class name, and file path.
     *
     * Processes the input to determine the namespace, appends 'Request' to the class
     * name if needed, and constructs the file path based on the namespace structure.
     *
     * @param string $input The raw class name input (e.g., Admin/UserRequest).
     * @return array Array containing [namespace, className, filePath].
     */
    protected function parseClassDetails(string $input): array
    {
        $segments = explode('/', $input);
        $className = array_pop($segments);

        if (!str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }

        $namespace = 'App\Requests';
        if (!empty($segments)) {
            $namespace .= '\\' . implode('\\', $segments);
        }

        $directory = $this->createDirectoryStructure($segments);
        $path = $directory . '/' . $className . '.php';

        return [$namespace, $className, $path];
    }

    /**
     * Creates the directory structure for the request class.
     *
     * Builds the directory path based on the provided segments, creating any missing
     * directories with appropriate permissions.
     *
     * @param array $segments Directory segments from the class name.
     * @return string The final directory path.
     */
    protected function createDirectoryStructure(array $segments): string
    {
        $directory = APPPATH . 'Requests';

        foreach ($segments as $segment) {
            $directory .= '/' . $segment;
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        return $directory;
    }

    /**
     * Checks if the target file already exists.
     *
     * Prevents overwriting by checking for an existing file and displaying an error
     * if found.
     *
     * @param string $path The file path to check.
     * @return bool True if the file exists, false otherwise.
     */
    protected function fileExists(string $path): bool
    {
        if (file_exists($path)) {
            CLI::error(basename($path, '.php') . ' already exists!');
            return true;
        }

        return false;
    }

    /**
     * Generates the Form Request class template.
     *
     * Constructs a PHP class template with the specified namespace and class name,
     * extending the base FormRequest class and including methods for validation rules,
     * messages, and attributes.
     *
     * @param string $namespace The namespace for the class.
     * @param string $className The class name.
     * @return string The complete class template.
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
     * Writes the generated content to a file.
     *
     * Creates the file at the specified path with the provided content, displaying
     * success or error messages based on the outcome.
     *
     * @param string $path The file path.
     * @param string $content The file content.
     * @return void
     */
    protected function createFile(string $path, string $content): void
    {
        if (write_file($path, $content)) {
            CLI::write('Created: ' . CLI::color($path, 'green'));
        } else {
            CLI::error('Error creating file: ' . $path);
        }
    }
}
