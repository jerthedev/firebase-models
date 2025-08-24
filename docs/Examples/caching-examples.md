# Caching Examples

This guide shows how to effectively use the caching system to improve performance and reduce Firebase costs.

## Basic Caching Setup

### Configuration

```php
// config/firebase-models.php
return [
    'cache' => [
        'enabled' => env('FIREBASE_CACHE_ENABLED', true),
        'store' => env('FIREBASE_CACHE_STORE', 'redis'),
        'ttl' => env('FIREBASE_CACHE_TTL', 300), // 5 minutes
        'prefix' => env('FIREBASE_CACHE_PREFIX', 'firebase_models'),
    ],
];
```

### Environment Variables

```env
# Enable caching
FIREBASE_CACHE_ENABLED=true

# Cache store (redis, file, database, etc.)
FIREBASE_CACHE_STORE=redis

# Default TTL in seconds (5 minutes)
FIREBASE_CACHE_TTL=300

# Cache key prefix
FIREBASE_CACHE_PREFIX=firebase_models
```

## Model-Level Caching

### Automatic Caching

```php
use App\Models\Post;

// Automatically cached based on configuration
$posts = Post::where('published', true)->get();

// Cache hit on subsequent calls
$posts = Post::where('published', true)->get(); // Served from cache
```

### Manual Cache Control

```php
// Cache for specific duration (in seconds)
$posts = Post::where('published', true)
    ->remember(600) // Cache for 10 minutes
    ->get();

// Cache forever (until manually cleared)
$posts = Post::where('published', true)
    ->rememberForever()
    ->get();

// Disable caching for specific query
$posts = Post::where('published', true)
    ->dontCache()
    ->get();
```

### Custom Cache Keys

```php
// Custom cache key
$posts = Post::where('category_id', $categoryId)
    ->remember(300, "posts_category_{$categoryId}")
    ->get();

// Dynamic cache keys
$cacheKey = "user_{$userId}_posts_" . date('Y-m-d');
$posts = Post::where('author_id', $userId)
    ->remember(3600, $cacheKey)
    ->get();
```

## Cache Tags and Invalidation

### Using Cache Tags

```php
// Tag cache entries for easy invalidation
$posts = Post::where('published', true)
    ->cacheTags(['posts', 'published'])
    ->remember(600)
    ->get();

$categories = Category::all()
    ->cacheTags(['categories'])
    ->remember(1800)
    ->get();
```

### Cache Invalidation

```php
use JTD\FirebaseModels\Facades\FirestoreCache;

// Clear specific cache key
FirestoreCache::forget('posts_published');

// Clear by tags
FirestoreCache::tags(['posts'])->flush();

// Clear all model cache
FirestoreCache::tags(['posts', 'categories'])->flush();

// Clear all Firebase Models cache
FirestoreCache::flush();
```

### Automatic Invalidation

```php
class Post extends FirestoreModel
{
    protected array $cacheTags = ['posts'];

    // Automatically clear cache when model is saved
    protected static function booted()
    {
        static::saved(function ($post) {
            FirestoreCache::tags(['posts'])->flush();
        });

        static::deleted(function ($post) {
            FirestoreCache::tags(['posts'])->flush();
        });
    }
}
```

## Request-Level Caching

### In-Memory Caching

```php
// Cache within the same request
$posts = Post::where('published', true)
    ->requestCache()
    ->get();

// Subsequent calls in same request return cached data
$posts = Post::where('published', true)
    ->requestCache()
    ->get(); // No Firestore query
```

### Combining Request and Persistent Cache

```php
// Use both request cache and persistent cache
$posts = Post::where('published', true)
    ->requestCache()
    ->remember(300)
    ->get();
```

## Advanced Caching Patterns

### Cache-Aside Pattern

```php
class PostService
{
    public function getPopularPosts($limit = 10)
    {
        $cacheKey = "popular_posts_{$limit}";
        
        return FirestoreCache::remember($cacheKey, 600, function () use ($limit) {
            return Post::where('published', true)
                ->orderBy('view_count', 'desc')
                ->limit($limit)
                ->dontCache() // Avoid double caching
                ->get();
        });
    }

    public function invalidatePopularPosts()
    {
        FirestoreCache::forget('popular_posts_*');
    }
}
```

### Write-Through Caching

```php
class PostService
{
    public function createPost(array $data)
    {
        $post = Post::create($data);
        
        // Update cache immediately
        $cacheKey = "post_{$post->id}";
        FirestoreCache::put($cacheKey, $post, 600);
        
        // Invalidate related caches
        FirestoreCache::tags(['posts'])->flush();
        
        return $post;
    }

    public function updatePost($id, array $data)
    {
        $post = Post::findOrFail($id);
        $post->update($data);
        
        // Update cache
        $cacheKey = "post_{$id}";
        FirestoreCache::put($cacheKey, $post, 600);
        
        return $post;
    }
}
```

### Cache Warming

```php
use Illuminate\Console\Command;

class WarmCacheCommand extends Command
{
    protected $signature = 'cache:warm';
    protected $description = 'Warm up frequently accessed cache';

    public function handle()
    {
        $this->info('Warming cache...');

        // Warm popular posts
        Post::where('published', true)
            ->orderBy('view_count', 'desc')
            ->limit(50)
            ->remember(3600, 'popular_posts')
            ->get();

        // Warm categories
        Category::all()
            ->remember(7200, 'all_categories')
            ->get();

        $this->info('Cache warmed successfully!');
    }
}
```

## Performance Optimization

### Batch Cache Operations

```php
// Cache multiple queries efficiently
$cacheData = [
    'recent_posts' => Post::latest()->limit(10)->get(),
    'popular_posts' => Post::orderBy('view_count', 'desc')->limit(10)->get(),
    'featured_posts' => Post::where('featured', true)->get(),
];

foreach ($cacheData as $key => $data) {
    FirestoreCache::put($key, $data, 600);
}
```

### Selective Field Caching

```php
// Cache only necessary fields to reduce memory usage
$posts = Post::select(['id', 'title', 'slug', 'created_at'])
    ->where('published', true)
    ->remember(600)
    ->get();
```

### Conditional Caching

```php
class Post extends FirestoreModel
{
    public function scopeCacheIf($query, $condition, $ttl = 300)
    {
        if ($condition) {
            return $query->remember($ttl);
        }
        
        return $query;
    }
}

// Usage
$posts = Post::where('published', true)
    ->cacheIf(app()->environment('production'), 600)
    ->get();
```

## Cache Monitoring

### Cache Hit/Miss Tracking

```php
use JTD\FirebaseModels\Events\CacheHit;
use JTD\FirebaseModels\Events\CacheMiss;

// Listen for cache events
Event::listen(CacheHit::class, function ($event) {
    Log::info('Cache hit', ['key' => $event->key]);
});

Event::listen(CacheMiss::class, function ($event) {
    Log::info('Cache miss', ['key' => $event->key]);
});
```

### Cache Statistics

```php
class CacheStatsService
{
    public function getStats()
    {
        return [
            'total_keys' => FirestoreCache::getRedis()->dbSize(),
            'memory_usage' => FirestoreCache::getRedis()->info('memory')['used_memory_human'],
            'hit_rate' => $this->calculateHitRate(),
        ];
    }

    private function calculateHitRate()
    {
        $info = FirestoreCache::getRedis()->info('stats');
        $hits = $info['keyspace_hits'];
        $misses = $info['keyspace_misses'];
        
        return $hits + $misses > 0 ? ($hits / ($hits + $misses)) * 100 : 0;
    }
}
```

## Testing with Cache

### Cache Testing

```php
use Tests\TestCase;
use JTD\FirebaseModels\Facades\FirestoreCache;

class CacheTest extends TestCase
{
    public function test_posts_are_cached()
    {
        // Clear cache
        FirestoreCache::flush();
        
        // First call should hit database
        $posts1 = Post::where('published', true)->remember(300)->get();
        
        // Second call should hit cache
        $posts2 = Post::where('published', true)->remember(300)->get();
        
        $this->assertEquals($posts1->toArray(), $posts2->toArray());
        
        // Verify cache was used
        $this->assertTrue(FirestoreCache::has('posts_published'));
    }

    public function test_cache_invalidation()
    {
        $post = Post::factory()->create(['published' => true]);
        
        // Cache the query
        Post::where('published', true)->remember(300)->get();
        
        // Update post (should invalidate cache)
        $post->update(['title' => 'Updated Title']);
        
        // Cache should be cleared
        $this->assertFalse(FirestoreCache::tags(['posts'])->has('posts_published'));
    }
}
```

### Mock Cache in Tests

```php
public function test_without_cache()
{
    // Disable cache for test
    config(['firebase-models.cache.enabled' => false]);
    
    $posts = Post::where('published', true)->get();
    
    // Verify no cache was used
    $this->assertFalse(FirestoreCache::has('posts_published'));
}
```

## Best Practices

### Cache Strategy Guidelines

```php
// 1. Cache expensive queries
$expensiveData = Post::with('comments', 'author')
    ->where('published', true)
    ->remember(1800) // 30 minutes
    ->get();

// 2. Use appropriate TTL based on data volatility
$staticData = Category::all()->rememberForever()->get(); // Rarely changes
$dynamicData = Post::latest()->remember(60)->get(); // Changes frequently

// 3. Use cache tags for related data
$posts = Post::where('category_id', $categoryId)
    ->cacheTags(['posts', "category_{$categoryId}"])
    ->remember(600)
    ->get();

// 4. Implement cache warming for critical data
$this->schedule(function () {
    Post::where('featured', true)->remember(3600)->get();
})->hourly();
```

## Next Steps

- Learn about [Testing Examples](testing-examples.md)
- Explore [Basic CRUD Operations](basic-crud.md)
- See [Advanced Querying](advanced-querying.md)
