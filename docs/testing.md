# Testing Guide

This guide covers testing Firebase Models applications using the comprehensive testing infrastructure provided by the package.

## Table of Contents

- [Testing Overview](#testing-overview)
- [FirestoreMock](#firestoremock)
- [Test Setup](#test-setup)
- [Testing Models](#testing-models)
- [Testing Queries](#testing-queries)
- [Testing Events](#testing-events)
- [Custom Expectations](#custom-expectations)
- [Best Practices](#best-practices)

## Testing Overview

The Firebase Models package provides a complete testing infrastructure that allows you to test your application without connecting to actual Firebase services.

### Key Features

- **FirestoreMock**: In-memory Firestore emulation
- **Custom Expectations**: Firebase-specific test assertions
- **Fast Execution**: No network calls or external dependencies
- **Deterministic**: Consistent, repeatable test results
- **Laravel Integration**: Works seamlessly with Laravel's testing tools

## FirestoreMock

The `FirestoreMock` class provides a complete in-memory emulation of Firestore operations.

### Automatic Setup

The mock is automatically set up in your test environment:

```php
<?php

use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;

class PostTest extends TestCase
{
    use FirestoreMock;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearFirestoreMocks(); // Reset mocks between tests
    }
}
```

### Mock Operations

```php
// Mock document creation
$this->mockFirestoreCreate('posts', 'post-123');

// Mock document retrieval
$this->mockFirestoreGet('posts', 'post-123', [
    'id' => 'post-123',
    'title' => 'Test Post',
    'published' => true
]);

// Mock document updates
$this->mockFirestoreUpdate('posts', 'post-123');

// Mock document deletion
$this->mockFirestoreDelete('posts', 'post-123');

// Mock query results
$this->mockFirestoreQuery('posts', [
    ['id' => '1', 'title' => 'Post 1', 'published' => true],
    ['id' => '2', 'title' => 'Post 2', 'published' => false],
]);
```

### Assertions

```php
// Assert operations were called
$this->assertFirestoreOperationCalled('create', 'posts', 'post-123');
$this->assertFirestoreOperationCalled('update', 'posts', 'post-123');
$this->assertFirestoreOperationCalled('delete', 'posts', 'post-123');

// Assert queries were executed
$this->assertFirestoreQueryExecuted('posts', [
    ['field' => 'published', 'operator' => '==', 'value' => true]
]);
```

## Test Setup

### Base Test Class

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, FirestoreMock;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear mocks between tests
        $this->clearFirestoreMocks();
        
        // Set up test environment
        config(['firebase-models.mode' => 'test']);
    }
}
```

### Feature Test Example

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Post;

class PostTest extends TestCase
{
    public function test_can_create_post()
    {
        $this->mockFirestoreCreate('posts');
        
        $post = Post::create([
            'title' => 'Test Post',
            'content' => 'This is a test post.',
            'published' => true
        ]);
        
        expect($post)->toBeFirestoreModel();
        expect($post->title)->toBe('Test Post');
        expect($post->published)->toBeTrue();
        expect($post)->toExistInFirestore();
    }
}
```

## Testing Models

### Model Creation

```php
public function test_post_creation()
{
    $this->mockFirestoreCreate('posts');
    
    $post = Post::create([
        'title' => 'New Post',
        'content' => 'Post content',
        'published' => false
    ]);
    
    // Test model properties
    expect($post)->toBeFirestoreModel();
    expect($post->title)->toBe('New Post');
    expect($post->published)->toBeFalse();
    expect($post->exists)->toBeTrue();
    expect($post->wasRecentlyCreated)->toBeTrue();
    
    // Test Firestore interaction
    $this->assertFirestoreOperationCalled('create', 'posts');
}
```

### Model Updates

```php
public function test_post_update()
{
    // Mock finding the post
    $this->mockFirestoreGet('posts', 'post-123', [
        'id' => 'post-123',
        'title' => 'Original Title',
        'published' => false
    ]);
    
    // Mock the update operation
    $this->mockFirestoreUpdate('posts', 'post-123');
    
    $post = Post::find('post-123');
    $post->title = 'Updated Title';
    $post->published = true;
    
    expect($post)->toBeDirty(['title', 'published']);
    
    $result = $post->save();
    
    expect($result)->toBeTrue();
    expect($post)->toBeClean();
    expect($post->title)->toBe('Updated Title');
    
    $this->assertFirestoreOperationCalled('update', 'posts', 'post-123');
}
```

### Model Deletion

```php
public function test_post_deletion()
{
    $this->mockFirestoreGet('posts', 'post-123', [
        'id' => 'post-123',
        'title' => 'Test Post'
    ]);
    
    $this->mockFirestoreDelete('posts', 'post-123');
    
    $post = Post::find('post-123');
    
    expect($post->exists)->toBeTrue();
    
    $result = $post->delete();
    
    expect($result)->toBeTrue();
    expect($post->exists)->toBeFalse();
    
    $this->assertFirestoreOperationCalled('delete', 'posts', 'post-123');
}
```

## Testing Queries

### Basic Queries

```php
public function test_query_published_posts()
{
    $this->mockFirestoreQuery('posts', [
        ['id' => '1', 'title' => 'Published Post', 'published' => true],
        ['id' => '2', 'title' => 'Draft Post', 'published' => false],
    ]);
    
    $posts = Post::where('published', true)->get();
    
    expect($posts)->toHaveCount(2); // Mock returns all data
    expect($posts->first())->toBeFirestoreModel();
    
    // In a real implementation, you might filter the mock data
    $publishedPosts = $posts->where('published', true);
    expect($publishedPosts)->toHaveCount(1);
}
```

### Complex Queries

```php
public function test_complex_query()
{
    $this->mockFirestoreQuery('posts', [
        ['id' => '1', 'title' => 'Post 1', 'published' => true, 'views' => 100],
        ['id' => '2', 'title' => 'Post 2', 'published' => true, 'views' => 200],
        ['id' => '3', 'title' => 'Post 3', 'published' => false, 'views' => 50],
    ]);
    
    $posts = Post::where('published', true)
        ->where('views', '>', 150)
        ->orderBy('views', 'desc')
        ->get();
    
    expect($posts)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    
    // Test the query was built correctly
    $this->assertFirestoreQueryExecuted('posts');
}
```

### Pagination

```php
public function test_pagination()
{
    $this->mockFirestoreQuery('posts', [
        ['id' => '1', 'title' => 'Post 1'],
        ['id' => '2', 'title' => 'Post 2'],
        ['id' => '3', 'title' => 'Post 3'],
    ]);
    
    $posts = Post::paginate(2);
    
    expect($posts)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class);
    expect($posts->items())->toHaveCount(3); // Mock limitation
}
```

## Testing Events

### Event Firing

```php
public function test_post_events()
{
    $events = [];
    
    Post::creating(function ($post) use (&$events) {
        $events[] = 'creating';
    });
    
    Post::created(function ($post) use (&$events) {
        $events[] = 'created';
    });
    
    $this->mockFirestoreCreate('posts');
    
    Post::create(['title' => 'Test Post']);
    
    expect($events)->toBe(['creating', 'created']);
}
```

### Event Cancellation

```php
public function test_event_cancellation()
{
    Post::creating(function ($post) {
        if ($post->title === 'forbidden') {
            return false;
        }
    });
    
    $this->mockFirestoreCreate('posts');
    
    $post = new Post(['title' => 'forbidden']);
    $result = $post->save();
    
    expect($result)->toBeFalse();
    expect($post->exists)->toBeFalse();
}
```

### Observer Testing

```php
public function test_observer()
{
    $observer = new PostObserver();
    Post::observe($observer);
    
    $this->mockFirestoreCreate('posts');
    
    Post::create(['title' => 'Test Post']);
    
    // Test observer methods were called
    // This depends on your observer implementation
}
```

## Custom Expectations

The package provides custom Pest expectations for Firebase Models:

### Model Expectations

```php
// Test if value is a FirestoreModel
expect($post)->toBeFirestoreModel();

// Test model attributes
expect($post)->toHaveAttribute('title', 'Test Post');
expect($post)->toHaveAttribute('published', true);

// Test model casts
expect($post)->toHaveCast('published', 'boolean');
expect($post)->toHaveCast('created_at', 'datetime');

// Test model state
expect($post)->toBeDirty(['title', 'content']);
expect($post)->toBeClean();
expect($post)->toExistInFirestore();
expect($post)->toBeRecentlyCreated();
```

### Collection Expectations

```php
// Test collections
expect($posts)->toBeFirestoreCollection();
expect($posts)->toHaveCount(5);
expect($posts->first())->toBeFirestoreModel();
```

## Best Practices

### 1. Clear Mocks Between Tests

```php
protected function setUp(): void
{
    parent::setUp();
    $this->clearFirestoreMocks();
}
```

### 2. Mock Specific Operations

```php
// Good: Mock specific operations you're testing
$this->mockFirestoreCreate('posts');
$post = Post::create(['title' => 'Test']);

// Avoid: Over-mocking operations you're not testing
```

### 3. Test Model State

```php
// Test both the model state and Firestore interactions
$post = Post::create(['title' => 'Test']);

expect($post)->toBeFirestoreModel();
expect($post->exists)->toBeTrue();
$this->assertFirestoreOperationCalled('create', 'posts');
```

### 4. Use Factories for Complex Data

```php
// Create model factories for complex test data
class PostFactory
{
    public static function make($attributes = [])
    {
        return array_merge([
            'title' => 'Default Title',
            'content' => 'Default content',
            'published' => false,
            'created_at' => now()->toDateTimeString(),
        ], $attributes);
    }
}

// Use in tests
$this->mockFirestoreGet('posts', 'post-123', PostFactory::make([
    'title' => 'Custom Title'
]));
```

### 5. Test Error Conditions

```php
public function test_handles_missing_post()
{
    $this->mockFirestoreGet('posts', 'missing-post', null);
    
    $post = Post::find('missing-post');
    
    expect($post)->toBeNull();
}

public function test_handles_save_failure()
{
    // Mock a save failure scenario
    $post = new Post(['title' => '']);
    
    Post::creating(function ($post) {
        return false; // Simulate validation failure
    });
    
    $result = $post->save();
    
    expect($result)->toBeFalse();
}
```

### 6. Test Performance

```php
public function test_query_performance()
{
    $startTime = microtime(true);
    
    $this->mockFirestoreQuery('posts', []);
    Post::where('published', true)->get();
    
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;
    
    // Ensure queries execute quickly with mocks
    expect($executionTime)->toBeLessThan(0.1);
}
```

### 7. Integration Tests

```php
// Mark integration tests that use real Firebase
/**
 * @group integration
 */
public function test_real_firebase_integration()
{
    if (!config('firebase-models.enable_integration_tests')) {
        $this->markTestSkipped('Integration tests disabled');
    }
    
    // Test with real Firebase connection
    $post = Post::create(['title' => 'Integration Test']);
    
    expect($post->exists)->toBeTrue();
    
    // Clean up
    $post->delete();
}
```
