<?php

use JTD\FirebaseModels\Cache\Concerns\Cacheable;
use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Cache\PersistentCache;
use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Cache\QueryCacheKey;
use Illuminate\Support\Collection;

// Test class that uses the Cacheable trait
class TestCacheableQuery
{
    use Cacheable;

    protected string $collection = 'test_collection';
    protected array $wheres = [];
    protected ?int $limitValue = null;
    protected array $selects = ['*'];

    public function __construct(string $collection = 'test_collection')
    {
        $this->collection = $collection;
    }

    // Mock query execution methods
    protected function parentGet(array $columns = ['*']): Collection
    {
        return new Collection([
            (object) ['id' => '1', 'name' => 'Test 1'],
            (object) ['id' => '2', 'name' => 'Test 2'],
        ]);
    }

    protected function parentFirst(array $columns = ['*']): ?object
    {
        return (object) ['id' => '1', 'name' => 'First Item'];
    }

    protected function parentCount(string $columns = '*'): int
    {
        return 42;
    }

    protected function parentExists(): bool
    {
        return true;
    }

    // Public methods that use caching
    public function get(array $columns = ['*']): Collection
    {
        return $this->getCached('get', [$columns]);
    }

    public function first(array $columns = ['*']): ?object
    {
        return $this->getCached('first', [$columns]);
    }

    public function count(string $columns = '*'): int
    {
        return $this->getCached('count', [$columns]);
    }

    public function exists(): bool
    {
        return $this->getCached('exists');
    }

    // Helper methods for testing
    public function where(string $field, string $operator, mixed $value): static
    {
        $this->wheres[] = compact('field', 'operator', 'value');
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function getCollection(): string
    {
        return $this->collection;
    }

    // Make protected methods public for testing
    public function shouldCache(): bool
    {
        return $this->cacheEnabled && RequestCache::isEnabled();
    }
}

describe('Cacheable Trait', function () {
    beforeEach(function () {
        // Use array cache driver for testing
        config([
            'cache.default' => 'array',
            'cache.stores.array' => [
                'driver' => 'array',
                'serialize' => false,
            ],
            'firebase-models.cache.store' => 'array',
        ]);

        // Set default store to array for testing
        PersistentCache::setDefaultStore('array');

        RequestCache::clear();
        RequestCache::resetStats();
        RequestCache::enable();

        try {
            PersistentCache::flush('array');
        } catch (\Exception $e) {
            // Ignore cache flush errors in tests
        }
        PersistentCache::resetStats();
        PersistentCache::enable();

        // Configure cache manager to use array store
        CacheManager::configure([
            'request_cache_enabled' => true,
            'persistent_cache_enabled' => true,
            'default_ttl' => 3600,
            'default_store' => 'array',
            'auto_promote' => true,
        ]);

        $this->query = new TestCacheableQuery();
    });

    afterEach(function () {
        RequestCache::clear();
        RequestCache::resetStats();
        try {
            PersistentCache::flush('array');
        } catch (\Exception $e) {
            // Ignore cache flush errors in tests
        }
        PersistentCache::resetStats();
    });

    describe('Basic Caching Functionality', function () {
        it('caches query results', function () {
            // First call should execute query and cache result
            $result1 = $this->query->get();
            $stats1 = RequestCache::getStats();
            
            expect($result1)->toBeInstanceOf(Collection::class);
            expect($result1->count())->toBe(2);
            expect($stats1['sets'])->toBe(1);
            expect($stats1['hits'])->toBe(0);
            expect($stats1['misses'])->toBe(1);

            // Second call should return cached result
            $result2 = $this->query->get();
            $stats2 = RequestCache::getStats();
            
            expect($result2)->toBe($result1);
            expect($stats2['hits'])->toBe(1);
            expect($stats2['misses'])->toBe(1);
        });

        it('caches different methods separately', function () {
            $getResult = $this->query->get();
            $firstResult = $this->query->first();
            $countResult = $this->query->count();
            $existsResult = $this->query->exists();

            $stats = RequestCache::getStats();
            
            expect($getResult)->toBeInstanceOf(Collection::class);
            expect($firstResult)->toBeObject();
            expect($countResult)->toBe(42);
            expect($existsResult)->toBeTrue();
            
            // Should have 4 cache sets (one for each method)
            expect($stats['sets'])->toBe(4);
        });

        it('generates different cache keys for different parameters', function () {
            $result1 = $this->query->get(['id', 'name']);
            $result2 = $this->query->get(['id']);
            
            $stats = RequestCache::getStats();
            
            // Should have 2 cache sets (different column parameters)
            expect($stats['sets'])->toBe(2);
            expect($stats['hits'])->toBe(0);
        });
    });

    describe('Cache Control Methods', function () {
        it('can disable caching for specific queries', function () {
            $result1 = $this->query->withoutCache()->get();
            $result2 = $this->query->withoutCache()->get();
            
            $stats = RequestCache::getStats();
            
            // No caching should occur
            expect($stats['sets'])->toBe(0);
            expect($stats['hits'])->toBe(0);
            expect($stats['misses'])->toBe(0);
        });

        it('can re-enable caching after disabling', function () {
            $this->query->withoutCache();
            expect($this->query->shouldCache())->toBeFalse();
            
            $this->query->withCache();
            expect($this->query->shouldCache())->toBeTrue();
            
            $result = $this->query->get();
            $stats = RequestCache::getStats();
            
            expect($stats['sets'])->toBe(1);
        });

        it('can set custom cache keys', function () {
            $result = $this->query->cacheKey('custom_key')->get();
            
            expect(RequestCache::has('custom_key'))->toBeTrue();
            expect(RequestCache::get('custom_key'))->toBe($result);
        });

        it('can add cache tags', function () {
            $this->query->cacheTags(['users', 'active']);
            
            $tags = $this->query->getCacheTags();
            expect($tags)->toBe(['users', 'active']);
            
            // Add more tags
            $this->query->cacheTags(['recent']);
            $tags = $this->query->getCacheTags();
            expect($tags)->toBe(['users', 'active', 'recent']);
        });
    });

    describe('Cache Invalidation', function () {
        it('can clear cache for specific queries', function () {
            // Cache some results
            $this->query->get();
            $this->query->first();
            
            expect($this->query->isCached('get'))->toBeTrue();
            expect($this->query->isCached('first'))->toBeTrue();
            
            // Clear cache
            $this->query->clearCache();
            
            expect($this->query->isCached('get'))->toBeFalse();
            expect($this->query->isCached('first'))->toBeFalse();
        });

        it('can invalidate specific operations', function () {
            // Cache multiple operations
            $this->query->get();
            $this->query->first();
            $this->query->count();
            
            expect($this->query->isCached('get'))->toBeTrue();
            expect($this->query->isCached('first'))->toBeTrue();
            expect($this->query->isCached('count'))->toBeTrue();
            
            // Invalidate only get and first
            $this->query->invalidateCache(['get', 'first']);
            
            expect($this->query->isCached('get'))->toBeFalse();
            expect($this->query->isCached('first'))->toBeFalse();
            expect($this->query->isCached('count'))->toBeTrue();
        });

        it('can flush all cache for collection', function () {
            $query1 = new TestCacheableQuery('collection1');
            $query2 = new TestCacheableQuery('collection2');
            
            // Cache results for both collections
            $query1->get();
            $query2->get();
            
            expect($query1->isCached('get'))->toBeTrue();
            expect($query2->isCached('get'))->toBeTrue();
            
            // Flush only collection1
            $query1->flushCache();
            
            expect($query1->isCached('get'))->toBeFalse();
            expect($query2->isCached('get'))->toBeTrue();
        });
    });

    describe('Cache Inspection', function () {
        it('can check if query is cached', function () {
            expect($this->query->isCached('get'))->toBeFalse();

            $result = $this->query->get();

            expect($this->query->isCached('get'))->toBeTrue();
            expect($this->query->isCached('first'))->toBeFalse();
        });

        it('can warm cache by executing query', function () {
            expect($this->query->isCached('get'))->toBeFalse();
            
            $result = $this->query->warmCache('get');
            
            expect($this->query->isCached('get'))->toBeTrue();
            expect($result)->toBeInstanceOf(Collection::class);
        });

        it('provides cache statistics', function () {
            $this->query->get();
            $this->query->get(); // Cache hit
            
            $stats = $this->query->getCacheStats();
            
            expect($stats['hits'])->toBe(1);
            expect($stats['misses'])->toBe(1);
            expect($stats['sets'])->toBe(1);
            expect($stats['hit_rate'])->toBe(50.0);
        });

        it('provides debug information', function () {
            $this->query->cacheTags(['test'])->cacheKey('debug_key');
            
            $debugInfo = $this->query->getCacheDebugInfo();
            
            expect($debugInfo)->toHaveKey('cache_enabled');
            expect($debugInfo)->toHaveKey('should_cache');
            expect($debugInfo)->toHaveKey('custom_cache_key');
            expect($debugInfo)->toHaveKey('cache_tags');
            expect($debugInfo)->toHaveKey('collection');
            expect($debugInfo)->toHaveKey('cache_key_get');
            expect($debugInfo)->toHaveKey('is_cached_get');
            expect($debugInfo)->toHaveKey('cache_stats');
            
            expect($debugInfo['cache_enabled'])->toBeTrue();
            expect($debugInfo['custom_cache_key'])->toBe('debug_key');
            expect($debugInfo['cache_tags'])->toBe(['test']);
            expect($debugInfo['collection'])->toBe('test_collection');
        });
    });

    describe('Remember Methods', function () {
        it('supports remember method for future compatibility', function () {
            $result = $this->query->remember(3600, 'get');
            
            expect($result)->toBeInstanceOf(Collection::class);
            expect($this->query->isCached('get'))->toBeTrue();
        });

        it('supports rememberForever method for future compatibility', function () {
            $result = $this->query->rememberForever('get');
            
            expect($result)->toBeInstanceOf(Collection::class);
            expect($this->query->isCached('get'))->toBeTrue();
        });
    });

    describe('Cache Behavior with Query Changes', function () {
        it('generates different cache keys for different query conditions', function () {
            $query1 = $this->query->where('status', '=', 'active');
            $query2 = (new TestCacheableQuery())->where('status', '=', 'inactive');
            
            $result1 = $query1->get();
            $result2 = $query2->get();
            
            $stats = RequestCache::getStats();
            
            // Should have 2 cache sets (different where conditions)
            expect($stats['sets'])->toBe(2);
            expect($stats['hits'])->toBe(0);
        });

        it('respects global cache enable/disable state', function () {
            RequestCache::disable();
            
            expect($this->query->shouldCache())->toBeFalse();
            
            $result = $this->query->get();
            $stats = RequestCache::getStats();
            
            expect($stats['sets'])->toBe(0);
            
            RequestCache::enable();
            
            expect($this->query->shouldCache())->toBeTrue();
        });
    });
});
