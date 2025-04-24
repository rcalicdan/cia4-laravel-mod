# CI4 Larabridge Command Line Tools Documentation

This documentation provides a comprehensive guide to using the command line tools available in the CI4 Larabridge package. These tools help integrate Laravel components with your CodeIgniter 4 application.

## Table of Contents

1. [Setup Commands](#setup-commands)
   - [Laravel Setup](#laravel-setup)
2. [Database Commands](#database-commands)
   - [Laravel Migrations](#laravel-migrations)
   - [Make Laravel Migration](#make-laravel-migration)
3. [Model Generation](#model-generation)
   - [Make Laravel Model](#make-laravel-model)
4. [Blade Template Commands](#blade-template-commands)
   - [Compile Blade Views](#compile-blade-views)
5. [Validation Commands](#validation-commands)
   - [Make Laravel Request](#make-laravel-request)
   - [Make Laravel Rule](#make-laravel-rule)
6. [Authorization Commands](#authorization-commands)
   - [Make Policy](#make-policy)

## Setup Commands

### Laravel Setup

Initializes your CodeIgniter 4 application with Laravel components.

**Usage:**
```bash
php spark laravel:setup
```

**Options:**
- `-f`: Force overwrite ALL existing files in destination.

**Example:**
```bash
php spark laravel:setup
```

This command will:
- Publish Laravel configuration files to your app
- Set up Laravel helpers
- Copy migration files
- Configure system events and filters
- Set up authentication-related models and services

## Database Commands

### Laravel Migrations

Manages Laravel-style migrations in your CodeIgniter 4 application.

**Usage:**
```bash
php spark eloquent:migrate [action]
```

**Actions:**
- `up` (default): Run pending migrations
- `down`: Rollback the last batch of migrations
- `refresh`: Rollback all migrations and run them again
- `status`: Show migration status
- `fresh`: Drop all tables and re-run all migrations

**Examples:**
```bash
# Run pending migrations
php spark eloquent:migrate

# Rollback the last batch of migrations
php spark eloquent:migrate down

# Show migration status
php spark eloquent:migrate status
```

### Make Laravel Migration

Creates a new Laravel-style migration file.

**Usage:**
```bash
php spark make:eloquent-migration [name] [--table=<table>] [--force]
```

**Arguments:**
- `name`: The migration name (e.g., CreateUsersTable, AddEmailToPostsTable)

**Options:**
- `--table=<table>`: Generate a migration for modifying an existing table
- `--force`: Force overwrite if a file with the same name exists

**Examples:**
```bash
# Create a migration to create a new table
php spark make:eloquent-migration CreateUsersTable

# Create a migration to modify an existing table
php spark make:eloquent-migration AddEmailToUsersTable --table=users
```

## Model Generation

### Make Laravel Model

Creates a new Laravel-style Eloquent model.

**Usage:**
```bash
php spark make:eloquent-model [name] [options]
```

**Arguments:**
- `name`: The model class name (e.g., User or Common/User)

**Options:**
- `-m`: Create a migration file for this model
- `--force`: Force overwrite existing model file

**Examples:**
```bash
# Create a basic model
php spark make:eloquent-model User

# Create a model with a migration
php spark make:eloquent-model Product -m

# Create a model in a subdirectory
php spark make:eloquent-model Admin/User
```

## Blade Template Commands

### Compile Blade Views

Pre-compiles all Blade templates for improved performance.

**Usage:**
```bash
php spark blade:compile
```

**Example:**
```bash
php spark blade:compile
```

This command will:
- Find all Blade templates in your app's Views directory
- Compile them to PHP for faster rendering
- Report compilation status

## Validation Commands

### Make Laravel Request

Creates a new Laravel-style Form Request class for form validation.

**Usage:**
```bash
php spark make:request [class_name]
```

**Arguments:**
- `class_name`: The name of the request class to create (automatically appends "Request" if not present)

**Examples:**
```bash
# Create a basic request class
php spark make:request User

# Create a request class in a subdirectory
php spark make:request Admin/User
```

### Make Laravel Rule

Creates a new Laravel validation rule class.

**Usage:**
```bash
php spark make:laravel-rule [name] [options]
```

**Arguments:**
- `name`: The name of the rule class (e.g., NoObsceneWord or Common/NoObsceneWord)

**Options:**
- `--force`: Force overwrite existing file

**Examples:**
```bash
# Create a basic validation rule
php spark make:laravel-rule NoObsceneWord

# Create a rule in a subdirectory
php spark make:laravel-rule Common/NoObsceneWord
```

## Authorization Commands

### Make Policy

Creates a new policy class for authorization rules.

**Usage:**
```bash
php spark make:policy [PolicyName] [options]
```

**Arguments:**
- `PolicyName`: The name of the policy class (use slashes for subdirectories)

**Options:**
- `--model=<model>`: Generate a policy for the specified model

**Examples:**
```bash
# Create a basic policy
php spark make:policy UserPolicy

# Create a model-specific policy
php spark make:policy Post --model=Post

# Create a policy in a subdirectory
php spark make:policy Admin/UserPolicy
```

## Best Practices

1. **Use Namespaces**: When creating models, requests, rules, or policies in subdirectories, use slashes in the name (e.g., `Admin/User`).

2. **Naming Conventions**:
   - Models: Singular and PascalCase (e.g., `User`, `Product`)
   - Migrations: Descriptive and start with create/add/update (e.g., `CreateUsersTable`)
   - Requests: Append "Request" to the name (e.g., `UserRequest`)
   - Rules: Descriptive of the validation (e.g., `NoObsceneWord`)
   - Policies: Append "Policy" to the name (e.g., `UserPolicy`)

3. **Generate Related Files Together**: When creating models, consider using the `-m` flag to create a corresponding migration file.

4. **Compile Blade Views**: For production environments, run `blade:compile` to pre-compile your views for better performance.

5. **Use Migration Actions**: Leverage `eloquent:migrate status` to check migration status before running or rolling back migrations.

By following this guide, you can effectively use CI4 Larabridge's command line tools to integrate Laravel components with your CodeIgniter 4 application.