# CI4-LaraBridge Documentation

## Introduction

CI4-LaraBridge is a bridge package that brings Laravel-like functionality to CodeIgniter 4 applications. This package aims to provide a familiar Laravel development experience while maintaining the speed and simplicity of the CodeIgniter 4 framework.

## Installation

To install the package, run the following command in your CodeIgniter 4 project:

```bash
composer require rcalicdan/ci4-larabridge
```

After installation, you need to run the setup command to initialize the package:

```bash
php spark laravel:setup
```

This command will:
- Set up and publish configuration files
- Configure package events
- Set up autodiscovery features

Always run this setup command before using any CI4-LaraBridge features in your application.

## Why CI4-LaraBridge?

This package was created to address the needs of developers who:

1. Are familiar with Laravel but work in a CodeIgniter 4 environment
2. Want to leverage some of Laravel's elegant syntax and powerful features without switching frameworks
3. Need to maintain existing CodeIgniter projects but prefer Laravel's development approach

## Who Should Use It?

CI4-LaraBridge is ideal for:

- Laravel developers transitioning to CodeIgniter 4 projects
- CodeIgniter 4 developers looking to adopt Laravel-style syntax and patterns
- Teams working with mixed Laravel/CodeIgniter codebases
- Developers who appreciate Laravel's expressiveness but prefer CodeIgniter's performance

## Features

CI4-LaraBridge brings several key Laravel features to CodeIgniter 4:

- **Eloquent ORM**: Use Laravel's powerful ORM for database operations
- **Blade Templating**: Implement Blade templates for clean, reusable views
- **Laravel Validation**: Leverage Laravel's robust validation system
- **Laravel Style Form Requests**: Encapsulate validation logic in dedicated request classes
- **Gates and Policies**: Implement Laravel-style authorization
- **Simple Authentication**: Easily implement user authentication
- **Auto Redirect Error Validation**: Automatic redirection with validation errors
- **Clean Syntax**: Write more expressive, readable code
- **Eloquent Migration and Models**: Define and manage database schema with Eloquent
- **Pagination Support**: Easily paginate results with Laravel-style methods
- **Laravel HTTP**: Easily calls api with it's expressive and elegant systax

## Benefits

- **Familiar Syntax**: Use Laravel-style facades, helpers, and patterns in CodeIgniter 4
- **Gradual Adoption**: Implement Laravel features incrementally without overhauling your entire application
- **Performance**: Maintain CodeIgniter's speed while enhancing developer experience
- **Best of Both Worlds**: Leverage specific Laravel strengths without abandoning CodeIgniter benefits
- **Smoother Learning Curve**: Reduce the learning curve for Laravel developers working on CI4 projects

## Custom Commands
CI4-LaraBridge provides several custom commands to enhance your development experience: [Check Here](docs/commands/index.md)

## Syntax Comparison

### Creating a New User

#### With CI4-LaraBridge:

```php
public function store()
{
    User::create(StoreUserRequest::validateRequest());
    return redirect()->route('users.index')->with('success', 'User created successfully');
}
```

#### Native CodeIgniter 4:

```php
public function store()
{
    $validation = \Config\Services::validation();
    $validation->setRules([
        'name' => 'required|min_length[3]',
        'email' => 'required|valid_email|is_unique[users.email]',
        'password' => 'required|min_length[8]'
    ]);
    
    if (!$validation->withRequest($this->request)->run()) {
        return redirect()->back()->withInput()->with('errors', $validation->getErrors());
    }
    
    $userModel = new \App\Models\UserModel();
    $userModel->insert([
        'name' => $this->request->getPost('name'),
        'email' => $this->request->getPost('email'),
        'password' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT)
    ]);
    
    return redirect()->to('/users')->with('message', 'User created successfully');
}
```

### Retrieving Users with Pagination

#### With CI4-LaraBridge:

```php
public function index()
{
    $users = User::paginate(15);
    return view('users.index', compact('users'));
}
```

#### Native CodeIgniter 4:

```php
public function index()
{
    $userModel = new \App\Models\UserModel();
    $data['users'] = $userModel->paginate(15);
    $data['pager'] = $userModel->pager;
    
    return view('users/index', $data);
}
```

## Plan Features
1. Eloquent Seeder
2. Eloquent Factory

## Limitations

It's important to note that CI4-LaraBridge does not implement all advanced Laravel features. The package focuses on the most commonly used Laravel functionality while maintaining compatibility with CodeIgniter 4's architecture. Some advanced Laravel features like:

- Complex queue systems
- Laravel's event broadcasting
- Some aspects of the advanced authorization system
- Certain middleware implementations

are not fully implemented or may work differently than in Laravel.

## Getting Started

After installation and setup, you can begin using Laravel-style syntax in your CodeIgniter 4 application. Check the GitHub repository at https://github.com/rcalicdan/ci4-larabridge for detailed usage examples, available features, and configuration options.

>This package is in experimental stage and many more features may change or added
