<?php

use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Cache\PersistentCache;

describe('CacheManager', function () {
    beforeEach(function () {
        // Clear all caches and reset stats
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
        
        // Reset cache manager configuration
        CacheManager::configure([
            'request_cache_enabled' => true,
            'persistent_cache_enabled' => true,
            'default_ttl' => 3600,
            'default_store' => null,
            'auto_promote' => true,
        ]);
        
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
    });

    afterEach(function () {
        RequestCache::clear();
        try {
            PersistentCache::flush('array');
        } catch (\Exception $e) {
            // Ignore cache flush errors in tests
        }
    });

    describe('Cache Hierarchy', function () {
        it('stores in both request and persistent cache', function () {
            $result = CacheManager::put('test_key', 'test_value', 60);
            expect($result)->toBeTrue();
            
            // Should be in both caches
            expect(RequestCache::has('test_key'))->toBeTrue();
            expect(PersistentCache::has('test_key'))->toBeTrue();
            
            expect(RequestCache::get('test_key'))->toBe('test_value');
            expect(PersistentCache::get('test_key'))->toBe('test_value');
        });

        it('retrieves from request cache first', function () {
            // Store in persistent cache only
            PersistentCache::put('persistent_key', 'persistent_value', 60);
            
            // Store different value in request cache
            RequestCache::put('persistent_key', 'request_value');
            
            // Should return request cache value
            $value = CacheManager::get('persistent_key');
            expect($value)->toBe('request_value');
        });

        it('falls back to persistent cache when request cache misses', function () {
            // Store only in persistent cache
            PersistentCache::put('fallback_key', 'fallback_value', 60);
            
            // Should retrieve from persistent cache
            $value = CacheManager::get('fallback_key');
            expect($value)->toBe('fallback_value');
        });

        it('auto-promotes persistent cache hits to request cache', function () {
            // Store only in persistent cache
            PersistentCache::put('promote_key', 'promote_value', 60);
            
            // First retrieval should promote to request cache
            $value = CacheManager::get('promote_key');
            expect($value)->toBe('promote_value');
            
            // Should now be in request cache
            expect(RequestCache::has('promote_key'))->toBeTrue();
            expect(RequestCache::get('promote_key'))->toBe('promote_value');
        });

        it('can disable auto-promotion', function () {
            CacheManager::disableAutoPromotion();
            
            // Store only in persistent cache
            PersistentCache::put('no_promote_key', 'no_promote_value', 60);
            
            // Retrieval should not promote to request cache
            $value = CacheManager::get('no_promote_key');
            expect($value)->toBe('no_promote_value');
            
            // Should not be in request cache
            expect(RequestCache::has('no_promote_key'))->toBeFalse();
        });
    });

    describe('Remember Functionality', function () {
        it('can remember values using callback', function () {
            $callCount = 0;
            
            $callback = function () use (&$callCount) {
                $callCount++;
                return 'computed_value';
            };
            
            // First call should execute callback
            $result1 = CacheManager::remember('remember_key', $callback, 60);
            expect($result1)->toBe('computed_value');
            expect($callCount)->toBe(1);
            
            // Should be in both caches
            expect(RequestCache::has('remember_key'))->toBeTrue();
            expect(PersistentCache::has('remember_key'))->toBeTrue();
            
            // Second call should return cached value
            $result2 = CacheManager::remember('remember_key', $callback, 60);
            expect($result2)->toBe('computed_value');
            expect($callCount)->toBe(1); // Callback not called again
        });

        it('can remember values forever', function () {
            $callCount = 0;
            
            $callback = function () use (&$callCount) {
                $callCount++;
                return 'forever_value';
            };
            
            $result = CacheManager::rememberForever('forever_key', $callback);
            expect($result)->toBe('forever_value');
            expect($callCount)->toBe(1);
            
            // Should be in both caches
            expect(RequestCache::has('forever_key'))->toBeTrue();
            expect(PersistentCache::has('forever_key'))->toBeTrue();
        });
    });

    describe('Cache Control', function () {
        it('can check if item exists in hierarchy', function () {
            expect(CacheManager::has('non_existent'))->toBeFalse();
            
            // Store in request cache only
            RequestCache::put('request_only', 'value');
            expect(CacheManager::has('request_only'))->toBeTrue();
            
            // Store in persistent cache only
            PersistentCache::put('persistent_only', 'value', 60);
            expect(CacheManager::has('persistent_only'))->toBeTrue();
        });

        it('can forget from both caches', function () {
            CacheManager::put('forget_key', 'forget_value', 60);
            
            expect(RequestCache::has('forget_key'))->toBeTrue();
            expect(PersistentCache::has('forget_key'))->toBeTrue();
            
            $result = CacheManager::forget('forget_key');
            expect($result)->toBeTrue();
            
            expect(RequestCache::has('forget_key'))->toBeFalse();
            expect(PersistentCache::has('forget_key'))->toBeFalse();
        });

        it('can forget multiple items', function () {
            CacheManager::put('key1', 'value1', 60);
            CacheManager::put('key2', 'value2', 60);
            
            $result = CacheManager::forgetMany(['key1', 'key2']);
            expect($result)->toBeTrue();
            
            expect(CacheManager::has('key1'))->toBeFalse();
            expect(CacheManager::has('key2'))->toBeFalse();
        });

        it('can flush all caches', function () {
            CacheManager::put('flush_key1', 'value1', 60);
            CacheManager::put('flush_key2', 'value2', 60);
            
            $result = CacheManager::flush();
            expect($result)->toBeTrue();
            
            expect(CacheManager::has('flush_key1'))->toBeFalse();
            expect(CacheManager::has('flush_key2'))->toBeFalse();
        });
    });

    describe('Cache Statistics', function () {
        it('provides combined statistics', function () {
            // Generate some cache activity
            CacheManager::put('stats_key', 'value', 60);
            CacheManager::get('stats_key'); // Hit
            CacheManager::get('non_existent'); // Miss
            
            $stats = CacheManager::getStats();
            
            expect($stats)->toHaveKey('request_cache');
            expect($stats)->toHaveKey('persistent_cache');
            expect($stats)->toHaveKey('combined');
            
            expect($stats['combined']['hits'])->toBeGreaterThan(0);
            expect($stats['combined']['misses'])->toBeGreaterThan(0);
            expect($stats['combined']['sets'])->toBeGreaterThan(0);
            expect($stats['combined']['hit_rate'])->toBeFloat();
        });
    });

    describe('Configuration', function () {
        it('can enable and disable request cache', function () {
            CacheManager::disableRequestCache();
            
            CacheManager::put('request_disabled', 'value', 60);
            
            // Should only be in persistent cache
            expect(RequestCache::has('request_disabled'))->toBeFalse();
            expect(PersistentCache::has('request_disabled'))->toBeTrue();
            
            CacheManager::enableRequestCache();
            
            CacheManager::put('request_enabled', 'value', 60);
            
            // Should be in both caches
            expect(RequestCache::has('request_enabled'))->toBeTrue();
            expect(PersistentCache::has('request_enabled'))->toBeTrue();
        });

        it('can enable and disable persistent cache', function () {
            CacheManager::disablePersistentCache();
            
            CacheManager::put('persistent_disabled', 'value', 60);
            
            // Should only be in request cache
            expect(RequestCache::has('persistent_disabled'))->toBeTrue();
            expect(PersistentCache::has('persistent_disabled'))->toBeFalse();
            
            CacheManager::enablePersistentCache();
            
            CacheManager::put('persistent_enabled', 'value', 60);
            
            // Should be in both caches
            expect(RequestCache::has('persistent_enabled'))->toBeTrue();
            expect(PersistentCache::has('persistent_enabled'))->toBeTrue();
        });

        it('can get and set configuration', function () {
            $config = CacheManager::getConfig();
            expect($config)->toBeArray();
            expect($config)->toHaveKey('request_cache_enabled');
            expect($config)->toHaveKey('persistent_cache_enabled');
            
            CacheManager::configure(['default_ttl' => 7200]);
            
            $newConfig = CacheManager::getConfig();
            expect($newConfig['default_ttl'])->toBe(7200);
        });
    });

    describe('Edge Cases', function () {
        it('handles null values correctly', function () {
            $result = CacheManager::put('null_key', null, 60);
            expect($result)->toBeTrue();
            
            $value = CacheManager::get('null_key', 'default');
            expect($value)->toBeNull();
        });

        it('handles callback returning null', function () {
            $callback = function () {
                return null;
            };
            
            $result = CacheManager::remember('null_callback', $callback, 60);
            expect($result)->toBeNull();
        });

        it('works when only one cache type is enabled', function () {
            CacheManager::disablePersistentCache();
            
            $result = CacheManager::put('request_only', 'value', 60);
            expect($result)->toBeTrue();
            
            $value = CacheManager::get('request_only');
            expect($value)->toBe('value');
            
            CacheManager::enablePersistentCache();
            CacheManager::disableRequestCache();
            
            $result = CacheManager::put('persistent_only', 'value', 60);
            expect($result)->toBeTrue();
            
            $value = CacheManager::get('persistent_only');
            expect($value)->toBe('value');
        });
    });
});
