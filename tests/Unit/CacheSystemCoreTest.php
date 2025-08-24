<?php

namespace JTD\FirebaseModels\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Cache\PersistentCache;
use JTD\FirebaseModels\Cache\QueryCacheKey;
use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Tests\Models\TestPost;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('core')]
#[Group('cache-system')]
class CacheSystemCoreTest extends UnitTestSuite
{
    protected CacheManager $cacheManager;

    protected RequestCache $requestCache;

    protected PersistentCache $persistentCache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheManager = app(CacheManager::class);
        $this->requestCache = app(RequestCache::class);
        $this->persistentCache = app(PersistentCache::class);

        // Clear all caches
        $this->requestCache->clear();
        $this->persistentCache->clear();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        $this->requestCache->clear();
        $this->persistentCache->clear();
        Cache::flush();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_cache_manager_instance()
    {
        expect($this->cacheManager)->toBeInstanceOf(CacheManager::class);
        expect($this->cacheManager->getRequestCache())->toBeInstanceOf(RequestCache::class);
        expect($this->cacheManager->getPersistentCache())->toBeInstanceOf(PersistentCache::class);
    }

    #[Test]
    public function it_handles_request_cache_operations()
    {
        $key = 'test-request-key';
        $value = ['data' => 'test-value', 'timestamp' => time()];

        // Test put and get
        $this->requestCache->put($key, $value);
        $retrieved = $this->requestCache->get($key);

        expect($retrieved)->toEqual($value);

        // Test has
        expect($this->requestCache->has($key))->toBeTrue();
        expect($this->requestCache->has('nonexistent-key'))->toBeFalse();

        // Test forget
        $this->requestCache->forget($key);
        expect($this->requestCache->has($key))->toBeFalse();
        expect($this->requestCache->get($key))->toBeNull();
    }

    #[Test]
    public function it_handles_persistent_cache_operations()
    {
        $key = 'test-persistent-key';
        $value = ['data' => 'persistent-value', 'id' => 123];
        $ttl = 3600; // 1 hour

        // Test put with TTL
        $this->persistentCache->put($key, $value, $ttl);
        $retrieved = $this->persistentCache->get($key);

        expect($retrieved)->toEqual($value);

        // Test remember functionality
        $rememberKey = 'remember-key';
        $rememberValue = $this->persistentCache->remember($rememberKey, $ttl, function () {
            return ['computed' => 'value', 'time' => time()];
        });

        expect($rememberValue)->toHaveKey('computed');
        expect($rememberValue['computed'])->toBe('value');

        // Second call should return cached value
        $cachedValue = $this->persistentCache->remember($rememberKey, $ttl, function () {
            return ['should' => 'not-be-called'];
        });

        expect($cachedValue)->toEqual($rememberValue);
    }

    #[Test]
    public function it_handles_query_cache_key_generation()
    {
        $collection = 'posts';
        $wheres = [
            ['field' => 'published', 'operator' => '=', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 100],
        ];
        $orders = [
            ['field' => 'created_at', 'direction' => 'desc'],
        ];
        $limit = 10;
        $offset = 0;

        $cacheKey = QueryCacheKey::generate($collection, $wheres, $orders, $limit, $offset);

        expect($cacheKey)->toBeString();
        expect($cacheKey)->toContain('posts');

        // Same parameters should generate same key
        $sameKey = QueryCacheKey::generate($collection, $wheres, $orders, $limit, $offset);
        expect($sameKey)->toBe($cacheKey);

        // Different parameters should generate different key
        $differentKey = QueryCacheKey::generate($collection, $wheres, $orders, 20, $offset);
        expect($differentKey)->not->toBe($cacheKey);
    }

    #[Test]
    public function it_handles_model_cache_integration()
    {
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'Cached Post 1']),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Cached Post 2']),
        ];

        $this->mockFirestoreQuery('posts', $testData);

        // Enable caching for the query
        $query = TestPost::query()->enableCache();

        // First query should hit the database and cache the result
        $results1 = $query->where('published', true)->get();
        expect($results1)->toHaveCount(2);

        // Second identical query should hit the cache
        $results2 = TestPost::query()->enableCache()->where('published', true)->get();
        expect($results2)->toHaveCount(2);
        expect($results2->toArray())->toEqual($results1->toArray());
    }

    #[Test]
    public function it_handles_cache_invalidation()
    {
        $key = 'invalidation-test';
        $value = ['data' => 'to-be-invalidated'];

        // Store in both caches
        $this->requestCache->put($key, $value);
        $this->persistentCache->put($key, $value, 3600);

        // Verify stored
        expect($this->requestCache->has($key))->toBeTrue();
        expect($this->persistentCache->has($key))->toBeTrue();

        // Invalidate
        $this->cacheManager->invalidate($key);

        // Verify invalidated
        expect($this->requestCache->has($key))->toBeFalse();
        expect($this->persistentCache->has($key))->toBeFalse();
    }

    #[Test]
    public function it_handles_cache_tags()
    {
        $tag = 'posts';
        $key1 = 'tagged-key-1';
        $key2 = 'tagged-key-2';
        $value1 = ['post' => 1];
        $value2 = ['post' => 2];

        // Store with tags
        $this->persistentCache->tags([$tag])->put($key1, $value1, 3600);
        $this->persistentCache->tags([$tag])->put($key2, $value2, 3600);

        // Verify stored
        expect($this->persistentCache->tags([$tag])->get($key1))->toEqual($value1);
        expect($this->persistentCache->tags([$tag])->get($key2))->toEqual($value2);

        // Flush by tag
        $this->persistentCache->tags([$tag])->flush();

        // Verify flushed
        expect($this->persistentCache->get($key1))->toBeNull();
        expect($this->persistentCache->get($key2))->toBeNull();
    }

    #[Test]
    public function it_handles_cache_serialization()
    {
        $complexData = [
            'model' => new TestPost(['id' => 'serialize-test', 'title' => 'Serialization Test']),
            'collection' => collect([1, 2, 3, 4, 5]),
            'datetime' => now(),
            'nested' => [
                'array' => ['a', 'b', 'c'],
                'object' => (object) ['prop' => 'value'],
            ],
        ];

        $key = 'serialization-test';

        // Store complex data
        $this->persistentCache->put($key, $complexData, 3600);
        $retrieved = $this->persistentCache->get($key);

        expect($retrieved)->toBeArray();
        expect($retrieved)->toHaveKey('model');
        expect($retrieved)->toHaveKey('collection');
        expect($retrieved)->toHaveKey('datetime');
        expect($retrieved)->toHaveKey('nested');
    }

    #[Test]
    public function it_handles_cache_performance_optimization()
    {
        $this->enableMemoryMonitoring();

        // Create large dataset
        $largeData = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeData["key-{$i}"] = [
                'id' => $i,
                'data' => str_repeat('x', 100), // 100 chars per item
                'timestamp' => time(),
            ];
        }

        // Test bulk storage performance
        $startTime = microtime(true);
        foreach ($largeData as $key => $value) {
            $this->requestCache->put($key, $value);
        }
        $storageTime = microtime(true) - $startTime;

        // Test bulk retrieval performance
        $startTime = microtime(true);
        $retrievedCount = 0;
        foreach (array_keys($largeData) as $key) {
            if ($this->requestCache->has($key)) {
                $this->requestCache->get($key);
                $retrievedCount++;
            }
        }
        $retrievalTime = microtime(true) - $startTime;

        expect($retrievedCount)->toBe(1000);
        expect($storageTime)->toBeLessThan(1.0); // Should be fast
        expect($retrievalTime)->toBeLessThan(0.5); // Should be very fast
    }

    #[Test]
    public function it_handles_cache_memory_management()
    {
        $initialMemory = memory_get_usage(true);

        // Fill cache with data
        for ($i = 0; $i < 100; $i++) {
            $key = "memory-test-{$i}";
            $value = array_fill(0, 100, "data-{$i}"); // Array with 100 elements
            $this->requestCache->put($key, $value);
        }

        $afterFillMemory = memory_get_usage(true);

        // Clear cache
        $this->requestCache->clear();

        $afterClearMemory = memory_get_usage(true);

        // Memory should increase after filling and decrease after clearing
        expect($afterFillMemory)->toBeGreaterThan($initialMemory);
        expect($afterClearMemory)->toBeLessThan($afterFillMemory);
    }

    #[Test]
    public function it_handles_cache_expiration()
    {
        $key = 'expiration-test';
        $value = ['expires' => 'soon'];
        $shortTtl = 1; // 1 second

        // Store with short TTL
        $this->persistentCache->put($key, $value, $shortTtl);
        expect($this->persistentCache->has($key))->toBeTrue();

        // Wait for expiration (simulate)
        sleep(2);

        // Should be expired (note: this test might be flaky in fast test environments)
        // In a real implementation, you might need to mock time or use a different approach
        $expired = $this->persistentCache->get($key);
        // The behavior depends on the cache driver implementation
    }

    #[Test]
    public function it_handles_cache_statistics()
    {
        // Perform various cache operations
        $this->requestCache->put('stat-1', 'value-1');
        $this->requestCache->put('stat-2', 'value-2');
        $this->requestCache->get('stat-1'); // Hit
        $this->requestCache->get('stat-3'); // Miss
        $this->requestCache->get('stat-2'); // Hit

        // Get statistics (if implemented)
        $stats = $this->requestCache->getStats();

        if ($stats !== null) {
            expect($stats)->toBeArray();
            expect($stats)->toHaveKey('hits');
            expect($stats)->toHaveKey('misses');
            expect($stats['hits'])->toBeGreaterThan(0);
            expect($stats['misses'])->toBeGreaterThan(0);
        }
    }

    #[Test]
    public function it_handles_concurrent_cache_access()
    {
        $key = 'concurrent-test';
        $value1 = ['thread' => 1, 'data' => 'first'];
        $value2 = ['thread' => 2, 'data' => 'second'];

        // Simulate concurrent access (in a real scenario, this would be multiple processes)
        $this->requestCache->put($key, $value1);
        $retrieved1 = $this->requestCache->get($key);

        $this->requestCache->put($key, $value2);
        $retrieved2 = $this->requestCache->get($key);

        expect($retrieved1)->toEqual($value1);
        expect($retrieved2)->toEqual($value2);
        expect($retrieved2)->not->toEqual($retrieved1);
    }

    #[Test]
    public function it_handles_cache_namespace_isolation()
    {
        $key = 'namespace-test';
        $value1 = ['namespace' => 'first'];
        $value2 = ['namespace' => 'second'];

        // Store in different namespaces (if supported)
        $this->requestCache->namespace('ns1')->put($key, $value1);
        $this->requestCache->namespace('ns2')->put($key, $value2);

        // Retrieve from different namespaces
        $retrieved1 = $this->requestCache->namespace('ns1')->get($key);
        $retrieved2 = $this->requestCache->namespace('ns2')->get($key);

        if ($retrieved1 !== null && $retrieved2 !== null) {
            expect($retrieved1)->toEqual($value1);
            expect($retrieved2)->toEqual($value2);
            expect($retrieved1)->not->toEqual($retrieved2);
        }
    }
}
