# CI4-Larabridge: Laravel Eloquent ORM Integration for CodeIgniter 4

## Overview

CI4-Larabridge is a package that seamlessly integrates Laravel's Eloquent ORM into CodeIgniter 4 applications. This integration provides CodeIgniter developers with access to Laravel's powerful database tools while maintaining CodeIgniter's lightweight framework structure.

This documentation covers setup, configuration, and usage of the Laravel-style migrations and model generation within your CodeIgniter 4 application.

## Important Notices

### ORM Compatibility

**Do not use CodeIgniter's native database tools alongside Eloquent ORM for the same tables.** This will likely cause conflicts, particularly with migrations, as both systems maintain separate migration tracking tables and handle schema changes differently.

### Database Driver Compatibility

Eloquent ORM only supports specific database drivers:
- MySQL/MariaDB (`mysql`)
- PostgreSQL (`pgsql`)
- SQLite (`sqlite`)
- SQL Server (`sqlsrv`)

If your application uses a database driver not supported by Eloquent (such as ODBC, Oracle, etc.), you will need to use the CodeIgniter ORM for those connections.

## Installation

```bash
composer require rcalicdan/ci4-larabridge
```

## Configuration

### Default Configuration

By default, the package will use your existing CodeIgniter database configuration from your `.env` file or database config. The Eloquent class automatically maps CodeIgniter's database configuration parameters to Eloquent's expected format.

### Custom Configuration

If you want to use separate database configurations for Eloquent and CodeIgniter, you can:

1. Create specific environment variables for Eloquent in your `.env` file:

```
# CodeIgniter database connection
database.default.hostname = localhost
database.default.database = ci4_app
database.default.username = ci4user
database.default.password = ci4pass

# Eloquent database connection (separate)
eloquent.hostname = localhost
eloquent.database = eloquent_db
eloquent.username = eloquentuser
eloquent.password = eloquentpass
eloquent.driver = mysql
```

2. Create a custom Eloquent configuration by extending the package's config in your application:

```php
<?php

namespace Config;

use Rcalicdan\Ci4Larabridge\Config\Eloquent as BaseEloquent;

class Eloquent extends BaseEloquent
{
    public function getDatabaseInformation(): array
    {
        return [
            'host'      => env('eloquent.hostname', 'localhost'),
            'driver'    => env('eloquent.driver', 'mysql'),
            'database'  => env('eloquent.database', ''),
            'username'  => env('eloquent.username', 'root'),
            'password'  => env('eloquent.password', ''),
            'charset'   => env('eloquent.charset', 'utf8'),
            'collation' => env('eloquent.collation', 'utf8_general_ci'),
            'prefix'    => env('eloquent.prefix', ''),
            'port'      => env('eloquent.port', '3306'),
        ];
    }
}
```

Save this file at `app/Config/Eloquent.php` and the system will use it instead of the package's default configuration.

## Available Commands

### Migrations

#### Running Migrations

```bash
php spark eloquent:migrate [action]
```

Available actions:
- `up` (default): Run pending migrations
- `down`: Roll back the last batch of migrations
- `refresh`: Roll back all migrations and run them again
- `status`: Show migration status
- `fresh`: Drop all tables and re-run all migrations

Example:
```bash
php spark eloquent:migrate up
```

#### Creating Migrations

```bash
php spark make:eloquent-migration [name] [--table=tablename] [--force]
```

Options:
- `name`: Migration name (e.g., CreateUsersTable, AddEmailToUsersTable)
- `--table`: Specify table name for modification migrations
- `--force`: Override existing migration file with the same name

Examples:
```bash
# Create a table migration
php spark make:eloquent-migration CreateProductsTable

# Create a table modification migration
php spark make:eloquent-migration AddPriceToProducts --table=products
```

### Models

#### Creating Eloquent Models

```bash
php spark make:eloquent-model [name] [-m] [--force]
```

Options:
- `name`: Model name, can include subdirectories (e.g., User, Admin/User)
- `-m`: Create a migration file for the model
- `--force`: Override existing model file

Examples:
```bash
# Create a model
php spark make:eloquent-model User

# Create a model with migration
php spark make:eloquent-model Product -m

# Create a model in a subdirectory
php spark make:eloquent-model Admin/Role
```

## Directory Structure

Laravel migrations and models are organized in the following directory structure:

```
app/
├── Config/
│   └── Eloquent.php (optional custom configuration)
├── Database/
│   └── Eloquent-Migrations/
│       ├── 2023_10_28_123456_create_users_table.php
│       └── ...
└── Models/
    ├── User.php
    ├── Product.php
    └── Admin/
        └── Role.php
```

## Using Eloquent Models

Once models are created, you can use them directly in your controllers:

```php
<?php

namespace App\Controllers;

use App\Models\User;

class UserController extends BaseController
{
    public function index()
    {
        $users = User::all();
        return view('users/index', ['users' => $users]);
    }
    
    public function show($id)
    {
        $user = User::find($id);
        return view('users/show', ['user' => $user]);
    }
    
    public function create()
    {
        $user = new User();
        $user->name = $this->request->getPost('name');
        $user->email = $this->request->getPost('email');
        $user->password = password_hash($this->request->getPost('password'), PASSWORD_DEFAULT);
        $user->save();
        
        return redirect()->to('/users');
    }
}
```

## Writing Migrations

Migrations follow Laravel's migration syntax:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

## DatabaseHandler Features

The DatabaseHandler provides utilities for database operations:

- Auto-detection of database existence
- Database creation if not present
- Support for multiple database drivers:
  - MySQL/MariaDB
  - PostgreSQL
  - SQLite
  - SQL Server

## Best Practices

1. **Single ORM Rule**: Choose either Eloquent OR CodeIgniter's ORM for your project, not both.

2. **Database Driver Compatibility**: Ensure you're using one of Eloquent's supported database drivers:
   - MySQL/MariaDB (`mysql`)
   - PostgreSQL (`pgsql`)
   - SQLite (`sqlite`)
   - SQL Server (`sqlsrv`)

3. **Migration Separation**: Keep Laravel migrations in the designated directory.

4. **Follow Laravel Conventions**: Use Laravel naming conventions for migrations and models:
   - Migrations: `create_table_name_table`, `add_column_to_table_name`
   - Models: Singular, PascalCase (e.g., `User`, `ProductCategory`)

5. **Custom Configuration**: For complex applications that require both ORMs, create separate database configurations with different database names.

## Troubleshooting

### Common Issues

1. **Migration Conflicts**: If you accidentally ran both CodeIgniter and Laravel migrations, you may need to drop all tables and start fresh.

   ```bash
   php spark eloquent:migrate fresh
   ```

2. **Database Connection Issues**: Ensure your database credentials are correctly set in your `.env` file or database config.

3. **Model Namespace Errors**: If models aren't found, check namespaces match the directory structure.

4. **Unsupported Database Driver**: If you encounter errors related to the database driver, verify you're using one of the supported drivers listed above.

5. **Connection Errors**: If Eloquent fails to connect while CodeIgniter connects successfully, you may need to explicitly map database parameters in a custom Eloquent configuration as described in the Configuration section.

## Conclusion

CI4-Larabridge provides a powerful way to leverage Laravel's Eloquent ORM within your CodeIgniter 4 application. By following the outlined practices and being careful not to mix the two ORM systems, you can enjoy the best of both frameworks.

For more information on Eloquent features, refer to the [Laravel documentation](https://laravel.com/docs/8.x/eloquent).