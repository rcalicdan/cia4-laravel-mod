<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelMigration;

use Illuminate\Support\Str;

class MigrationCodeGenerator
{
    /**
     * Generates migration code for creating a new table.
     *
     * @param  string  $tableName  The table name.
     * @return string The migration code.
     */
    public function generateCreateMigrationCode(string $tableName): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for creating the '{$tableName}' table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates the {$tableName} table.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id(); 
            \$table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     * Drops the {$tableName} table.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};

PHP;
    }

    /**
     * Generates migration code for modifying an existing table.
     *
     * @param  string  $tableName  The table name.
     * @return string The migration code.
     */
    public function generateModifyMigrationCode(string $tableName): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for modifying the '{$tableName}' table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     * Apply changes to the {$tableName} table.
     */
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            //Add or modify columns here;
        });
    }

    /**
     * Reverse the migrations.
     * Revert changes applied to the {$tableName} table in the up() method.
     */
    public function down(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            //Revert changes here;
        });
    }
};

PHP;
    }

    /**
     * Generates generic migration code when table cannot be inferred.
     *
     * @return string The migration code.
     */
    public function generateGenericMigrationCode(): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; 

/**
 * Generic migration for schema or data changes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     * Apply necessary changes.
     */
    public function up(): void
    {
        // Implement migration logic here.
        // This could involve Schema::table(), Schema::create(),
    }

    /**
     * Reverse the migrations.
     * Revert the changes made in the up() method.
     */
    public function down(): void
    {
        // TODO: Reverse the logic implemented in the up() method.
    }
};
PHP;
    }

    /**
     * Infers the table name for a "create" migration using naming conventions.
     *
     * @param  string  $migrationName  PascalCase or snake_case migration name.
     * @return string|null The inferred table name (e.g., 'users', 'product_categories') or null.
     */
    public function inferTableNameForCreate(string $migrationName): ?string
    {
        // Convert to snake_case for consistent parsing
        $snakeCase = Str::snake($migrationName);

        // Match 'create_TABLE_NAME_table' pattern
        if (preg_match('/^create_([a-z0-9_]+?)_table$/', $snakeCase, $matches)) {
            return $matches[1]; // Return the captured table name part
        }

        return null; // Pattern not matched
    }
}
