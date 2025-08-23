# Getting Started with Firebase Models

Welcome to Firebase Models - the Laravel package that brings Eloquent-like functionality to Google Firestore. This guide will help you get up and running quickly.

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Your First Model](#your-first-model)
- [Basic Operations](#basic-operations)
- [Advanced Features](#advanced-features)
- [Next Steps](#next-steps)

## Installation

### Requirements

- PHP 8.2 or higher
- Laravel 12.x
- Google Cloud Firestore project
- Composer

### Install via Composer

```bash
composer require jerthedev/firebase-models
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="JTD\FirebaseModels\FirebaseModelsServiceProvider"
```

This will publish:
- `config/firebase.php` - Main configuration file
- `config/firestore.php` - Firestore-specific settings

## Configuration

### 1. Firebase Project Setup

First, set up your Firebase project:

1. Go to the [Firebase Console](https://console.firebase.google.com/)
2. Create a new project or select an existing one
3. Enable Firestore Database
4. Generate a service account key

### 2. Environment Variables

Add these variables to your `.env` file:

```env
# Firebase Configuration
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_PRIVATE_KEY_ID=your-private-key-id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nYour-Private-Key\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com
FIREBASE_CLIENT_ID=your-client-id
FIREBASE_AUTH_URI=https://accounts.google.com/o/oauth2/auth
FIREBASE_TOKEN_URI=https://oauth2.googleapis.com/token

# Firestore Configuration
FIRESTORE_DATABASE_ID=(default)
FIRESTORE_CACHE_ENABLED=true
FIRESTORE_CACHE_TTL=3600
```

### 3. Service Account Key

Place your Firebase service account JSON file in `storage/app/firebase/`:

```
storage/
  app/
    firebase/
      service-account.json
```

Or set the path in your configuration:

```php
// config/firebase.php
'credentials' => [
    'file' => storage_path('app/firebase/service-account.json'),
    // or use environment variables (recommended for production)
    'auto' => true,
],
```

## Your First Model

### Create a Model

```bash
php artisan make:firestore-model Post
```

This creates a model in `app/Models/Post.php`:

```php
<?php

namespace App\Models;

use JTD\FirebaseModels\Firestore\FirestoreModel;

class Post extends FirestoreModel
{
    /**
     * The collection associated with the model.
     */
    protected ?string $collection = 'posts';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'title',
        'content',
        'published',
        'author_id',
        'tags',
    ];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        'published' => 'boolean',
        'published_at' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        'author_email',
    ];
}
```

### Model Configuration Options

```php
class Post extends FirestoreModel
{
    // Collection name (auto-generated if not specified)
    protected ?string $collection = 'posts';
    
    // Primary key field (default: 'id')
    protected string $primaryKey = 'id';
    
    // Enable/disable timestamps
    public bool $timestamps = true;
    
    // Custom timestamp field names
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    // Mass assignment protection
    protected array $fillable = ['title', 'content'];
    protected array $guarded = ['id', 'created_at'];
    
    // Attribute casting
    protected array $casts = [
        'published' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
        'settings' => 'object',
    ];
    
    // Hidden attributes (for JSON serialization)
    protected array $hidden = ['secret_key'];
    
    // Always append these attributes
    protected array $appends = ['full_title'];
}
```

## Basic Operations

### Creating Documents

```php
use App\Models\Post;

// Create and save
$post = new Post([
    'title' => 'My First Post',
    'content' => 'This is the content of my first post.',
    'published' => true,
    'tags' => ['laravel', 'firebase', 'php'],
]);
$post->save();

// Create directly
$post = Post::create([
    'title' => 'Another Post',
    'content' => 'More content here.',
    'published' => false,
]);

// Mass create
Post::insert([
    ['title' => 'Post 1', 'content' => 'Content 1'],
    ['title' => 'Post 2', 'content' => 'Content 2'],
    ['title' => 'Post 3', 'content' => 'Content 3'],
]);
```

### Reading Documents

```php
// Find by ID
$post = Post::find('document-id');

// Find or fail
$post = Post::findOrFail('document-id');

// Get all documents
$posts = Post::all();

// Get first document
$post = Post::first();

// Count documents
$count = Post::count();
```

### Querying Documents

```php
// Simple where clauses
$publishedPosts = Post::where('published', true)->get();

// Multiple conditions
$posts = Post::where('published', true)
             ->where('author_id', 'user-123')
             ->get();

// Comparison operators
$popularPosts = Post::where('views', '>', 1000)->get();

// Array queries
$phpPosts = Post::where('tags', 'array-contains', 'php')->get();

// Ordering
$latestPosts = Post::orderBy('created_at', 'desc')->get();

// Limiting
$topPosts = Post::orderBy('views', 'desc')->limit(10)->get();

// Pagination
$posts = Post::paginate(15);
```

### Updating Documents

```php
// Update single document
$post = Post::find('document-id');
$post->title = 'Updated Title';
$post->save();

// Update with array
$post->update(['title' => 'New Title', 'content' => 'New content']);

// Mass update
Post::where('published', false)->update(['status' => 'draft']);

// Increment/decrement
$post->increment('views');
$post->increment('likes', 5);
$post->decrement('dislikes');
```

### Deleting Documents

```php
// Delete single document
$post = Post::find('document-id');
$post->delete();

// Delete by ID
Post::destroy('document-id');

// Delete multiple
Post::destroy(['id1', 'id2', 'id3']);

// Mass delete
Post::where('published', false)->delete();
```

## Advanced Features

### Relationships

```php
class Post extends FirestoreModel
{
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
    
    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
    
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }
}

// Using relationships
$post = Post::with('author', 'comments')->find('post-id');
$author = $post->author;
$comments = $post->comments;
```

### Scopes

```php
class Post extends FirestoreModel
{
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }
    
    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }
}

// Using scopes
$publishedPosts = Post::published()->get();
$authorPosts = Post::byAuthor('user-123')->get();
```

### Events

```php
class Post extends FirestoreModel
{
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($post) {
            $post->slug = Str::slug($post->title);
        });
        
        static::updating(function ($post) {
            $post->updated_by = auth()->id();
        });
    }
}
```

### Caching

```php
// Enable caching for queries
$posts = Post::cache(3600)->where('published', true)->get();

// Cache with tags
$posts = Post::cacheWithTags(['posts', 'published'], 3600)
             ->where('published', true)
             ->get();

// Forget cache
Post::forgetCache('posts');
```

### Transactions

```php
use JTD\FirebaseModels\Facades\FirestoreDB;

FirestoreDB::transaction(function ($transaction) {
    $post = Post::find('post-id');
    $post->increment('views');
    
    $user = User::find($post->author_id);
    $user->increment('total_views');
});
```

### Batch Operations

```php
use JTD\FirebaseModels\Firestore\Batch\BatchManager;

// Bulk insert
$posts = [
    ['title' => 'Post 1', 'content' => 'Content 1'],
    ['title' => 'Post 2', 'content' => 'Content 2'],
    ['title' => 'Post 3', 'content' => 'Content 3'],
];

BatchManager::bulkInsert('posts', $posts);

// Bulk update
$updates = [
    'post-1' => ['views' => 100],
    'post-2' => ['views' => 200],
];

BatchManager::bulkUpdate('posts', $updates);
```

## Next Steps

Now that you have the basics down, explore these advanced topics:

1. **[Model Relationships](relationships.md)** - Learn about complex relationships
2. **[Query Optimization](query-optimization.md)** - Optimize your queries for performance
3. **[Caching Strategies](caching.md)** - Implement effective caching
4. **[Real-time Features](realtime.md)** - Add real-time functionality
5. **[Testing](testing.md)** - Test your Firestore models
6. **[Deployment](deployment.md)** - Deploy to production

## Getting Help

- **Documentation**: [Full documentation](README.md)
- **API Reference**: [API docs](api-reference.md)
- **Examples**: [Example applications](examples/)
- **Issues**: [GitHub Issues](https://github.com/jerthedev/firebase-models/issues)
- **Discussions**: [GitHub Discussions](https://github.com/jerthedev/firebase-models/discussions)

## Quick Reference

### Common Commands

```bash
# Create model
php artisan make:firestore-model ModelName

# Create migration
php artisan make:firestore-migration create_posts_collection

# Run migrations
php artisan firestore:migrate

# Create seeder
php artisan make:firestore-seeder PostSeeder

# Run seeders
php artisan firestore:seed
```

### Common Patterns

```php
// Model with relationships and scopes
class Post extends FirestoreModel
{
    protected array $fillable = ['title', 'content', 'published'];
    protected array $casts = ['published' => 'boolean'];
    
    public function author() { return $this->belongsTo(User::class); }
    public function scopePublished($query) { return $query->where('published', true); }
}

// Query with relationships and caching
$posts = Post::with('author')
             ->published()
             ->cache(3600)
             ->orderBy('created_at', 'desc')
             ->paginate(10);

// Transaction with error handling
try {
    FirestoreDB::transaction(function ($transaction) {
        // Your transactional operations
    });
} catch (Exception $e) {
    Log::error('Transaction failed: ' . $e->getMessage());
}
```

Welcome to Firebase Models! 🚀
