# Authorization System Documentation

## Introduction

This document explains how to implement role-based access control in your CodeIgniter 4 application using the Ci4Larabridge authorization system. The system provides a Gate facade for defining permissions and Policies for organizing permission logic around specific models.

## Table of Contents

1. [Basic Concepts](#basic-concepts)
2. [Setting Up Authorization](#setting-up-authorization)
3. [Defining Abilities](#defining-abilities)
4. [Creating Policies](#creating-policies)
5. [Using Authorization in Controllers](#using-authorization-in-controllers)
6. [Authorization in Blade Templates](#authorization-in-blade-templates)
7. [Best Practices](#best-practices)

## Basic Concepts

The authorization system consists of two main components:

- **Gate**: A centralized permission manager where you can define and check abilities
- **Policies**: Classes that organize permission logic related to specific models

## Setting Up Authorization

### 1. Install Ci4Larabridge

First, make sure you have installed the Ci4Larabridge package and run the setup command:

```bash
php spark laravel:setup
```

This command sets up the necessary components including authentication and authorization systems.

### 2. Configure the AuthServiceProvider

Create or modify your `AuthServiceProvider.php` in your application directory:

```php
<?php

namespace App\Providers;

use App\Models\Post;
use App\Policies\PostPolicy;
use Rcalicdan\Ci4Larabridge\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application
     */
    protected $policies = [
        Post::class => PostPolicy::class,
        // Add more model-to-policy mappings here
    ];

    /**
     * Register authorization services
     */
    public function register(): void
    {
        // Register simple abilities
        gate()->define('access-admin', function($user) {
            return $user->isAdmin();
        });

        // Don't forget to call parent method to register policies
        $this->registerPolicies();
    }
}
```

## Defining Abilities

Define permissions in your `AuthServiceProvider` using the `gate()` helper:

```php
// Simple ability with a single parameter (the user)
gate()->define('create-post', function($user) {
    return $user->hasRole('editor') || $user->hasRole('admin');
});

// Ability with additional parameters
gate()->define('update-post', function($user, $post) {
    return $user->id === $post->user_id || $user->hasRole('admin');
});
```

## Creating Policies

Policies organize permission logic for specific models:

```php
<?php
// app/Policies/PostPolicy.php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    /**
     * Optional "before" method runs before other policy methods
     * Return true/false to override other checks, or null to continue
     */
    public function before($user, $ability)
    {
        if ($user->isAdmin()) {
            return true; // Admins can do everything
        }

        return null; // Continue with specific policy check
    }

    /**
     * Determine if user can view posts
     */
    public function view(User $user, Post $post)
    {
        return $post->status === 'published' || $user->id === $post->user_id;
    }

    /**
     * Determine if user can create posts
     */
    public function create(User $user)
    {
        return $user->hasRole('editor') || $user->hasRole('author');
    }

    /**
     * Determine if user can update posts
     */
    public function update(User $user, Post $post)
    {
        return $user->id === $post->user_id;
    }

    /**
     * Determine if user can delete posts
     */
    public function delete(User $user, Post $post)
    {
        return $user->id === $post->user_id;
    }
}
```

## Using Authorization in Controllers

### Accessing the Current User

You can use either the `auth()` helper or the `Auth` facade to access the current authenticated user:

```php
// Using the auth() helper
$user = auth()->user();

// Or using the Auth facade
use Rcalicdan\Ci4Larabridge\Facades\Auth;
$user = Auth::user();
```

### Basic Authorization

```php
<?php

namespace App\Controllers;

use App\Models\Post;
use CodeIgniter\Controller;

class PostController extends Controller
{
    public function index()
    {
        // Check a simple ability
        if (can('access-admin')) {
            // User can access admin area
        }

        return view('posts/index');
    }

    public function show($id)
    {
        $post = new Post();
        $post = $post->find($id);

        // Check if user can view this post
        if (cannot('view', $post)) {
            return redirect()->back()->with('error', 'Unauthorized');
        }

        return view('posts/show', ['post' => $post]);
    }

    public function edit($id)
    {
        $post = new Post();
        $post = $post->find($id);

        // Use authorize() helper to automatically throw a PageNotFoundException
        // if the user doesn't have permission
        authorize('update', $post);

        return view('posts/edit', ['post' => $post]);
    }

    public function create()
    {
        // Check if the user can create posts
        authorize('create-post');

        return view('posts/create');
    }

    public function delete($id)
    {
        $post = new Post();
        $post = $post->find($id);

        // Authorize before deleting
        authorize('delete', $post);

        $post->delete();
        return redirect()->to('/posts')->with('success', 'Post deleted successfully');
    }
}
```

### Using Policy Methods Directly

For fine-grained control:

```php
public function update($id)
{
    $post = new Post();
    $post = $post->find($id);

    $user = auth()->user();
    $policy = gate()->getPolicyFor($post);

    if ($policy && $policy->update($user, $post)) {
        // Process the update
        $post->fill($this->request->getPost());
        $post->save();
        return redirect()->to("/posts/{$id}")->with('success', 'Post updated successfully');
    }

    return redirect()->back()->with('error', 'Unauthorized');
}
```

### Authentication State Checks

You can check authentication status using various methods:

```php
// Check if user is logged in
if (auth()->check()) {
    // User is authenticated
}

// Check if user is a guest
if (auth()->guest()) {
    // User is not authenticated
    return redirect()->to('/login');
}

// Using Auth facade
use Rcalicdan\Ci4Larabridge\Facades\Auth;

if (Auth::check()) {
    // User is authenticated
}

if (Auth::guest()) {
    // User is not authenticated
}
```

## Authorization in Blade Templates

### Using @can and @cannot Directives

Show or hide UI elements based on permissions:

```blade
<!-- Check simple ability -->
@can('create-post')
    <a href="/posts/create" class="btn btn-primary">Create New Post</a>
@endcan

<!-- Check policy-based permission -->
@can('update', $post)
    <a href="/posts/{{ $post->id }}/edit" class="btn btn-secondary">Edit</a>
@endcan

@cannot('delete', $post)
    <span class="text-muted">You cannot delete this post</span>
@endcannot
```

### Authentication State

Check if a user is logged in:

```blade
@auth
    Welcome, {{ auth()->user()->name }}!
    <form action="/logout" method="POST">
        @method('POST')
        <button type="submit">Logout</button>
    </form>
@endauth

@guest
    <a href="/login">Login</a> or <a href="/register">Register</a>
@endguest
```

### Complex Authorization Examples

For more complex conditions:

```blade
<!-- Multiple conditions with policy -->
@can('update', $post)
    @if($post->status !== 'published')
        <a href="/posts/{{ $post->id }}/publish" class="btn btn-success">Publish</a>
    @else
        <a href="/posts/{{ $post->id }}/unpublish" class="btn btn-warning">Unpublish</a>
    @endif
@endcan

<!-- Combined checks -->
@auth
    @can('create-post')
        <div class="card mb-3">
            <div class="card-header">Quick Post</div>
            <div class="card-body">
                <form action="/posts/quick" method="POST">
                    <!-- Form fields -->
                    <button type="submit" class="btn btn-primary">Post</button>
                </form>
            </div>
        </div>
    @endcan
@endauth
```

## Best Practices

1. **Use Policy Classes** for model-related permissions to keep authorization logic organized
2. **Leverage the `before` Method** in policies for role-based permissions that apply across actions
3. **Use `authorize()` Helper** in controllers to automatically handle unauthorized access
4. **Keep Blade Templates Clean** by using the `@can` and `@cannot` directives
5. **Define Simple Gates** in your AuthServiceProvider for application-wide permissions
6. **Use Descriptive Names** for permissions that clearly indicate their purpose
7. **Centralize Role Logic** in your User model or a dedicated Role/Permission system
8. **Prefer Policies** over direct Gate checks for model-related operations

## Extending the User Model

To enhance your User model with role-checking capabilities:

```php
<?php

namespace App\Models;

use Rcalicdan\Ci4Larabridge\Models\User as BaseUser;

class User extends BaseUser
{
    /**
     * Check if user has a specific role
     */
    public function hasRole($role)
    {
        // Implementation depends on your role storage strategy
        // Example with a roles relationship:
        return $this->roles()->where('name', $role)->exists();

        // Or with a simple comma-separated role field:
        // return in_array($role, explode(',', $this->roles));
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    /**
     * Define roles relationship (if using a roles table)
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
```

## Complete Example Implementation

### Example Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['title', 'content', 'status', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPublished()
    {
        return $this->status === 'published';
    }
}
```

### Example Policy

```php
<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function before($user, $ability)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, Post $post)
    {
        return $post->isPublished() || $user->id === $post->user_id;
    }

    public function create(User $user)
    {
        return $user->hasRole('editor') || $user->hasRole('author');
    }

    public function update(User $user, Post $post)
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post)
    {
        return $user->id === $post->user_id;
    }

    public function publish(User $user, Post $post)
    {
        return $user->hasRole('editor') ||
               ($user->hasRole('author') && $user->id === $post->user_id);
    }
}
```

### Example Controller

```php
<?php

namespace App\Controllers;

use App\Models\Post;
use CodeIgniter\Controller;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::where('status', 'published')->get();
        return view('posts/index', ['posts' => $posts]);
    }

    public function show($id)
    {
        $post = Post::find($id);

        if (!$post) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException("Post not found");
        }

        authorize('view', $post);

        return view('posts/show', ['post' => $post]);
    }

    public function create()
    {
        authorize('create-post');
        return view('posts/create');
    }

    public function store()
    {
        authorize('create-post');

        $validationRules = [
            'title' => 'required|min_length[5]',
            'content' => 'required'
        ];

        if (!$this->validate($validationRules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $post = new Post();
        $post->title = $this->request->getPost('title');
        $post->content = $this->request->getPost('content');
        $post->status = 'draft';
        $post->user_id = auth()->user()->id;
        $post->save();

        return redirect()->to('/posts')->with('success', 'Post created successfully');
    }

    public function edit($id)
    {
        $post = Post::find($id);

        if (!$post) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException("Post not found");
        }

        authorize('update', $post);

        return view('posts/edit', ['post' => $post]);
    }

    public function update($id)
    {
        $post = Post::find($id);

        if (!$post) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException("Post not found");
        }

        authorize('update', $post);

        $validationRules = [
            'title' => 'required|min_length[5]',
            'content' => 'required'
        ];

        if (!$this->validate($validationRules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $post->title = $this->request->getPost('title');
        $post->content = $this->request->getPost('content');
        $post->save();

        return redirect()->to("/posts/{$id}")->with('success', 'Post updated successfully');
    }

    public function publish($id)
    {
        $post = Post::find($id);

        if (!$post) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException("Post not found");
        }

        authorize('publish', $post);

        $post->status = 'published';
        $post->save();

        return redirect()->to("/posts/{$id}")->with('success', 'Post published successfully');
    }

    public function delete($id)
    {
        $post = Post::find($id);

        if (!$post) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException("Post not found");
        }

        authorize('delete', $post);

        $post->delete();

        return redirect()->to('/posts')->with('success', 'Post deleted successfully');
    }
}
```

### Example Blade Template

```blade
<!-- posts/show.blade.php -->
@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>{{ $post->title }}</h1>

        <div class="mb-3">
            <span class="badge {{ $post->status === 'published' ? 'bg-success' : 'bg-secondary' }}">
                {{ ucfirst($post->status) }}
            </span>
            <span class="text-muted">By {{ $post->user->name }}</span>
        </div>

        <div class="content mb-4">
            {!! $post->content !!}
        </div>

        <div class="actions">
            @can('update', $post)
                <a href="/posts/{{ $post->id }}/edit" class="btn btn-primary">Edit</a>
            @endcan

            @can('publish', $post)
                @if($post->status !== 'published')
                    <form action="/posts/{{ $post->id }}/publish" method="POST" style="display: inline">
                        <button type="submit" class="btn btn-success">Publish</button>
                    </form>
                @endif
            @endcan

            @can('delete', $post)
                <form action="/posts/{{ $post->id }}/delete" method="POST" style="display: inline"
                      onsubmit="return confirm('Are you sure you want to delete this post?')">
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            @endcan
        </div>
    </div>
@endsection
```

By following this documentation, you'll be able to implement a robust authorization system using 
Laravel style authorization in your CodeIgniter 4 application using the Ci4Larabridge package.
