<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelModel;

use Illuminate\Support\Str;

class ModelCodeGenerator
{
    /**
     * Generates model class code using resolved details.
     *
     * @param  array  $details  Resolved model details.
     * @return string The generated model code.
     */
    public function generateModelCode(array $details): string
    {
        ['fullNamespace' => $fullNamespace, 'baseClassName' => $baseClassName] = $details;
        $tableName = $this->getTableName($baseClassName);

        // Define fillable property (initially empty)
        $fillableProperty = 'protected $fillable = [];';

        return <<<PHP
<?php

namespace {$fullNamespace};

use Illuminate\Database\Eloquent\Model;

class {$baseClassName} extends Model
{
    protected \$table = '{$tableName}';
    {$fillableProperty}
}
PHP;
    }

    /**
     * Converts a base model name (PascalCase) to a table name (snake_case, plural).
     * Requires illuminate/support package.
     *
     * @param  string  $baseModelName  The base model class name (e.g., User, ProductCategory).
     * @return string The table name (e.g., users, product_categories).
     */
    public function getTableName(string $baseModelName): string
    {
        return Str::plural(Str::snake($baseModelName));
    }
}
