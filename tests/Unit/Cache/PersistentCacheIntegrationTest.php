<?php

use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Cache\PersistentCache;
use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Firestore\FirestoreQueryBuilder;
use JTD\FirebaseModels\Firestore\FirestoreDatabase;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;

describe('Persistent Cache Integration', function () {
    beforeEach(function () {
        // Initialize Firestore mock
        FirestoreMock::initialize();
        
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
        
        // Clear all caches
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
        
        // Configure cache manager
        CacheManager::configure([
            'request_cache_enabled' => true,
            'persistent_cache_enabled' => true,
            'default_ttl' => 3600,
            'default_store' => 'array',
            'auto_promote' => true,
        ]);
        
        // Create a mock database and query builder
        $mockFirestore = app(\Kreait\Firebase\Contract\Firestore::class);
        $this->database = new FirestoreDatabase($mockFirestore);
        $this->builder = new FirestoreQueryBuilder($this->database, 'test_collection');
    });

    afterEach(function () {
        RequestCache::clear();
        try {
            PersistentCache::flush('array');
        } catch (\Exception $e) {
            // Ignore cache flush errors in tests
        }
        FirestoreMock::clear();
    });

    describe('Cache Hierarchy with FirestoreQueryBuilder', function () {
        it('stores query results in both request and persistent cache', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Test 1', 'active' => true]);
            FirestoreMock::createDocument('test_collection', 'doc2', ['name' => 'Test 2', 'active' => true]);

            // First call should execute query and cache in both layers
            $result1 = $this->builder->get();
            
            expect($result1)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($result1->count())->toBe(2);
            
            // Check that both caches have the result
            expect($this->builder->isCached('get'))->toBeTrue();
            
            $stats = CacheManager::getStats();
            expect($stats['request_cache']['sets'])->toBe(1);
            expect($stats['persistent_cache']['sets'])->toBe(1);
        });

        it('retrieves from request cache first', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Test 1']);

            // First call caches the result
            $result1 = $this->builder->get();
            
            // Clear only request cache stats to track next call
            RequestCache::resetStats();
            
            // Second call should hit request cache
            $result2 = $this->builder->get();
            
            expect($result2)->toBe($result1);
            
            $requestStats = RequestCache::getStats();
            expect($requestStats['hits'])->toBe(1);
            expect($requestStats['misses'])->toBe(0);
        });

        it('falls back to persistent cache when request cache is cleared', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Test 1']);

            // First call caches the result in both layers
            $result1 = $this->builder->get();
            
            // Clear only request cache (simulating new request)
            RequestCache::clear();
            RequestCache::resetStats();
            
            // Second call should hit persistent cache and auto-promote
            $result2 = $this->builder->get();
            
            expect($result2->count())->toBe($result1->count());
            expect($result2->first()->name)->toBe('Test 1');
            
            // Should have promoted to request cache (check if get is cached)
            expect($this->builder->isCached('get'))->toBeTrue();
            
            $persistentStats = PersistentCache::getStats();
            expect($persistentStats['hits'])->toBe(1);
        });

        it('supports TTL configuration for persistent cache', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'TTL Test']);

            // Use custom TTL
            $result = $this->builder->cacheTtl(7200)->get();
            
            expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($result->count())->toBe(1);
            
            // Verify it's cached
            expect($this->builder->isCached('get'))->toBeTrue();
        });

        it('supports cache store configuration', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Store Test']);

            // Use specific cache store
            $result = $this->builder->cacheStore('array')->get();
            
            expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($result->count())->toBe(1);
            
            // Verify it's cached
            expect($this->builder->isCached('get'))->toBeTrue();
        });

        it('supports cache tags for intelligent invalidation', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Tagged Test']);

            // Use cache tags
            $result = $this->builder->cacheTags(['users', 'active'])->get();
            
            expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($result->count())->toBe(1);
            
            // Verify cache tags are set
            $tags = $this->builder->getCacheTags();
            expect($tags)->toContain('users');
            expect($tags)->toContain('active');
        });

        it('can disable persistent cache for specific queries', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'No Persistent']);

            // Reset stats to track this specific query
            RequestCache::resetStats();
            PersistentCache::resetStats();

            // Disable persistent cache
            $result = $this->builder->withoutPersistentCache()->get();

            expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);

            $requestStats = RequestCache::getStats();
            $persistentStats = PersistentCache::getStats();

            // Should only be in request cache
            expect($requestStats['sets'])->toBe(1);
            expect($persistentStats['sets'])->toBe(0);
        });

        it('supports remember and rememberForever methods', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Remember Test']);

            // Test remember with TTL
            $result1 = $this->builder->remember(1800, 'get');
            expect($result1)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($result1->count())->toBe(1);

            // Test rememberForever with get method
            $result2 = $this->builder->rememberForever('get');
            expect($result2)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($result2->count())->toBe(1);
        });
    });

    describe('Cache Performance Benefits', function () {
        it('demonstrates significant performance improvement', function () {
            // Mock a larger dataset
            for ($i = 1; $i <= 50; $i++) {
                FirestoreMock::createDocument('test_collection', "doc{$i}", ['name' => "Test {$i}", 'value' => $i]);
            }

            // Time the first query (uncached)
            $start1 = microtime(true);
            $result1 = $this->builder->get();
            $time1 = microtime(true) - $start1;

            // Clear request cache to test persistent cache performance
            RequestCache::clear();

            // Time the second query (persistent cache hit)
            $start2 = microtime(true);
            $result2 = $this->builder->get();
            $time2 = microtime(true) - $start2;

            // Time the third query (request cache hit after auto-promotion)
            $start3 = microtime(true);
            $result3 = $this->builder->get();
            $time3 = microtime(true) - $start3;

            expect($result1->count())->toBe(50);
            expect($result2->count())->toBe(50);
            expect($result3->count())->toBe(50);

            // Persistent cache should be faster than original query
            expect($time2)->toBeLessThan($time1 * 0.8);
            
            // Request cache should be fastest
            expect($time3)->toBeLessThan($time2 * 0.5);
            
            $stats = CacheManager::getStats();
            expect($stats['combined']['hits'])->toBeGreaterThan(0);
            expect($stats['combined']['hit_rate'])->toBeGreaterThan(0);
        });
    });

    describe('Cache Invalidation', function () {
        it('can flush cache with tags', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Flush Test']);

            // Cache with tags
            $this->builder->cacheTags(['test_tag'])->get();
            
            expect($this->builder->isCached('get'))->toBeTrue();
            
            // Flush cache with tags
            $this->builder->flushCache();
            
            expect($this->builder->isCached('get'))->toBeFalse();
        });

        it('handles cache errors gracefully', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Error Test']);

            // Disable persistent cache to simulate error
            PersistentCache::disable();
            
            // Should still work with request cache only
            $result = $this->builder->get();
            
            expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($result->count())->toBe(1);
            
            PersistentCache::enable();
        });
    });

    describe('Laravel Cache Integration', function () {
        it('integrates seamlessly with Laravel Cache facade', function () {
            // Mock some test data
            FirestoreMock::createDocument('test_collection', 'doc1', ['name' => 'Laravel Integration']);

            // Cache the result
            $result = $this->builder->get();
            
            // Verify the result is cached
            expect($this->builder->isCached('get'))->toBeTrue();

            // Verify cache statistics show Laravel cache integration
            $stats = CacheManager::getStats();
            expect($stats['persistent_cache']['sets'])->toBeGreaterThan(0);
        });
    });
});
