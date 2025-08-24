# Firebase Models Caching Guide

This comprehensive guide covers the intelligent caching system in the JTD Firebase Models package, designed to optimize Firestore performance and reduce read costs.

## 📋 Overview

The package provides a **two-tier caching system**:

1. **Request Cache** - Ultra-fast in-memory cache for single request
2. **Persistent Cache** - Cross-request cache using Laravel cache drivers (Redis, Memcached, etc.)

### 🎯 Benefits

- **Performance**: Dramatically reduce query response times
- **Cost Savings**: Minimize Firestore read operations and billing
- **Scalability**: Handle high-traffic applications efficiently
- **Intelligent**: Automatic cache promotion and invalidation

## 🔧 Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Enable/disable caching
FIREBASE_CACHE_ENABLED=true

# Cache store (redis, memcached, database, file, array)
FIREBASE_CACHE_STORE=redis

# Default TTL in seconds (300 = 5 minutes)
FIREBASE_CACHE_TTL=300

# Cache key prefix
FIREBASE_CACHE_PREFIX=firebase_models
```

### Configuration File

Publish and configure `config/firebase-models.php`:

```bash
php artisan vendor:publish --tag=firebase-models-config
```

```php
'cache' => [
    // Master switch for all caching
    'enabled' => env('FIREBASE_CACHE_ENABLED', true),
    
    // Laravel cache store to use
    'store' => env('FIREBASE_CACHE_STORE', 'redis'),
    
    // Default TTL in seconds
    'ttl' => env('FIREBASE_CACHE_TTL', 300),
    
    // Cache key prefix
    'prefix' => env('FIREBASE_CACHE_PREFIX', 'firebase_models'),
    
    // Advanced settings
    'request_enabled' => true,      // Enable request cache
    'persistent_enabled' => true,   // Enable persistent cache
    'auto_promote' => true,         // Auto-promote persistent hits to request cache
    'max_items' => 1000,           // Max items in request cache
],
```

### Cache Store Setup

#### Redis (Recommended)

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

FIREBASE_CACHE_STORE=redis
```

#### Memcached

```env
CACHE_DRIVER=memcached
MEMCACHED_HOST=127.0.0.1

FIREBASE_CACHE_STORE=memcached
```

#### Database

```env
FIREBASE_CACHE_STORE=database
```

Create cache table:
```bash
php artisan cache:table
php artisan migrate
```

## 🚀 Usage Examples

### Basic Query Caching

```php
use App\Models\User;

// Automatically cached for 5 minutes (default TTL)
$users = User::where('active', true)->get();

// Same query within TTL returns cached result
$cachedUsers = User::where('active', true)->get(); // From cache!
```

### Custom Cache TTL

```php
// Cache for 1 hour (3600 seconds)
$posts = Post::where('published', true)
    ->cache(3600)
    ->get();

// Cache forever (until manually invalidated)
$settings = Setting::cache(null)->get();
```

### Cache Tags for Organized Invalidation

```php
// Tag cache entries for easy invalidation
$userPosts = Post::where('user_id', $userId)
    ->cacheTags(['user:' . $userId, 'posts'])
    ->get();

// Later, invalidate all posts for a user
Post::flushCacheTags(['user:' . $userId]);
```

### Custom Cache Keys

```php
// Use custom cache key
$popularPosts = Post::where('views', '>', 1000)
    ->cacheKey('popular_posts_' . date('Y-m-d'))
    ->cache(3600)
    ->get();
```

### Disable Caching for Specific Queries

```php
// Skip cache for real-time data
$liveStats = Analytics::withoutCache()->get();

// Or disable for a query chain
$freshData = User::disableCache()
    ->where('last_login', '>', now()->subMinutes(5))
    ->get();
```

## 🔍 Cache Management

### Check Cache Status

```php
$query = User::where('active', true);

// Check if query result is cached
if ($query->isCached()) {
    echo "Result is cached!";
}

// Get cache debug information
$debugInfo = $query->getCacheDebugInfo();
print_r($debugInfo);
```

### Manual Cache Operations

```php
// Warm cache by executing query
User::where('active', true)->warmCache();

// Clear cache for specific query
User::where('active', true)->clearCache();

// Flush all cache for a model/collection
User::flushCache();

// Flush cache by tags
User::flushCacheTags(['users', 'active']);
```

### Cache Statistics

```php
use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Cache\PersistentCache;

// Get request cache stats
$requestStats = RequestCache::getStats();
// ['hits' => 45, 'misses' => 12, 'sets' => 12, ...]

// Get persistent cache stats  
$persistentStats = PersistentCache::getStats();
// ['hits' => 123, 'misses' => 45, 'sets' => 45, ...]
```

## ⚙️ Advanced Configuration

### Cache Hierarchy Behavior

```php
use JTD\FirebaseModels\Cache\CacheManager;

// Configure cache manager
CacheManager::configure([
    'request_cache_enabled' => true,
    'persistent_cache_enabled' => true,
    'default_ttl' => 3600,
    'default_store' => 'redis',
    'auto_promote' => true, // Promote persistent hits to request cache
]);
```

### Custom Cache Stores

```php
// Use different stores for different models
class User extends FirestoreModel
{
    protected $cacheStore = 'redis';
}

class Analytics extends FirestoreModel  
{
    protected $cacheStore = 'memcached';
}
```

### Cache Invalidation Strategies

```php
class Post extends FirestoreModel
{
    protected static function booted()
    {
        // Auto-invalidate cache on model changes
        static::saved(function ($post) {
            $post->flushCache();
            $post->flushCacheTags(['posts', 'user:' . $post->user_id]);
        });
        
        static::deleted(function ($post) {
            $post->flushCache();
        });
    }
}
```

## 🛠️ Cache Middleware

### Clear Request Cache

Add middleware to clear request cache between requests:

```php
// In app/Http/Kernel.php
protected $middleware = [
    // ... other middleware
    \JTD\FirebaseModels\Cache\Middleware\ClearRequestCache::class,
];
```

### Custom Cache Middleware

```php
<?php

namespace App\Http\Middleware;

use JTD\FirebaseModels\Cache\RequestCache;

class CustomCacheMiddleware
{
    public function handle($request, \Closure $next)
    {
        // Clear cache for admin users
        if ($request->user()?->isAdmin()) {
            RequestCache::clear();
        }
        
        return $next($request);
    }
}
```

## 🔧 Performance Optimization

### Cache Warming Strategies

```php
// Warm cache during off-peak hours
class WarmCacheCommand extends Command
{
    public function handle()
    {
        // Warm frequently accessed data
        User::where('active', true)->warmCache();
        Post::where('published', true)->warmCache();
        
        $this->info('Cache warmed successfully!');
    }
}
```

### Batch Cache Operations

```php
// Efficiently cache multiple queries
$queries = [
    ['model' => User::class, 'method' => 'active'],
    ['model' => Post::class, 'method' => 'published'],
];

foreach ($queries as $query) {
    $query['model']::{$query['method']}()->warmCache();
}
```

### Memory Management

```php
// Configure request cache limits
use JTD\FirebaseModels\Cache\RequestCache;

// Set maximum items in request cache
RequestCache::setMaxItems(500);

// Clear request cache when memory is low
if (memory_get_usage() > 50 * 1024 * 1024) { // 50MB
    RequestCache::clear();
}
```

## 🐛 Troubleshooting

### Common Issues

1. **Cache not working**
   ```php
   // Check if caching is enabled
   if (!config('firebase-models.cache.enabled')) {
       // Enable in config or .env
   }
   ```

2. **Wrong cache store**
   ```php
   // Verify cache store configuration
   $store = config('firebase-models.cache.store');
   if (!Cache::store($store)->getStore()) {
       // Configure the cache store properly
   }
   ```

3. **Cache not invalidating**
   ```php
   // Manual cache clear
   User::flushCache();
   
   // Or clear all Firebase cache
   Cache::tags(['firebase_models'])->flush();
   ```

### Debug Mode

Enable cache debugging:

```env
LOG_LEVEL=debug
```

```php
// Get detailed cache information
$debugInfo = User::where('active', true)->getCacheDebugInfo();
Log::debug('Cache Debug', $debugInfo);
```

### Performance Monitoring

```php
// Monitor cache hit rates
$stats = RequestCache::getStats();
$hitRate = $stats['hits'] / ($stats['hits'] + $stats['misses']) * 100;

if ($hitRate < 80) {
    Log::warning("Low cache hit rate: {$hitRate}%");
}
```

## 🚫 Disabling Cache

### Globally Disable

```env
FIREBASE_CACHE_ENABLED=false
```

### Disable for Testing

```php
// In tests
use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Cache\PersistentCache;

public function setUp(): void
{
    parent::setUp();
    
    RequestCache::disable();
    PersistentCache::disable();
}
```

### Disable for Specific Environments

```php
// In AppServiceProvider
if (app()->environment('testing')) {
    config(['firebase-models.cache.enabled' => false]);
}
```

## 📚 Next Steps

- [Query Builder Documentation](05-query-builder.md)
- [Model Relationships](04-models.md)
- [Authentication Setup](AUTH_SETUP.md)
- [Performance Best Practices](PERFORMANCE.md)
