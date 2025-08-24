# Testing Examples

This guide shows how to write comprehensive tests for your Firebase Models applications.

## Test Setup

### Base Test Class

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use JTD\FirebaseModels\Testing\FirestoreMock;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, FirestoreMock;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use Firestore mock for all tests
        $this->useFirestoreMock();
        
        // Clear cache between tests
        $this->clearFirestoreCache();
    }
}
```

### Feature Test Base

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use JTD\FirebaseModels\Testing\FirebaseAuthMock;

abstract class FeatureTestCase extends TestCase
{
    use FirebaseAuthMock;

    protected function authenticatedUser(): User
    {
        $user = User::factory()->create();
        $this->mockFirebaseAuth($user);
        return $user;
    }
}
```

## Model Testing

### Basic Model Tests

```php
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Post;

class PostTest extends TestCase
{
    public function test_can_create_post()
    {
        $post = Post::create([
            'title' => 'Test Post',
            'content' => 'This is test content',
            'published' => true,
            'author_id' => 'user123'
        ]);

        $this->assertNotNull($post->id);
        $this->assertEquals('Test Post', $post->title);
        $this->assertTrue($post->published);
    }

    public function test_can_update_post()
    {
        $post = Post::factory()->create();
        
        $post->update([
            'title' => 'Updated Title',
            'published' => false
        ]);

        $this->assertEquals('Updated Title', $post->title);
        $this->assertFalse($post->published);
    }

    public function test_can_delete_post()
    {
        $post = Post::factory()->create();
        $postId = $post->id;
        
        $post->delete();
        
        $this->assertNull(Post::find($postId));
    }
}
```

### Model Relationships

```php
public function test_post_belongs_to_user()
{
    $user = User::factory()->create();
    $post = Post::factory()->create(['author_id' => $user->id]);
    
    $this->assertEquals($user->id, $post->author->id);
}

public function test_user_has_many_posts()
{
    $user = User::factory()->create();
    $posts = Post::factory()->count(3)->create(['author_id' => $user->id]);
    
    $this->assertCount(3, $user->posts);
}
```

### Model Events

```php
public function test_post_creating_event()
{
    Event::fake();
    
    Post::create([
        'title' => 'Test Post',
        'content' => 'Test content'
    ]);
    
    Event::assertDispatched('eloquent.creating: ' . Post::class);
}

public function test_post_slug_generated_on_create()
{
    $post = Post::create([
        'title' => 'My Test Post',
        'content' => 'Test content'
    ]);
    
    $this->assertEquals('my-test-post', $post->slug);
}
```

## Query Builder Testing

### Basic Queries

```php
<?php

namespace Tests\Unit\QueryBuilder;

use Tests\TestCase;
use App\Models\Post;

class QueryBuilderTest extends TestCase
{
    public function test_where_clause()
    {
        Post::factory()->create(['published' => true]);
        Post::factory()->create(['published' => false]);
        
        $publishedPosts = Post::where('published', true)->get();
        
        $this->assertCount(1, $publishedPosts);
        $this->assertTrue($publishedPosts->first()->published);
    }

    public function test_order_by()
    {
        $post1 = Post::factory()->create(['created_at' => now()->subDay()]);
        $post2 = Post::factory()->create(['created_at' => now()]);
        
        $posts = Post::orderBy('created_at', 'desc')->get();
        
        $this->assertEquals($post2->id, $posts->first()->id);
    }

    public function test_limit()
    {
        Post::factory()->count(5)->create();
        
        $posts = Post::limit(3)->get();
        
        $this->assertCount(3, $posts);
    }
}
```

### Complex Queries

```php
public function test_complex_where_conditions()
{
    Post::factory()->create(['published' => true, 'view_count' => 100]);
    Post::factory()->create(['published' => true, 'view_count' => 50]);
    Post::factory()->create(['published' => false, 'view_count' => 200]);
    
    $posts = Post::where('published', true)
        ->where('view_count', '>', 75)
        ->get();
    
    $this->assertCount(1, $posts);
}

public function test_where_in()
{
    $post1 = Post::factory()->create(['category_id' => 1]);
    $post2 = Post::factory()->create(['category_id' => 2]);
    $post3 = Post::factory()->create(['category_id' => 3]);
    
    $posts = Post::whereIn('category_id', [1, 2])->get();
    
    $this->assertCount(2, $posts);
}
```

## Authentication Testing

### Login Tests

```php
<?php

namespace Tests\Feature\Auth;

use Tests\FeatureTestCase;
use App\Models\User;

class LoginTest extends FeatureTestCase
{
    public function test_user_can_login_with_valid_token()
    {
        $user = User::factory()->create();
        
        $response = $this->postJson('/auth/firebase/login', [
            'idToken' => $this->mockFirebaseToken($user)
        ]);
        
        $response->assertOk();
        $this->assertAuthenticatedAs($user, 'firebase');
    }

    public function test_login_fails_with_invalid_token()
    {
        $response = $this->postJson('/auth/firebase/login', [
            'idToken' => 'invalid-token'
        ]);
        
        $response->assertStatus(401);
        $this->assertGuest();
    }

    public function test_user_can_logout()
    {
        $user = $this->authenticatedUser();
        
        $response = $this->postJson('/auth/firebase/logout');
        
        $response->assertOk();
        $this->assertGuest();
    }
}
```

### Registration Tests

```php
public function test_user_can_register()
{
    $userData = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'idToken' => $this->mockFirebaseToken()
    ];
    
    $response = $this->postJson('/auth/firebase/register', $userData);
    
    $response->assertOk();
    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);
}
```

## Caching Tests

### Cache Behavior

```php
<?php

namespace Tests\Unit\Cache;

use Tests\TestCase;
use App\Models\Post;
use JTD\FirebaseModels\Facades\FirestoreCache;

class CacheTest extends TestCase
{
    public function test_query_results_are_cached()
    {
        Post::factory()->create(['published' => true]);
        
        // First query should cache results
        $posts1 = Post::where('published', true)->remember(300)->get();
        
        // Second query should use cache
        $posts2 = Post::where('published', true)->remember(300)->get();
        
        $this->assertEquals($posts1->toArray(), $posts2->toArray());
    }

    public function test_cache_is_invalidated_on_model_change()
    {
        $post = Post::factory()->create(['published' => true]);
        
        // Cache the query
        Post::where('published', true)->remember(300)->get();
        
        // Update model
        $post->update(['title' => 'Updated']);
        
        // Cache should be cleared
        $this->assertFalse(FirestoreCache::has('posts_published'));
    }

    public function test_cache_can_be_disabled()
    {
        config(['firebase-models.cache.enabled' => false]);
        
        Post::factory()->create(['published' => true]);
        
        $posts = Post::where('published', true)->remember(300)->get();
        
        // No cache should be created
        $this->assertFalse(FirestoreCache::has('posts_published'));
    }
}
```

## Integration Tests

### API Endpoints

```php
<?php

namespace Tests\Feature\Api;

use Tests\FeatureTestCase;
use App\Models\Post;

class PostApiTest extends FeatureTestCase
{
    public function test_can_list_posts()
    {
        $user = $this->authenticatedUser();
        Post::factory()->count(3)->create(['published' => true]);
        
        $response = $this->getJson('/api/posts');
        
        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_post()
    {
        $user = $this->authenticatedUser();
        
        $postData = [
            'title' => 'New Post',
            'content' => 'Post content',
            'published' => true
        ];
        
        $response = $this->postJson('/api/posts', $postData);
        
        $response->assertCreated()
            ->assertJsonFragment(['title' => 'New Post']);
    }

    public function test_cannot_create_post_without_authentication()
    {
        $postData = [
            'title' => 'New Post',
            'content' => 'Post content'
        ];
        
        $response = $this->postJson('/api/posts', $postData);
        
        $response->assertUnauthorized();
    }
}
```

## Performance Tests

### Query Performance

```php
<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\Post;

class QueryPerformanceTest extends TestCase
{
    public function test_large_dataset_query_performance()
    {
        // Create large dataset
        Post::factory()->count(1000)->create();
        
        $startTime = microtime(true);
        
        $posts = Post::where('published', true)
            ->limit(50)
            ->get();
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Assert query completes within reasonable time
        $this->assertLessThan(1.0, $executionTime); // Less than 1 second
        $this->assertCount(50, $posts);
    }

    public function test_cached_query_performance()
    {
        Post::factory()->count(100)->create();
        
        // First query (cache miss)
        $startTime = microtime(true);
        Post::where('published', true)->remember(300)->get();
        $firstQueryTime = microtime(true) - $startTime;
        
        // Second query (cache hit)
        $startTime = microtime(true);
        Post::where('published', true)->remember(300)->get();
        $secondQueryTime = microtime(true) - $startTime;
        
        // Cached query should be significantly faster
        $this->assertLessThan($firstQueryTime / 2, $secondQueryTime);
    }
}
```

## Test Factories

### Model Factories

```php
<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'published' => $this->faker->boolean(70), // 70% chance of being published
            'author_id' => 'user_' . $this->faker->uuid(),
            'view_count' => $this->faker->numberBetween(0, 1000),
            'tags' => $this->faker->words(3),
        ];
    }

    public function published()
    {
        return $this->state(function (array $attributes) {
            return [
                'published' => true,
                'published_at' => now(),
            ];
        });
    }

    public function draft()
    {
        return $this->state(function (array $attributes) {
            return [
                'published' => false,
                'published_at' => null,
            ];
        });
    }
}
```

## Test Utilities

### Custom Assertions

```php
<?php

namespace Tests\Utilities;

trait CustomAssertions
{
    protected function assertFirestoreDocumentExists($collection, $id)
    {
        $document = FirestoreDB::collection($collection)->document($id)->snapshot();
        $this->assertTrue($document->exists());
    }

    protected function assertFirestoreDocumentNotExists($collection, $id)
    {
        $document = FirestoreDB::collection($collection)->document($id)->snapshot();
        $this->assertFalse($document->exists());
    }

    protected function assertCacheHit($key)
    {
        $this->assertTrue(FirestoreCache::has($key));
    }

    protected function assertCacheMiss($key)
    {
        $this->assertFalse(FirestoreCache::has($key));
    }
}
```

## Running Tests

### Test Commands

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature

# Run with coverage
vendor/bin/phpunit --coverage-html coverage

# Run specific test file
vendor/bin/phpunit tests/Unit/Models/PostTest.php

# Run specific test method
vendor/bin/phpunit --filter test_can_create_post
```

### Continuous Integration

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
        
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
      
    - name: Run tests
      run: vendor/bin/phpunit --coverage-clover=coverage.xml
      
    - name: Upload coverage
      uses: codecov/codecov-action@v1
      with:
        file: ./coverage.xml
```

## Next Steps

- Learn about [Basic CRUD Operations](basic-crud.md)
- Explore [Advanced Querying](advanced-querying.md)
- See [Authentication Examples](authentication-examples.md)
