# FirestoreModel Guide

The `FirestoreModel` class provides an Eloquent-like interface for working with Firestore documents. This guide covers all the features and capabilities of FirestoreModel.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Model Definition](#model-definition)
- [CRUD Operations](#crud-operations)
- [Query Builder](#query-builder)
- [Attributes & Casting](#attributes--casting)
- [Timestamps](#timestamps)
- [Events](#events)
- [Mass Assignment](#mass-assignment)
- [Serialization](#serialization)
- [Best Practices](#best-practices)

## Basic Usage

### Creating a Model

```bash
php artisan make:firestore-model Post
```

```php
<?php

namespace App\Models;

use JTD\FirebaseModels\Firestore\FirestoreModel;

class Post extends FirestoreModel
{
    protected ?string $collection = 'posts';
    
    protected array $fillable = [
        'title', 'content', 'published', 'author_id', 'tags'
    ];
    
    protected array $casts = [
        'published' => 'boolean',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

## Model Definition

### Collection Name

```php
class Post extends FirestoreModel
{
    // Explicit collection name
    protected ?string $collection = 'posts';
    
    // If not specified, uses snake_case of class name
    // Post -> posts, BlogPost -> blog_posts
}
```

### Primary Key

```php
class Post extends FirestoreModel
{
    // Default primary key is 'id'
    protected string $primaryKey = 'id';
    
    // For auto-incrementing keys (Firestore generates IDs)
    public bool $incrementing = true;
    
    // Key type
    protected string $keyType = 'string';
}
```

### Fillable Attributes

```php
class Post extends FirestoreModel
{
    // Mass assignable attributes
    protected array $fillable = [
        'title', 'content', 'published', 'author_id'
    ];
    
    // Or use guarded to protect specific attributes
    protected array $guarded = ['id', 'created_at', 'updated_at'];
}
```

## CRUD Operations

### Creating Records

```php
// Using create() method
$post = Post::create([
    'title' => 'My First Post',
    'content' => 'This is the content.',
    'published' => true
]);

// Using new instance and save()
$post = new Post();
$post->title = 'My Second Post';
$post->content = 'More content here.';
$post->save();

// Using firstOrCreate()
$post = Post::firstOrCreate(
    ['title' => 'Unique Post'],
    ['content' => 'Default content', 'published' => false]
);

// Using updateOrCreate()
$post = Post::updateOrCreate(
    ['title' => 'Updated Post'],
    ['content' => 'New content', 'published' => true]
);
```

### Reading Records

```php
// Find by ID
$post = Post::find('post-id-123');

// Find or fail
$post = Post::findOrFail('post-id-123');

// Find multiple
$posts = Post::findMany(['id1', 'id2', 'id3']);

// Get all records
$posts = Post::all();

// Get first record
$post = Post::first();

// Get first or fail
$post = Post::firstOrFail();
```

### Updating Records

```php
// Update existing model
$post = Post::find('post-id-123');
$post->title = 'Updated Title';
$post->save();

// Mass update
$post->update(['title' => 'New Title', 'published' => true]);

// Update multiple records
Post::where('published', false)->update(['published' => true]);

// Touch timestamps
$post->touch();
```

### Deleting Records

```php
// Delete model instance
$post = Post::find('post-id-123');
$post->delete();

// Delete by query
Post::where('published', false)->delete();

// Delete quietly (without events)
$post->deleteQuietly();
```

## Query Builder

### Basic Queries

```php
// Where clauses
$posts = Post::where('published', true)->get();
$posts = Post::where('views', '>', 100)->get();
$posts = Post::where('title', 'like', '%Laravel%')->get();

// Multiple where clauses
$posts = Post::where('published', true)
    ->where('author_id', 'user123')
    ->get();

// Or where clauses
$posts = Post::where('published', true)
    ->orWhere('featured', true)
    ->get();
```

### Advanced Where Clauses

```php
// Where in
$posts = Post::whereIn('category_id', [1, 2, 3])->get();

// Where not in
$posts = Post::whereNotIn('status', ['draft', 'archived'])->get();

// Where null
$posts = Post::whereNull('deleted_at')->get();

// Where not null
$posts = Post::whereNotNull('published_at')->get();

// Where between
$posts = Post::whereBetween('created_at', ['2023-01-01', '2023-12-31'])->get();

// Where date
$posts = Post::whereDate('created_at', '2023-01-01')->get();

// Where year
$posts = Post::whereYear('created_at', 2023)->get();
```

### Ordering

```php
// Order by
$posts = Post::orderBy('created_at', 'desc')->get();
$posts = Post::orderBy('title', 'asc')->get();

// Order by descending
$posts = Post::orderByDesc('created_at')->get();

// Latest/oldest
$posts = Post::latest()->get(); // latest('created_at')
$posts = Post::oldest('updated_at')->get();

// Random order
$posts = Post::inRandomOrder()->get();
```

### Limiting & Pagination

```php
// Limit results
$posts = Post::limit(10)->get();
$posts = Post::take(5)->get(); // alias for limit

// Offset
$posts = Post::offset(10)->limit(5)->get();
$posts = Post::skip(10)->take(5)->get(); // aliases

// Pagination
$posts = Post::paginate(15);
$posts = Post::simplePaginate(10);

// Cursor pagination (Firestore-optimized)
$posts = Post::startAfter('document-id')->limit(10)->get();
```

### Aggregates

```php
// Count
$count = Post::count();
$publishedCount = Post::where('published', true)->count();

// Existence
$exists = Post::where('title', 'My Post')->exists();
$doesntExist = Post::where('title', 'Missing')->doesntExist();

// Min/Max
$minViews = Post::min('views');
$maxViews = Post::max('views');

// Sum/Average
$totalViews = Post::sum('views');
$avgViews = Post::avg('views');
```

## Attributes & Casting

### Attribute Casting

```php
class Post extends FirestoreModel
{
    protected array $casts = [
        'published' => 'boolean',
        'views' => 'integer',
        'rating' => 'float',
        'tags' => 'array',
        'metadata' => 'object',
        'published_at' => 'datetime',
        'settings' => 'json',
    ];
}
```

### Supported Cast Types

- `boolean` - Converts to PHP boolean
- `integer` - Converts to PHP integer
- `float` - Converts to PHP float
- `string` - Converts to PHP string
- `array` - Converts to PHP array
- `object` - Converts to PHP object
- `datetime` - Converts to Carbon instance
- `json` - JSON encode/decode

### Accessing Attributes

```php
$post = Post::find('post-id');

// Get attribute
$title = $post->title;
$published = $post->published; // Cast to boolean

// Set attribute
$post->title = 'New Title';
$post->published = true;

// Check if attribute exists
if (isset($post->title)) {
    // Title is set
}

// Get all attributes
$attributes = $post->getAttributes();

// Get dirty attributes
$dirty = $post->getDirty();

// Check if model is dirty
if ($post->isDirty()) {
    // Model has unsaved changes
}
```

## Timestamps

```php
class Post extends FirestoreModel
{
    // Enable timestamps (default: true)
    public bool $timestamps = true;
    
    // Customize timestamp fields
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    // Timestamp format
    protected string $dateFormat = 'Y-m-d H:i:s';
}

// Usage
$post = Post::create(['title' => 'Test']);
echo $post->created_at; // Carbon instance
echo $post->updated_at; // Carbon instance

// Touch timestamps
$post->touch();
```

## Events

```php
// Register event listeners
Post::creating(function ($post) {
    $post->slug = Str::slug($post->title);
});

Post::created(function ($post) {
    Log::info('Post created: ' . $post->title);
});

Post::updating(function ($post) {
    if ($post->isDirty('title')) {
        $post->slug = Str::slug($post->title);
    }
});

Post::deleting(function ($post) {
    // Cancel deletion if post has comments
    if ($post->comments()->count() > 0) {
        return false;
    }
});

// Available events:
// creating, created, updating, updated, saving, saved, deleting, deleted, retrieved
```

## Mass Assignment

```php
class Post extends FirestoreModel
{
    // Allow mass assignment for these attributes
    protected array $fillable = [
        'title', 'content', 'published'
    ];
    
    // Or protect specific attributes
    protected array $guarded = [
        'id', 'created_at', 'updated_at'
    ];
}

// Mass assignment
$post = Post::create([
    'title' => 'My Post',
    'content' => 'Content here',
    'published' => true
]);

// Force fill (bypasses fillable/guarded)
$post = new Post();
$post->forceFill([
    'id' => 'custom-id',
    'title' => 'Forced Title'
]);
```

## Serialization

```php
// Convert to array
$array = $post->toArray();

// Convert to JSON
$json = $post->toJson();

// Hide attributes from serialization
class Post extends FirestoreModel
{
    protected array $hidden = ['password', 'secret_key'];
}

// Show only specific attributes
class Post extends FirestoreModel
{
    protected array $visible = ['id', 'title', 'content'];
}

// Append computed attributes
class Post extends FirestoreModel
{
    protected array $appends = ['full_title'];
    
    public function getFullTitleAttribute()
    {
        return $this->title . ' - ' . $this->subtitle;
    }
}
```

## Best Practices

### 1. Use Explicit Collection Names
```php
// Good
protected ?string $collection = 'blog_posts';

// Avoid relying on auto-generated names
```

### 2. Define Fillable Attributes
```php
// Always specify fillable or guarded
protected array $fillable = ['title', 'content', 'published'];
```

### 3. Use Appropriate Casts
```php
// Cast attributes to proper types
protected array $casts = [
    'published' => 'boolean',
    'tags' => 'array',
    'created_at' => 'datetime',
];
```

### 4. Handle Events Properly
```php
// Use events for business logic
Post::creating(function ($post) {
    $post->slug = Str::slug($post->title);
    $post->author_id = auth()->id();
});
```

### 5. Optimize Queries
```php
// Use specific queries instead of loading all data
$posts = Post::where('published', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Use cursor pagination for large datasets
$posts = Post::startAfter($lastDocumentId)->limit(20)->get();
```

### 6. Error Handling
```php
try {
    $post = Post::findOrFail('invalid-id');
} catch (ModelNotFoundException $e) {
    // Handle not found
}

try {
    $post->save();
} catch (Exception $e) {
    // Handle save errors
}
```
