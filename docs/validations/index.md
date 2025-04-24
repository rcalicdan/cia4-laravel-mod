# CodeIgniter 4 Laravel-style Validation Guide

This documentation covers how to use the Laravel-inspired validation components in your CodeIgniter 4 application with Blade templates. The `Ci4Larabridge` package brings Laravel's elegant form validation to CodeIgniter, with multiple approaches to suit your preferences.

## Table of Contents

1. [Installation](#installation)
2. [Form Request Validation](#form-request-validation)
3. [Direct Validation with RequestValidator](#direct-validation-with-requestvalidator)
4. [File Validation in CodeIgniter 4](#file-validation-in-codeigniter-4)
5. [Working with Validation Results](#working-with-validation-results)
6. [Custom Validation Rules](#custom-validation-rules)
7. [Displaying Errors in Blade Views](#displaying-errors-in-blade-views)
8. [Examples and Patterns](#examples-and-patterns)

## Form Request Validation

### Creating Form Request Classes

The package provides a `make:request` command to generate Form Request classes:

```bash
php spark make:request User/StoreUserRequest
```

This will create a new request class at `app/Requests/User/StoreUserRequest.php`.

### Basic Form Request Structure

```php
<?php

namespace App\Requests\User;

use Rcalicdan\Ci4Larabridge\Validation\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'avatar' => 'ci_file|ci_image|ci_mimes:jpg,png,jpeg|ci_file_size:2048'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'A name is required',
            'email.unique' => 'This email is already registered',
            'avatar.ci_image' => 'Please upload a valid image file',
        ];
    }

    public function attributes()
    {
        return [
            'email' => 'email address',
            'avatar' => 'profile picture',
        ];
    }
}
```

### Using Form Requests in Controllers

#### Approach 1: Class Instance with Automatic Validation

```php
public function store()
{
    $request = new StoreUserRequest();

    // Validation already happened in the constructor
    // If validation failed, user was automatically redirected back with errors

    $userData = $request->validated();

    // Handle file separately since it's a CI4 file object
    $avatarPath = null;
    if ($request->hasFile('avatar')) {
        $file = $request->file('avatar');
        $avatarPath = 'uploads/avatars/' . $file->getRandomName();
        $file->move('uploads/avatars', $file->getRandomName());
    }

    $userData['avatar'] = $avatarPath;
    User::create($userData);

    return redirect()->route('users.index')
        ->with('success', 'User created successfully.');
}
```

#### Approach 2: Static Validation Method

```php
public function store()
{
    // Static method validates and returns the validated data array
    $validated = StoreUserRequest::validateRequest();

    // Handle file upload if present
    $avatarPath = null;
    if (isset($validated['avatar']) && is_array($validated['avatar']) && isset($validated['avatar']['_ci_file'])) {
        $file = $validated['avatar']['_ci_file'];
        $avatarPath = 'uploads/avatars/' . $file->getRandomName();
        $file->move('uploads/avatars', $file->getRandomName());
    }

    // Remove the raw file data before creating the user
    unset($validated['avatar']);
    $validated['avatar_path'] = $avatarPath;

    User::create($validated);

    return redirect()->route('users.index')
        ->with('success', 'User created successfully');
}
```

## Direct Validation with RequestValidator

For simpler cases where you don't want to create a dedicated request class:

```php
public function store()
{
    // Direct validation without a form request class
    $validatedData = RequestValidator::validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8',
        'profile_image' => 'ci_file|ci_image|ci_mimes:jpg,png,jpeg|ci_file_size:2048',
    ], [
        'email.unique' => 'This email address is already registered',
        'profile_image.ci_image' => 'Please upload a valid image file',
    ]);

    // Handle the file upload separately
    $imagePath = null;
    if ($validatedData->hasFile('profile_image')) {
        $file = $validatedData->file('profile_image');
        $imagePath = 'uploads/profiles/' . $file->getRandomName();
        $file->move('uploads/profiles', $file->getRandomName());
    }

    // Create the user with validated data
    User::create([
        'name' => $validatedData->name,
        'email' => $validatedData->email,
        'password' => password_hash($validatedData->password, PASSWORD_DEFAULT),
        'profile_image' => $imagePath
    ]);

    return redirect()->route('users.index')
        ->with('success', 'User created successfully');
}
```

## File Validation in CodeIgniter 4

### Important: CodeIgniter 4 File Validation Rules

Since CodeIgniter's file handling differs from Laravel's, this package provides special validation rules for files:

| Rule                   | Description                                                          |
| ---------------------- | -------------------------------------------------------------------- |
| `ci_file`              | Validates that the field contains a valid CodeIgniter uploaded file  |
| `ci_image`             | Validates that the file is a valid image file                        |
| `ci_mimes:jpg,png,etc` | Validates the file's mime type against the specified extensions      |
| `ci_file_size:2048`    | Validates the file size is less than the specified size in kilobytes |
| `ci_video`             | Validates that the file is a valid video file                        |

### Example File Validation Rules

```php
public function rules()
{
    return [
        'profile_image' => 'ci_file|ci_image|ci_mimes:jpg,png,jpeg|ci_file_size:2048',
        'resume' => 'nullable|ci_file|ci_mimes:pdf,doc,docx|ci_file_size:5120',
        'video_intro' => 'nullable|ci_file|ci_video|ci_file_size:20480',
        'documents' => 'array',
        'documents.*' => 'ci_file|ci_mimes:pdf,doc,docx|ci_file_size:3072',
    ];
}
```

### Handling Validated Files

```php
public function store()
{
    $request = new JobApplicationRequest();

    // Create the base application record
    $application = JobApplication::create([
        'name' => $request->name,
        'email' => $request->email,
        'job_id' => $request->job_id,
        'cover_letter' => $request->cover_letter,
    ]);

    // Handle profile image
    if ($request->hasFile('profile_image')) {
        $image = $request->file('profile_image');
        $imageName = $image->getRandomName();
        $image->move('uploads/applications/images', $imageName);

        $application->profile_image = 'uploads/applications/images/' . $imageName;
    }

    // Handle resume
    if ($request->hasFile('resume')) {
        $resume = $request->file('resume');
        $resumeName = $resume->getRandomName();
        $resume->move('uploads/applications/resumes', $resumeName);

        $application->resume = 'uploads/applications/resumes/' . $resumeName;
    }

    // Handle video intro if present
    if ($request->hasFile('video_intro')) {
        $video = $request->file('video_intro');
        $videoName = $video->getRandomName();
        $video->move('uploads/applications/videos', $videoName);

        $application->video_intro = 'uploads/applications/videos/' . $videoName;
    }

    // Handle multiple document uploads
    if ($request->hasFile('documents')) {
        $documents = $request->file('documents');
        $documentPaths = [];

        foreach ($documents as $index => $document) {
            $documentName = $document->getRandomName();
            $document->move('uploads/applications/documents', $documentName);
            $documentPaths[] = 'uploads/applications/documents/' . $documentName;
        }

        // Store document paths as JSON or handle according to your DB schema
        $application->documents = json_encode($documentPaths);
    }

    $application->save();

    return redirect()->route('applications.thank-you')
        ->with('success', 'Your application has been submitted successfully!');
}
```

## Working with Validation Results

### With FormRequest

```php
$request = new StoreUserRequest();

// Get all validated data as array
$data = $request->validated();

// Get all validated data as object
$dataObject = $request->validated(true);

// Access individual fields as properties
$name = $request->name;

// Check if a field exists and has a non-empty value
if ($request->has('middle_name')) {
    // Do something with optional field
}

// Check if a field has a file
if ($request->hasFile('avatar')) {
    $file = $request->file('avatar');
    // $file is a CodeIgniter UploadedFile instance
}
```

### With ValidatedData

```php
$validatedData = RequestValidator::validate([
    'name' => 'required',
    'email' => 'required|email',
    'tags' => 'array',
    'document' => 'ci_file|ci_mimes:pdf'
]);

// Get all data
$all = $validatedData->all();

// Get specific fields
$nameAndEmail = $validatedData->only('name', 'email');

// Get everything except certain fields
$withoutTags = $validatedData->except('tags');

// Get a specific field with a default value
$tags = $validatedData->get('tags', []);

// Access as property
$name = $validatedData->name;

// File handling
if ($validatedData->hasFile('document')) {
    $document = $validatedData->file('document');
    // $document is a CodeIgniter UploadedFile instance
}
```

## Displaying Errors in Blade Views

You can display validation errors in your Blade templates:
```blade
@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
```

Checking for specific field errors:

```blade
<div class="form-group">
    <label for="email">Email Address</label>
    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">

    @error('email')
        <div class="invalid-feedback">
            {{ $message }}
        </div>
    @enderror
</div>
```

## Examples and Patterns

### Example: Product Upload with Images

```php
<?php

namespace App\Requests;

use Rcalicdan\Ci4Larabridge\Validation\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'main_image' => 'required|ci_file|ci_image|ci_mimes:jpg,png,jpeg|ci_file_size:5120',
            'gallery_images' => 'array|nullable',
            'gallery_images.*' => 'ci_file|ci_image|ci_mimes:jpg,png,jpeg|ci_file_size:5120',
            'product_pdf' => 'nullable|ci_file|ci_mimes:pdf|ci_file_size:10240',
        ];
    }

    public function messages()
    {
        return [
            'main_image.required' => 'A main product image is required',
            'main_image.ci_image' => 'The main image must be a valid image file',
            'gallery_images.*.ci_image' => 'All gallery uploads must be valid images',
            'product_pdf.ci_mimes' => 'The product PDF must be a valid PDF file'
        ];
    }
}
```

In your controller:

```php
public function store()
{
    $request = new StoreProductRequest();

    // Start with basic product data
    $productData = [
        'name' => $request->name,
        'description' => $request->description,
        'price' => $request->price,
        'category_id' => $request->category_id,
    ];

    // Handle main image
    if ($request->hasFile('main_image')) {
        $mainImage = $request->file('main_image');
        $mainImageName = 'main_' . time() . '_' . $mainImage->getRandomName();
        $mainImage->move('uploads/products', $mainImageName);
        $productData['main_image'] = 'uploads/products/' . $mainImageName;
    }

    // Handle optional PDF
    if ($request->hasFile('product_pdf')) {
        $pdf = $request->file('product_pdf');
        $pdfName = 'pdf_' . time() . '_' . $pdf->getRandomName();
        $pdf->move('uploads/products/pdfs', $pdfName);
        $productData['pdf_path'] = 'uploads/products/pdfs/' . $pdfName;
    }

    // Create the product
    $product = Product::create($productData);

    // Handle gallery images
    if ($request->hasFile('gallery_images')) {
        $galleryImages = $request->file('gallery_images');

        foreach ($galleryImages as $index => $image) {
            $imageName = 'gallery_' . time() . '_' . $index . '_' . $image->getRandomName();
            $image->move('uploads/products/gallery', $imageName);

            // Create image record linked to the product
            ProductImage::create([
                'product_id' => $product->id,
                'path' => 'uploads/products/gallery/' . $imageName,
                'sort_order' => $index
            ]);
        }
    }

    return redirect()->route('admin.products.index')
        ->with('success', 'Product created successfully.');
}
```

### Example: Form with File Input in Blade Template

```blade
<form action="{{ route_to('products.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="form-group">
        <label for="name">Product Name</label>
        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">

        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>

        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="price">Price</label>
        <input type="number" step="0.01" name="price" id="price" class="form-control @error('price') is-invalid @enderror" value="{{ old('price') }}">

        @error('price')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="category_id">Category</label>
        <select name="category_id" id="category_id" class="form-control @error('category_id') is-invalid @enderror">
            <option value="">Select Category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>

        @error('category_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="main_image">Main Product Image</label>
        <input type="file" name="main_image" id="main_image" class="form-control-file @error('main_image') is-invalid @enderror">
        <small class="text-muted">Accepted formats: JPG, PNG, JPEG. Max size: 5MB</small>

        @error('main_image')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="gallery_images">Gallery Images (Optional)</label>
        <input type="file" name="gallery_images[]" id="gallery_images" multiple class="form-control-file @error('gallery_images') is-invalid @enderror">
        <small class="text-muted">You can select multiple images. Accepted formats: JPG, PNG, JPEG. Max size per image: 5MB</small>

        @error('gallery_images')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        @error('gallery_images.0')
            <div class="invalid-feedback d-block">Error in gallery image: {{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="product_pdf">Product Manual PDF (Optional)</label>
        <input type="file" name="product_pdf" id="product_pdf" class="form-control-file @error('product_pdf') is-invalid @enderror">
        <small class="text-muted">Accepted format: PDF. Max size: 10MB</small>

        @error('product_pdf')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary">Create Product</button>
</form>
```

By leveraging these validation approaches, you can implement clean, maintainable form handling in your CodeIgniter application with the elegance and familiarity of Laravel's validation system, while properly handling CodeIgniter's file upload functionality.

Remember to use the `ci_file`, `ci_image`, `ci_mimes`, `ci_file_size`, and `ci_video` rules for file validation as these are specifically designed to work with CodeIgniter 4's file handling system, rather than Laravel's native file validation rules which are not compatible.
