# Events System Guide

The FirestoreModel events system provides hooks into the model lifecycle, allowing you to execute code when models are created, updated, deleted, or retrieved. This system is fully compatible with Laravel's Eloquent events.

## Table of Contents

- [Available Events](#available-events)
- [Event Registration](#event-registration)
- [Event Cancellation](#event-cancellation)
- [Observer Pattern](#observer-pattern)
- [Event Control](#event-control)
- [Event Data](#event-data)
- [Best Practices](#best-practices)

## Available Events

### Model Lifecycle Events

| Event | Description | Cancellable |
|-------|-------------|-------------|
| `creating` | Before a model is created | ✅ |
| `created` | After a model is created | ❌ |
| `updating` | Before a model is updated | ✅ |
| `updated` | After a model is updated | ❌ |
| `saving` | Before a model is saved (create or update) | ✅ |
| `saved` | After a model is saved (create or update) | ❌ |
| `deleting` | Before a model is deleted | ✅ |
| `deleted` | After a model is deleted | ❌ |
| `retrieved` | When a model is loaded from database | ❌ |

### Event Flow

#### Creating a New Model
```
saving → creating → [insert to Firestore] → created → saved
```

#### Updating an Existing Model
```
saving → updating → [update in Firestore] → updated → saved
```

#### Deleting a Model
```
deleting → [delete from Firestore] → deleted
```

#### Retrieving a Model
```
[load from Firestore] → retrieved
```

## Event Registration

### Basic Event Listeners

```php
use App\Models\Post;

// Register event listeners
Post::creating(function ($post) {
    $post->slug = Str::slug($post->title);
    $post->author_id = auth()->id();
});

Post::created(function ($post) {
    Log::info('New post created: ' . $post->title);
    
    // Send notification
    Notification::send($post->author, new PostCreatedNotification($post));
});

Post::updating(function ($post) {
    if ($post->isDirty('title')) {
        $post->slug = Str::slug($post->title);
    }
});

Post::updated(function ($post) {
    // Clear cache when post is updated
    Cache::forget("post.{$post->id}");
});

Post::deleting(function ($post) {
    // Prevent deletion if post has comments
    if ($post->comments()->count() > 0) {
        return false; // Cancels the deletion
    }
});

Post::deleted(function ($post) {
    // Clean up related data
    $post->comments()->delete();
    $post->tags()->detach();
});
```

### Multiple Listeners

```php
// You can register multiple listeners for the same event
Post::creating(function ($post) {
    $post->slug = Str::slug($post->title);
});

Post::creating(function ($post) {
    $post->author_id = auth()->id();
});

Post::creating(function ($post) {
    $post->status = 'draft';
});
```

### Conditional Event Registration

```php
// Register events conditionally
if (config('app.env') === 'production') {
    Post::created(function ($post) {
        // Only send notifications in production
        Mail::to($post->author)->send(new PostPublishedMail($post));
    });
}
```

## Event Cancellation

### Cancelling Operations

You can cancel model operations by returning `false` from certain event listeners:

```php
Post::creating(function ($post) {
    // Validate post before creation
    if (empty($post->title)) {
        return false; // Cancels creation
    }
    
    // Check for duplicate titles
    if (Post::where('title', $post->title)->exists()) {
        return false; // Cancels creation
    }
});

Post::updating(function ($post) {
    // Prevent updates to published posts
    if ($post->getOriginal('published') && !auth()->user()->isAdmin()) {
        return false; // Cancels update
    }
});

Post::deleting(function ($post) {
    // Prevent deletion of featured posts
    if ($post->featured) {
        return false; // Cancels deletion
    }
});

Post::saving(function ($post) {
    // Global save validation
    if (!$post->isValid()) {
        return false; // Cancels save operation
    }
});
```

### Handling Cancelled Operations

```php
$post = new Post(['title' => '']); // Empty title

$result = $post->save(); // Returns false if cancelled

if (!$result) {
    // Handle the failed save
    return response()->json(['error' => 'Post could not be saved'], 422);
}
```

## Observer Pattern

### Creating an Observer

```php
<?php

namespace App\Observers;

use App\Models\Post;

class PostObserver
{
    public function creating(Post $post)
    {
        $post->slug = Str::slug($post->title);
        $post->author_id = auth()->id();
    }

    public function created(Post $post)
    {
        Log::info('Post created: ' . $post->title);
        
        // Update search index
        $post->updateSearchIndex();
    }

    public function updating(Post $post)
    {
        if ($post->isDirty('title')) {
            $post->slug = Str::slug($post->title);
        }
    }

    public function updated(Post $post)
    {
        // Clear cache
        Cache::forget("post.{$post->id}");
        
        // Update search index
        $post->updateSearchIndex();
    }

    public function saving(Post $post)
    {
        // Validate before any save operation
        if (!$post->isValid()) {
            return false;
        }
    }

    public function saved(Post $post)
    {
        // Log all save operations
        Log::info('Post saved: ' . $post->title);
    }

    public function deleting(Post $post)
    {
        // Prevent deletion if post has comments
        if ($post->comments()->count() > 0) {
            return false;
        }
    }

    public function deleted(Post $post)
    {
        // Clean up related data
        $post->comments()->delete();
        
        // Remove from search index
        $post->removeFromSearchIndex();
    }

    public function retrieved(Post $post)
    {
        // Track post views
        $post->increment('views');
    }
}
```

### Registering Observers

```php
// In a service provider (e.g., AppServiceProvider)
use App\Models\Post;
use App\Observers\PostObserver;

public function boot()
{
    Post::observe(PostObserver::class);
    
    // Or with an instance
    Post::observe(new PostObserver());
}
```

### Multiple Observers

```php
// Register multiple observers
Post::observe([
    PostObserver::class,
    AuditObserver::class,
    SearchIndexObserver::class,
]);
```

## Event Control

### Disabling Events Temporarily

```php
// Disable events for a specific operation
Post::withoutEvents(function () {
    Post::create([
        'title' => 'Silent Post',
        'content' => 'This post was created without firing events'
    ]);
});

// Disable events for multiple operations
Post::withoutEvents(function () {
    $posts = Post::where('status', 'draft')->get();
    
    foreach ($posts as $post) {
        $post->update(['status' => 'published']);
    }
});
```

### Quiet Operations

```php
// Save without events
$post = Post::find(1);
$post->title = 'Updated Title';
$post->saveQuietly(); // No events fired

// Delete without events
$post->deleteQuietly(); // No events fired

// Create without events (using withoutEvents)
$post = Post::withoutEvents(function () {
    return Post::create(['title' => 'Silent Post']);
});
```

### Flushing Event Listeners

```php
// Remove all event listeners for a model
Post::flushEventListeners();

// Useful for testing
public function setUp(): void
{
    parent::setUp();
    Post::flushEventListeners();
}
```

## Event Data

### Accessing Model State

```php
Post::creating(function ($post) {
    // Model state during creating event
    echo $post->exists; // false (not yet saved)
    echo $post->wasRecentlyCreated; // false
    echo $post->isDirty(); // true (has unsaved changes)
    
    // Access attributes
    echo $post->title;
    echo $post->content;
});

Post::created(function ($post) {
    // Model state during created event
    echo $post->exists; // true (now saved)
    echo $post->wasRecentlyCreated; // true
    echo $post->isDirty(); // true (changes not yet synced)
    
    // Access the ID (now available)
    echo $post->id;
});

Post::updating(function ($post) {
    // Access original values
    $originalTitle = $post->getOriginal('title');
    $newTitle = $post->title;
    
    // Check what changed
    $dirty = $post->getDirty(); // ['title' => 'New Title']
    $changes = $post->getChanges(); // Empty until after update
    
    // Check specific attributes
    if ($post->isDirty('title')) {
        // Title was changed
    }
});

Post::updated(function ($post) {
    // Access changes that were made
    $changes = $post->getChanges(); // ['title' => 'New Title']
    
    // Check if specific attribute was changed
    if ($post->wasChanged('title')) {
        // Title was changed
    }
});
```

### Event Context

```php
Post::creating(function ($post) {
    // Add metadata
    $post->created_by = auth()->id();
    $post->created_ip = request()->ip();
    $post->created_user_agent = request()->userAgent();
});

Post::updating(function ($post) {
    // Track updates
    $post->updated_by = auth()->id();
    $post->updated_at = now();
    
    // Log changes
    $changes = $post->getDirty();
    Log::info('Post updated', [
        'post_id' => $post->id,
        'user_id' => auth()->id(),
        'changes' => $changes
    ]);
});
```

## Best Practices

### 1. Keep Event Listeners Simple

```php
// Good: Simple, focused listeners
Post::created(function ($post) {
    Log::info('Post created: ' . $post->title);
});

Post::created(function ($post) {
    Mail::to($post->author)->send(new PostCreatedMail($post));
});

// Avoid: Complex logic in listeners
Post::created(function ($post) {
    // Too much logic here...
    $this->updateSearchIndex($post);
    $this->sendNotifications($post);
    $this->updateStatistics($post);
    $this->processImages($post);
});
```

### 2. Use Observers for Complex Logic

```php
// Good: Use observers for related functionality
class PostObserver
{
    public function created(Post $post)
    {
        $this->updateSearchIndex($post);
        $this->sendNotifications($post);
        $this->updateStatistics($post);
    }
    
    private function updateSearchIndex(Post $post) { /* ... */ }
    private function sendNotifications(Post $post) { /* ... */ }
    private function updateStatistics(Post $post) { /* ... */ }
}
```

### 3. Handle Errors Gracefully

```php
Post::created(function ($post) {
    try {
        Mail::to($post->author)->send(new PostCreatedMail($post));
    } catch (Exception $e) {
        Log::error('Failed to send post creation email', [
            'post_id' => $post->id,
            'error' => $e->getMessage()
        ]);
    }
});
```

### 4. Use Queued Jobs for Heavy Operations

```php
Post::created(function ($post) {
    // Dispatch heavy operations to queue
    ProcessPostImages::dispatch($post);
    UpdateSearchIndex::dispatch($post);
    SendNotifications::dispatch($post);
});
```

### 5. Be Careful with Event Cancellation

```php
Post::creating(function ($post) {
    // Provide clear feedback when cancelling
    if (!$post->isValid()) {
        // Log why the operation was cancelled
        Log::warning('Post creation cancelled: validation failed', [
            'title' => $post->title,
            'errors' => $post->getValidationErrors()
        ]);
        
        return false;
    }
});
```

### 6. Test Event Behavior

```php
// Test that events are fired
public function test_post_creation_fires_events()
{
    Event::fake();
    
    Post::create(['title' => 'Test Post']);
    
    Event::assertDispatched('eloquent.creating: ' . Post::class);
    Event::assertDispatched('eloquent.created: ' . Post::class);
}

// Test event cancellation
public function test_invalid_post_creation_is_cancelled()
{
    Post::creating(function ($post) {
        return false; // Cancel creation
    });
    
    $post = new Post(['title' => 'Test']);
    $result = $post->save();
    
    $this->assertFalse($result);
    $this->assertFalse($post->exists);
}
```
