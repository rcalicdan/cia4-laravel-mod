<?php

namespace Rcalicdan\Ci4Larabridge\Commands\Handlers\MakeLaravelModel;

use CodeIgniter\CLI\CLI;
use Illuminate\Support\Str;

class MigrationGeneratorHandler
{
    /** Standard exit codes */
    public const EXIT_SUCCESS = 0;
    public const EXIT_ERROR   = 1;

    /**
     * Creates a migration file for the model.
     *
     * @param string $baseModelName The base model class name (without namespace path).
     * @return int Exit code (EXIT_SUCCESS or EXIT_ERROR).
     */
    public function createMigrationFile(string $baseModelName): int
    {
        // Configuration: Define where Laravel-style migrations are stored
        $migrationBasePath = APPPATH . 'Database/Eloquent-Migrations';

        // Generate table name from base model name
        $modelCodeGenerator = new ModelCodeGenerator();
        $tableName = $modelCodeGenerator->getTableName($baseModelName);

        // Generate migration class name and file name
        $migrationName = "create_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$migrationName}.php";
        $targetDir = rtrim($migrationBasePath, DIRECTORY_SEPARATOR);
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        $relativeTargetFile = str_replace(APPPATH, 'app/', $targetFile);

        // Ensure the directory exists
        $modelGenerator = new ModelGeneratorHandler();
        if (!$modelGenerator->ensureDirectoryExists($targetDir)) {
            return self::EXIT_ERROR;
        }

        // Generate migration code
        $code = $this->generateMigrationCode($tableName);

        // Write the migration file
        helper('filesystem');
        if (!write_file($targetFile, $code)) {
            CLI::error("Error creating migration file: {$relativeTargetFile}");
            return self::EXIT_ERROR;
        }

        CLI::write("Migration created successfully: " . CLI::color($relativeTargetFile, 'green'));
        return self::EXIT_SUCCESS;
    }

    /**
     * Generates migration code for creating a table.
     *
     * @param string $tableName The table name.
     * @return string The generated migration code.
     */
    public function generateMigrationCode(string $tableName): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
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
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
    }
}
