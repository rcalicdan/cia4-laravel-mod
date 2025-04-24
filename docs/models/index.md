# Laravel Eloquent Models in CodeIgniter 4

## Overview

The `make:eloquent-model` command allows you to create Laravel-style Eloquent models within your CodeIgniter 4 application. This integration bridges the gap between CodeIgniter's native ORM system and Laravel's Eloquent ORM, providing an alternative approach to database interactions.

> **Important Note**: It is not recommended to use Eloquent and CodeIgniter's native ORM/models simultaneously within the same application as this can create conflicts, particularly in migrations and database schema management. Choose one approach for your project to maintain consistency and prevent unexpected behavior.

# Table of Contents

1. [Overview](#overview)
   - [Installation](#installation)

2. [Command Usage](#command-usage)
   - [Basic Syntax](#basic-syntax)
   - [Arguments](#arguments)
   - [Options](#options)

3. [Examples](#examples)
   - [Basic Usage](#basic-usage)
   - [With Subdirectories](#with-subdirectories)
   - [With Migration](#with-migration)

4. [Generated Files](#generated-files)
   - [Model Structure](#model-structure)
   - [Migration Structure](#migration-structure)

5. [Best Practices](#best-practices)
   - [Consistent ORM Usage](#consistent-orm-usage)
   - [Migration Management](#migration-management)
   - [Naming Conventions](#naming-conventions)
   - [Model Customization](#model-customization)

6. [Advanced Model Features](#advanced-model-features)
   - [Enhanced Model Example](#enhanced-model-example)
   - [Creating Related Models](#creating-related-models)
   - [Modifying Migrations](#modifying-migrations)

7. [Using Eloquent Models](#using-eloquent-models)
   - [In Blade Templates](#in-blade-templates)
   - [Controller Implementation](#controller-implementation)

8. [Query Examples](#query-examples)
   - [Basic Queries](#basic-queries)
   - [Advanced Queries](#advanced-queries)

9. [Troubleshooting](#troubleshooting)

10. [Learn More about Eloquent](#learn-more-about-eloquent)

## Installation

Ensure the CI4Larabridge package is properly installed in your CodeIgniter 4 project before using this command.

## Command Usage

```bash
php spark make:eloquent-model [<name>] [options]
```

### Arguments

- `name`: The model class name in PascalCase format. You can include subdirectories using forward slashes.
  - Examples: `User`, `Common/User`, `Admin/Auth/Role`

### Options

- `-m`: Create a corresponding migration file for the model
- `--force`: Force overwrite if a model file already exists

## Examples

### Basic Usage

```bash
php spark make:eloquent-model User
```

This creates a basic User model at `app/Models/User.php`.

### With Subdirectories

```bash
php spark make:eloquent-model Admin/Role
```

This creates a Role model at `app/Models/Admin/Role.php` with the namespace `App\Models\Admin`.

### With Migration

```bash
php spark make:eloquent-model Product -m
```

This creates both:
- A Product model at `app/Models/Product.php`
- A migration file at `app/Database/Eloquent-Migrations/[timestamp]_create_products_table.php`

## Generated Files

### Model Structure

The generated model will have the following structure:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $fillable = [];
}
```

Key features:
- Table name is automatically derived from the model name (pluralized, snake_case)
- Empty `$fillable` array is provided for you to define mass-assignable attributes

### Migration Structure

If you use the `-m` option, the generated migration will include:

```php
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

## Best Practices

1. **Consistent ORM Usage**: Choose either Eloquent or CodeIgniter's native model system for your project. Mixing them can lead to conflicts.

2. **Migration Management**: When using Eloquent models, ensure you use Eloquent migrations exclusively. The migrations are stored in `app/Database/Eloquent-Migrations/`.

3. **Naming Conventions**: Follow Laravel naming conventions for Eloquent models:
   - Models should be singular and PascalCase (e.g., `User`, not `Users`)
   - Table names will be automatically pluralized (e.g., `User` model will use `users` table)

4. **Model Customization**: After generating the model, you should:
   - Define fillable attributes in the `$fillable` array
   - Add relationships, scopes, and accessors as needed
   - Customize timestamps if required

## Troubleshooting

1. **Invalid Model Name**: Ensure your model name is in PascalCase and follows the format described.

2. **Directory Issues**: If you receive errors about directories not existing, check your file permissions.

3. **Migration Conflicts**: If you're experiencing migration conflicts, ensure you're not mixing CodeIgniter migrations with Eloquent migrations.

# Working with Eloquent Models in CodeIgniter 4

## Advanced Model Features Demo

After generating your basic Eloquent model, you can enhance it with various features that make Eloquent powerful, including magic methods, relationships, accessors, mutators, and more.

### Model with Advanced Features

Here's an example of an enhanced `User` model with various Eloquent features:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends Model
{
    protected $table = 'users';
    
    protected $fillable = [
        'name', 'email', 'password', 'role', 'status'
    ];
    
    protected $hidden = [
        'password', 'remember_token'
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'settings' => 'array',
        'is_active' => 'boolean'
    ];
    
    // Accessors and Mutators
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucwords($value),
            set: fn (string $value) => strtolower($value)
        );
    }
    
    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => password_hash($value, PASSWORD_DEFAULT)
        );
    }
    
    // Relationships
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
    
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
    
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    public function scopeWithRole($query, $role)
    {
        return $query->where('role', $role);
    }
    
    // Custom Methods
    public function isAdmin()
    {
        return $this->role === 'admin';
    }
    
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
```

### Creating Related Models

Let's create related models to demonstrate relationships:

1. First, create a Post model:

```bash
php spark make:eloquent-model Post -m
```

2. Enhance the Post model with relationships:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $table = 'posts';
    
    protected $fillable = [
        'user_id', 'title', 'content', 'published_at'
    ];
    
    protected $casts = [
        'published_at' => 'datetime'
    ];
    
    // Define the relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
    
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
    
    // Scope for published posts
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }
}
```

### Modifying the Migrations

Enhance the generated migrations to include the necessary fields and foreign keys:

```php
// For users table
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->string('role')->default('user');
    $table->string('status')->default('active');
    $table->timestamp('email_verified_at')->nullable();
    $table->rememberToken();
    $table->json('settings')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

// For posts table
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('title');
    $table->text('content');
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

## Using Eloquent Models in Blade Templates

Within your Blade templates, you can use Eloquent models like this:

### User Profile Example

```blade
<div class="profile-card">
    <h2>{{ $user->name }}</h2>
    <p>{{ $user->email }}</p>
    
    @if($user->isAdmin())
        <span class="badge badge-admin">Administrator</span>
    @endif
    
    <h3>Recent Posts</h3>
    <ul class="post-list">
        @forelse($user->posts()->latest()->take(5)->get() as $post)
            <li>
                <a href="{{ route('posts.show', $post->id) }}">
                    {{ $post->title }}
                </a>
                <small>{{ $post->published_at->diffForHumans() }}</small>
            </li>
        @empty
            <li>No posts yet</li>
        @endforelse
    </ul>
</div>
```

### Posts Index Example

```blade
<div class="posts-container">
    @foreach($posts as $post)
        <article class="post-card">
            <h2>{{ $post->title }}</h2>
            <div class="post-meta">
                <span>By: {{ $post->user->name }}</span>
                <span>{{ $post->published_at->format('M d, Y') }}</span>
            </div>
            
            <div class="post-excerpt">
                {{ Str::limit($post->content, 200) }}
            </div>
            
            <div class="post-tags">
                @foreach($post->tags as $tag)
                    <span class="tag">{{ $tag->name }}</span>
                @endforeach
            </div>
            
            <div class="post-comments">
                <h4>Comments ({{ $post->comments->count() }})</h4>
                @foreach($post->comments()->latest()->take(3)->get() as $comment)
                    <div class="comment">
                        <strong>{{ $comment->author_name }}</strong>
                        <p>{{ $comment->content }}</p>
                    </div>
                @endforeach
            </div>
        </article>
    @endforeach
    
    {{ $posts->links() }}  <!-- Pagination -->
</div>
```

## Controller Implementation

Here's an example of how to use these models in a controller:

```php
<?php

namespace App\Controllers;

use App\Models\Post;
use App\Models\User;
use CodeIgniter\Controller;

class BlogController extends Controller
{
    public function index()
    {
        $posts = Post::with(['user', 'tags'])
                    ->published()
                    ->latest()
                    ->paginate(10);
                    
        return view('blog/index', compact('posts'));
    }
    
    public function show($id)
    {
        $post = Post::with(['user', 'comments.user', 'tags'])
                  ->findOrFail($id);
                  
        return view('blog/show', compact('post'));
    }
    
    public function userPosts($userId)
    {
        $user = User::findOrFail($userId);
        $posts = $user->posts()
                    ->published()
                    ->latest()
                    ->paginate(10);
                    
        return view('blog/user_posts', compact('user', 'posts'));
    }
}
```

## Query Examples

### Basic Queries

```php
// Find all active users
$activeUsers = User::active()->get();

// Find an admin user
$admin = User::withRole('admin')->first();

// Create a new user
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'securepassword'
]);

// Find a user with their relationships
$user = User::with(['posts', 'profile', 'roles'])->find(1);

// Update a user
$user->update(['status' => 'inactive']);

// Delete a user
$user->delete();
```

### Advanced Queries

```php
// Users with published posts
$users = User::whereHas('posts', function($query) {
    $query->published();
})->get();

// Posts with specific tag
$posts = Post::whereHas('tags', function($query) {
    $query->where('name', 'Laravel');
})->get();

// Latest posts with user and comment count
$posts = Post::with('user')
          ->withCount('comments')
          ->published()
          ->latest()
          ->take(5)
          ->get();
```

## Learn More about Eloquent

The examples provided above only scratch the surface of what's possible with Eloquent ORM. For comprehensive documentation and advanced features, please refer to the official Laravel documentation on Eloquent models:

[Laravel Eloquent Documentation](https://laravel.com/docs/eloquent)

Key topics to explore:
- Model relationships
- Query building
- Mutators and accessors
- Serialization
- Events and observers
- Scopes and global scopes
- Soft deleting
- Model factories and seeders

Remember, while this integration allows you to use Eloquent within CodeIgniter, the full Laravel ecosystem is not available. Some advanced features may require additional configuration or may not be fully compatible with the CodeIgniter framework.