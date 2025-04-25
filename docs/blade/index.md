# Blade Templating for CodeIgniter 4

## Table of Contents
1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Initial Setup](#initial-setup)
4. [Configuration](#configuration)
   - [Blade Configuration File](#blade-configuration-file)
   - [Customizing Paths](#customizing-paths)
5. [Basic Usage](#basic-usage)
   - [The blade_view Helper](#the-blade_view-helper)
   - [Passing Data to Views](#passing-data-to-views)
6. [Blade Templating Features](#blade-templating-features)
   - [Layouts and Sections](#layouts-and-sections)
   - [Template Inheritance](#template-inheritance)
   - [Includes](#includes)
   - [Conditionals](#conditionals)
   - [Loops](#loops)
7. [Custom Directives](#custom-directives)
   - [Authentication Directives](#authentication-directives)
   - [Form Method Spoofing](#form-method-spoofing)
   - [Permission Checks](#permission-checks)
   - [Error Handling](#error-handling)
   - [Back URL Helper](#back-url-helper)
8. [Components](#components)
   - [Using @component](#using-component)
   - [Important Note About x-component](#important-note-about-x-component)
9. [Pagination Support](#pagination-support)
   - [CodeIgniter 4 Integration](#codeigniter-4-integration)
   - [Displaying Pagination Links](#displaying-pagination-links)
10. [Error Handling](#error-handling-1)
11. [View Compilation](#view-compilation)
    - [Manual Compilation](#manual-compilation)
    - [Development vs. Production](#development-vs-production)
12. [Advanced Usage](#advanced-usage)
    - [Accessing the Blade Instance](#accessing-the-blade-instance)
    - [Custom Extensions](#custom-extensions)
13. [Performance Optimization](#performance-optimization)
14. [Troubleshooting](#troubleshooting)
15. [Best Practices](#best-practices)

## Introduction

This documentation covers how to use the Laravel Blade templating engine within your CodeIgniter 4 application through the Ci4Larabridge package. Blade provides powerful templating features including layouts, components, directives, and more that can enhance your view layer.

Blade combines the familiarity of PHP with elegant template syntax that makes writing views more enjoyable and maintainable. The Ci4Larabridge implementation brings this powerful templating engine to CodeIgniter 4 with CI4-specific integrations.

## Installation

To install the Ci4Larabridge package with Blade support:

```bash
composer require rcalicdan/ci4-larabridge
```

## Initial Setup

Before using Blade in your CI4 application, you need to run the setup command:

```bash
php spark laravel:setup
```

This command:
- Creates necessary directories (if they don't exist)
- Publishes the Blade configuration file to your application
- Sets up cache directories with proper permissions
- Initializes the Blade service provider

## Configuration

### Blade Configuration File

After running `php spark laravel:setup`, a `Blade.php` configuration file will be published to your `app/Config` directory. The configuration file contains the following key settings:

```php
<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Blade extends BaseConfig
{
    /**
     * Path to the views directory
     */
    public string $viewsPath = APPPATH . 'Views';
    
    /**
     * Path to the compiled views cache directory
     */
    public string $cachePath = WRITEPATH . 'cache/blade';
    
    /**
     * Components namespace
     */
    public string $componentNamespace = 'Components';
    
    /**
     * Path to components directory
     */
    public string $componentPath = APPPATH . 'Views/components';
    
    /**
     * Whether to check for view updates in production
     * Set to false in production for better performance
     */
    public bool $checksCompilationInProduction = false;
}
```

### Customizing Paths

You can customize these settings in your config file:

- `$viewsPath`: Where your Blade templates are located
- `$cachePath`: Where compiled templates are stored
- `$componentNamespace`: Namespace used for component resolution
- `$componentPath`: Directory containing your components
- `$checksCompilationInProduction`: Performance setting for production environments

## Basic Usage

### The blade_view Helper

Once installed, you can render Blade templates using the `blade_view()` helper function:

```php
// In your controller
public function index()
{
    $data = [
        'title' => 'Dashboard',
        'users' => $userModel->findAll()
    ];
    
    return blade_view('pages.dashboard', $data);
}
```

### Passing Data to Views

Data is passed as an associative array and becomes available as variables in your template:

```php
// Controller
$data = [
    'users' => $userModel->findAll(),
    'title' => 'User List',
    'isAdmin' => $this->auth->isAdmin()
];

return blade_view('admin.users.index', $data);

// In your blade template (admin/users/index.blade.php)
<h1>{{ $title }}</h1>

@if($isAdmin)
    <p>Welcome, Administrator!</p>
@endif

<ul>
    @foreach($users as $user)
        <li>{{ $user->name }}</li>
    @endforeach
</ul>
```

## Blade Templating Features

### Layouts and Sections

Create reusable layouts with content sections:

```php
<!-- layouts/main.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Default Title')</title>
    <meta name="description" content="@yield('meta_description', 'Default description')">
    
    <link rel="stylesheet" href="/css/app.css">
    @yield('styles')
</head>
<body>
    <header>
        @include('partials.navbar')
    </header>
    
    <div class="container">
        @yield('content')
    </div>
    
    <footer>
        @include('partials.footer')
    </footer>
    
    <script src="/js/app.js"></script>
    @yield('scripts')
</body>
</html>
```

### Template Inheritance

Extend layouts in child templates:

```php
<!-- pages/about.blade.php -->
@extends('layouts.main')

@section('title', 'About Us')
@section('meta_description', 'Learn about our company')

@section('content')
    <h1>About Our Company</h1>
    <p>Lorem ipsum dolor sit amet...</p>
@endsection

@section('scripts')
    <script src="/js/about.js"></script>
@endsection
```

### Includes

Include partial templates:

```php
<!-- Including a partial -->
@include('partials.alert', ['type' => 'danger', 'message' => 'Error occurred!'])

<!-- Including when a condition is met -->
@includeWhen($user->isAdmin, 'admin.dashboard.stats')

<!-- Including with a fallback if the partial doesn't exist -->
@includeFirst(['custom.header', 'default.header'])
```

### Conditionals

```php
@if($user->isAdmin)
    <p>Admin actions:</p>
    <ul>
        <li><a href="/admin/users">Manage Users</a></li>
    </ul>
@elseif($user->isEditor)
    <p>Editor actions:</p>
    <ul>
        <li><a href="/editor/posts">Manage Posts</a></li>
    </ul>
@else
    <p>User actions:</p>
    <ul>
        <li><a href="/profile">Edit Profile</a></li>
    </ul>
@endif

<!-- Unless directive -->
@unless($user->hasVerifiedEmail())
    <div class="alert">Please verify your email address</div>
@endunless

<!-- Isset and empty checks -->
@isset($message)
    <div class="alert">{{ $message }}</div>
@endisset

@empty($users)
    <p>No users found</p>
@endempty
```

### Loops

```php
<!-- For loop -->
@for($i = 0; $i < 10; $i++)
    <p>Item {{ $i }}</p>
@endfor

<!-- Foreach loop -->
@foreach($users as $user)
    <p>{{ $user->name }}</p>
@endforeach

<!-- While loop -->
@while($condition)
    <p>Loop content</p>
@endwhile

<!-- Loop variables -->
@foreach($items as $item)
    {{ $loop->index }} - {{ $item->name }}
    @if($loop->first)
        (This is the first item)
    @endif
    
    @if($loop->last)
        (This is the last item)
    @endif
@endforeach
```

## Custom Directives

### Authentication Directives

```php
@auth
    <!-- For authenticated users -->
    <p>Welcome back, {{ auth()->user()->name }}!</p>
@endauth

@guest
    <!-- For guests -->
    <p><a href="/login">Login</a> or <a href="/register">Register</a></p>
@endguest
```

### Form Method Spoofing

Since HTML forms only support GET and POST methods, use method spoofing for PUT, PATCH, and DELETE requests:

```php
<form method="POST" action="/users/{{ $user->id }}">
    @method('PUT')
    <!-- Form fields -->
    <button type="submit">Update</button>
</form>

<!-- Alternative shortcuts -->
<form method="POST" action="/users/{{ $user->id }}">
    @put
    <!-- Form fields -->
</form>

<form method="POST" action="/users/{{ $user->id }}">
    @patch
    <!-- Form fields -->
</form>

<form method="POST" action="/users/{{ $user->id }}">
    @delete
    <button type="submit">Delete</button>
</form>
```

### Permission Checks

Check user permissions:

```php
@can('edit-post', $post)
    <a href="/posts/{{ $post->id }}/edit">Edit</a>
@endcan

@cannot('delete-post', $post)
    <p>You don't have permission to delete this post.</p>
@endcannot
```

### Error Handling

Handle validation errors with dedicated directives:

```php
<input type="email" name="email" value="{{ old('email') }}" 
    class="form-control @error('email') is-invalid @enderror">

@error('email')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror

<!-- Check for any errors -->
@if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
```

### Back URL Helper

Maintain navigation history with the back helper:

```php
<form method="POST" action="/process">
    @back('/default/path')
    <!-- This will insert a hidden field with the previous URL -->
    <!-- Form fields -->
</form>
```

## Components

### Using @component

Components allow you to create reusable view snippets with their own data and logic:

```php
<!-- Using a component -->
@component('components.alert', ['type' => 'danger'])
    <strong>Error!</strong> Something went wrong.
@endcomponent

<!-- Components with slots -->
@component('components.card')
    @slot('title')
        Featured Post
    @endslot
    
    <p>This is the card content.</p>
    
    @slot('footer')
        <a href="#">Read more</a>
    @endslot
@endcomponent
```

Component file example (`components/alert.blade.php`):

```php
<div class="alert alert-{{ $type ?? 'info' }}">
    {{ $slot }}
</div>
```

### Important Note About x-component

> **Important Note:** The Laravel-style `<x-component>` syntax is NOT supported in this implementation. 
> 
> While Laravel's newer versions support the elegant angle-bracket syntax for components (`<x-alert type="danger"></x-alert>`), this feature requires Laravel's complete view system and is not compatible with this CI4 implementation.
>
> Always use the `@component` directive approach shown above instead.

## Pagination Support

### CodeIgniter 4 Integration

The package provides integration between CodeIgniter's pagination and the Blade templates. However, there's an important difference from Laravel:

> **Important Note:** The standard Laravel pagination links via `{{ $items->links() }}` will NOT work correctly. This is because Laravel's pagination system uses Laravel's URL generation methods, which are not compatible with CodeIgniter 4.
>
> Instead, use the provided `linksHtml` property that's automatically injected into pagination objects:

```php
{!! $users->linksHtml !!}
```

Note the use of `{!! !!}` which outputs the HTML without escaping, as the pagination links contain HTML.

### Displaying Pagination Links

Example controller method:

```php
public function index()
{
    $userModel = new \App\Models\UserModel();
    $users = $userModel->paginate(15);
    
    return blade_view('users.index', [
        'users' => $users,
        // Optional: override the pagination theme
        'paginationTheme' => 'bootstrap4'
    ]);
}
```

Example Blade template:

```php
<table class="table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $user)
            <tr>
                <td>{{ $user->id }}</td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<!-- Display pagination links -->
{!! $users->linksHtml !!}
```

## Error Handling

The Blade service includes robust error handling:

- In development: Detailed error messages are shown to help debug issues
- In production: Errors are logged and a safe fallback is shown to users

Example of how errors are handled:

```php
try {
    return blade_view('users.show', ['user' => $user]);
} catch (\Throwable $e) {
    log_message('error', "Blade rendering error: {$e->getMessage()}");
    
    if (ENVIRONMENT !== 'production') {
        throw $e;  // Re-throw in development for debugging
    }
    
    return "<!-- View Rendering Error -->";
}
```

## View Compilation

### Manual Compilation

You can pre-compile all Blade views using the included command:

```bash
php spark blade:compile
```

This is useful for:
- Pre-warming the cache in production deployments
- Verifying that all templates compile without errors
- Improving initial page load performance

### Development vs. Production

In development:
- Views are automatically re-compiled when changed
- Errors include detailed stack traces

In production:
- Set `checksCompilationInProduction` to `false` in your config
- Pre-compile views during deployment
- Errors are logged but not displayed

## Advanced Usage

### Accessing the Blade Instance

If you need direct access to the Blade instance:

```php
$blade = service('blade')->getBlade();

// Register a custom directive
$blade->directive('datetime', function ($expression) {
    return "<?php echo date('Y-m-d H:i:s', strtotime($expression)); ?>";
});
```

### Custom Extensions

To extend Blade with custom directives, you can create your own extension class that follows the pattern established in `BladeExtension.php`:

```php
<?php

namespace App\Libraries;

use Rcalicdan\Blade\Blade;

class MyBladeExtensions
{
    public function register(Blade $blade)
    {
        $blade->directive('uppercase', function ($expression) {
            return "<?php echo strtoupper($expression); ?>";
        });
        
        $blade->directive('formatDate', function ($expression) {
            return "<?php echo date('F j, Y', strtotime($expression)); ?>";
        });
    }
}
```

Then register it in your application's service:

```php
// In a custom service provider or bootstrap file
$blade = service('blade')->getBlade();
$extensions = new \App\Libraries\MyBladeExtensions();
$extensions->register($blade);
```

## Performance Optimization

1. **Cache Compiled Views**:
   - Always set `checksCompilationInProduction` to `false` in production
   - Pre-compile views during deployment with `php spark blade:compile`

2. **Optimize Template Structure**:
   - Use includes and components to avoid code duplication
   - Keep templates focused and modular

3. **Minimize Data Passing**:
   - Only pass necessary data to views
   - Consider using view composers for commonly needed data

4. **Server Configuration**:
   - Ensure the cache directory is on a fast filesystem
   - Consider using an opcode cache like OPcache

## Troubleshooting

**Issue: Views not updating after changes**
- Ensure you're not in production mode or `checksCompilationInProduction` is `true`
- Clear the view cache: `rm -rf writable/cache/blade/*`

**Issue: Pagination links not showing**
- Remember to use `{!! $items->linksHtml !!}` instead of `{{ $items->links() }}`
- Ensure your model is using CodeIgniter's pagination methods

**Issue: Component not found**
- Check that your component is in the correct directory (default: `app/Views/components`)
- Verify that the component name in the directive matches the filename

**Issue: Blade compilation errors**
- Run `php spark blade:compile` to see detailed error messages
- Check that your Blade syntax is correct

## Best Practices

1. **Organization**:
   - Use dot notation to reflect directory structure
   - Group related views in subdirectories
   - Follow naming conventions consistently

2. **Security**:
   - Always use `{{ }}` (escaped output) unless you specifically need unescaped HTML
   - Validate and sanitize data before passing to views

3. **Maintenance**:
   - Keep templates DRY with proper inheritance and components
   - Comment complex sections of templates
   - Use a consistent style and formatting

4. **Integration**:
   - Leverage both CI4 and Blade features appropriately
   - Consider creating helpers for common view patterns

By following this documentation, you should be able to efficiently use Blade templating in your CodeIgniter 4 application with the Ci4Larabridge package.