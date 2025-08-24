# Basic CRUD Operations

This guide shows you how to perform basic Create, Read, Update, and Delete operations with Firebase Models.

## Setting Up Your Model

First, create a model using the Artisan command:

```bash
php artisan make:firestore-model Post
```

This creates a model file:

```php
<?php

namespace App\Models;

use JTD\FirebaseModels\Firestore\FirestoreModel;

class Post extends FirestoreModel
{
    protected ?string $collection = 'posts';

    protected array $fillable = [
        'title',
        'content',
        'author_id',
        'published',
        'tags'
    ];

    protected array $casts = [
        'published' => 'boolean',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

## Create Operations

### Creating a Single Record

```php
use App\Models\Post;

// Method 1: Using create()
$post = Post::create([
    'title' => 'My First Post',
    'content' => 'This is the content of my first post.',
    'author_id' => 'user123',
    'published' => true,
    'tags' => ['laravel', 'firebase', 'tutorial']
]);

// Method 2: Using new and save()
$post = new Post();
$post->title = 'My Second Post';
$post->content = 'This is another post.';
$post->author_id = 'user123';
$post->published = false;
$post->tags = ['draft', 'work-in-progress'];
$post->save();

echo "Created post with ID: " . $post->id;
```

### Mass Assignment Protection

```php
// This will only fill the fields listed in $fillable
$post = Post::create([
    'title' => 'Safe Post',
    'content' => 'Only fillable fields are set',
    'author_id' => 'user123',
    'published' => true,
    'secret_field' => 'This will be ignored' // Not in $fillable
]);
```

## Read Operations

### Finding by ID

```php
// Find a specific post by ID
$post = Post::find('post-id-123');

if ($post) {
    echo "Found post: " . $post->title;
} else {
    echo "Post not found";
}

// Find or fail (throws exception if not found)
$post = Post::findOrFail('post-id-123');

// Find or return default
$post = Post::findOr('post-id-123', function () {
    return new Post(['title' => 'Default Post']);
});
```

### Getting Multiple Records

```php
// Get all posts
$posts = Post::all();

// Get first post
$firstPost = Post::first();

// Get latest post
$latestPost = Post::latest()->first();

// Count posts
$count = Post::count();
```

### Basic Queries

```php
// Where clauses
$publishedPosts = Post::where('published', true)->get();

$userPosts = Post::where('author_id', 'user123')->get();

// Multiple conditions
$recentPublishedPosts = Post::where('published', true)
    ->where('created_at', '>', now()->subDays(7))
    ->get();
```

## Update Operations

### Updating Existing Records

```php
// Method 1: Find and update
$post = Post::find('post-id-123');
$post->title = 'Updated Title';
$post->content = 'Updated content';
$post->save();

// Method 2: Update method
$post = Post::find('post-id-123');
$post->update([
    'title' => 'Updated Title',
    'content' => 'Updated content',
    'published' => true
]);

// Method 3: Mass update
Post::where('author_id', 'user123')
    ->update(['published' => true]);
```

### Conditional Updates

```php
// Update only if certain conditions are met
$post = Post::find('post-id-123');

if ($post && !$post->published) {
    $post->update([
        'published' => true,
        'published_at' => now()
    ]);
}
```

## Delete Operations

### Deleting Records

```php
// Method 1: Find and delete
$post = Post::find('post-id-123');
if ($post) {
    $post->delete();
}

// Method 2: Delete by ID
Post::destroy('post-id-123');

// Method 3: Delete multiple by IDs
Post::destroy(['post-id-1', 'post-id-2', 'post-id-3']);

// Method 4: Conditional delete
Post::where('published', false)
    ->where('created_at', '<', now()->subMonths(6))
    ->delete();
```

## Working with Attributes

### Accessing Attributes

```php
$post = Post::find('post-id-123');

// Direct access
echo $post->title;
echo $post->content;

// Array access
echo $post['title'];

// Get all attributes
$attributes = $post->toArray();
```

### Checking for Changes

```php
$post = Post::find('post-id-123');
$post->title = 'New Title';

// Check if model has changes
if ($post->isDirty()) {
    echo "Post has unsaved changes";
}

// Check specific field
if ($post->isDirty('title')) {
    echo "Title has changed";
}

// Get original value
echo "Original title: " . $post->getOriginal('title');
```

## Error Handling

```php
use JTD\FirebaseModels\Exceptions\FirestoreException;

try {
    $post = Post::create([
        'title' => 'Test Post',
        'content' => 'Test content'
    ]);
} catch (FirestoreException $e) {
    echo "Error creating post: " . $e->getMessage();
}

try {
    $post = Post::findOrFail('non-existent-id');
} catch (ModelNotFoundException $e) {
    echo "Post not found";
}
```

## Next Steps

- Learn about [Advanced Querying](advanced-querying.md)
- Explore [Authentication Examples](authentication-examples.md)
- See [Caching Examples](caching-examples.md)
