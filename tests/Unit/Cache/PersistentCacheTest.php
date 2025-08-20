<?php

use JTD\FirebaseModels\Cache\PersistentCache;
use Illuminate\Support\Facades\Cache;

describe('PersistentCache', function () {
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

        // Clear cache and reset stats before each test
        PersistentCache::resetStats();
        PersistentCache::enable();

        // Clear cache using array store
        try {
            PersistentCache::flush('array');
        } catch (\Exception $e) {
            // Ignore cache flush errors in tests
        }
    });

    afterEach(function () {
        // Clean up after each test
        try {
            PersistentCache::flush('array');
        } catch (\Exception $e) {
            // Ignore cache flush errors in tests
        }
        PersistentCache::resetStats();
    });

    describe('Basic Cache Operations', function () {
        it('can store and retrieve values', function () {
            // Debug: Check what cache driver is being used
            $cacheDriver = config('cache.default');
            expect($cacheDriver)->toBe('array');

            $result = PersistentCache::put('test_key', 'test_value', 60);
            expect($result)->toBeTrue();

            $value = PersistentCache::get('test_key');
            expect($value)->toBe('test_value');
            expect(PersistentCache::has('test_key'))->toBeTrue();
        });

        it('returns default for non-existent keys', function () {
            expect(PersistentCache::get('non_existent'))->toBeNull();
            expect(PersistentCache::get('non_existent', 'default'))->toBe('default');
            expect(PersistentCache::has('non_existent'))->toBeFalse();
        });

        it('can store complex data types', function () {
            $data = [
                'array' => [1, 2, 3],
                'object' => (object) ['prop' => 'value'],
                'null' => null,
                'boolean' => true,
                'number' => 42.5,
            ];

            PersistentCache::put('complex_data', $data, 60);
            
            $retrieved = PersistentCache::get('complex_data');
            expect($retrieved)->toEqual($data);
        });

        it('can forget cached values', function () {
            PersistentCache::put('forget_me', 'value', 60);
            expect(PersistentCache::has('forget_me'))->toBeTrue();
            
            $result = PersistentCache::forget('forget_me');
            expect($result)->toBeTrue();
            expect(PersistentCache::has('forget_me'))->toBeFalse();
            expect(PersistentCache::get('forget_me'))->toBeNull();
        });

        it('can store values forever', function () {
            $result = PersistentCache::forever('forever_key', 'forever_value');
            expect($result)->toBeTrue();
            
            $value = PersistentCache::get('forever_key');
            expect($value)->toBe('forever_value');
        });
    });

    describe('Cache Statistics', function () {
        it('tracks cache hits and misses', function () {
            // Initial stats should be zero
            $stats = PersistentCache::getStats();
            expect($stats['hits'])->toBe(0);
            expect($stats['misses'])->toBe(0);

            // Miss on non-existent key
            PersistentCache::get('non_existent');
            $stats = PersistentCache::getStats();
            expect($stats['misses'])->toBe(1);
            expect($stats['hits'])->toBe(0);

            // Store and hit
            PersistentCache::put('test_key', 'test_value', 60);
            PersistentCache::get('test_key');
            $stats = PersistentCache::getStats();
            expect($stats['hits'])->toBe(1);
            expect($stats['misses'])->toBe(1);
        });

        it('tracks sets and deletes', function () {
            PersistentCache::put('key1', 'value1', 60);
            PersistentCache::put('key2', 'value2', 60);
            
            $stats = PersistentCache::getStats();
            expect($stats['sets'])->toBe(2);
            expect($stats['deletes'])->toBe(0);

            PersistentCache::forget('key1');
            
            $stats = PersistentCache::getStats();
            expect($stats['deletes'])->toBe(1);
        });

        it('calculates hit rate correctly', function () {
            // No operations yet
            expect(PersistentCache::getHitRate())->toBe(0.0);

            // 1 miss, 0 hits = 0% hit rate
            PersistentCache::get('non_existent');
            expect(PersistentCache::getHitRate())->toBe(0.0);

            // 1 miss, 1 hit = 50% hit rate
            PersistentCache::put('test_key', 'value', 60);
            PersistentCache::get('test_key');
            expect(PersistentCache::getHitRate())->toBe(50.0);
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
            $result1 = PersistentCache::remember('remember_key', $callback, 60);
            expect($result1)->toBe('computed_value');
            expect($callCount)->toBe(1);
            
            // Second call should return cached value without executing callback
            $result2 = PersistentCache::remember('remember_key', $callback, 60);
            expect($result2)->toBe('computed_value');
            expect($callCount)->toBe(1); // Callback not called again
        });

        it('can remember values forever using callback', function () {
            $callCount = 0;
            
            $callback = function () use (&$callCount) {
                $callCount++;
                return 'forever_value';
            };
            
            // First call should execute callback
            $result1 = PersistentCache::rememberForever('forever_key', $callback);
            expect($result1)->toBe('forever_value');
            expect($callCount)->toBe(1);
            
            // Second call should return cached value
            $result2 = PersistentCache::rememberForever('forever_key', $callback);
            expect($result2)->toBe('forever_value');
            expect($callCount)->toBe(1);
        });
    });

    describe('Multiple Items Operations', function () {
        it('can forget multiple items', function () {
            PersistentCache::put('key1', 'value1', 60);
            PersistentCache::put('key2', 'value2', 60);
            PersistentCache::put('key3', 'value3', 60);
            
            expect(PersistentCache::has('key1'))->toBeTrue();
            expect(PersistentCache::has('key2'))->toBeTrue();
            expect(PersistentCache::has('key3'))->toBeTrue();
            
            $result = PersistentCache::forgetMany(['key1', 'key2']);
            expect($result)->toBeTrue();
            
            expect(PersistentCache::has('key1'))->toBeFalse();
            expect(PersistentCache::has('key2'))->toBeFalse();
            expect(PersistentCache::has('key3'))->toBeTrue();
        });
    });

    describe('Cache Tags', function () {
        it('can store and flush items with tags', function () {
            // Skip if cache driver doesn't support tagging
            if (!method_exists(Cache::store(), 'tags')) {
                $this->markTestSkipped('Cache driver does not support tagging');
            }

            PersistentCache::put('tagged1', 'value1', 60, ['users']);
            PersistentCache::put('tagged2', 'value2', 60, ['users', 'active']);
            PersistentCache::put('untagged', 'value3', 60);
            
            expect(PersistentCache::get('tagged1'))->toBe('value1');
            expect(PersistentCache::get('tagged2'))->toBe('value2');
            expect(PersistentCache::get('untagged'))->toBe('value3');
            
            // Flush items with 'users' tag
            PersistentCache::flushTags(['users']);
            
            expect(PersistentCache::get('tagged1'))->toBeNull();
            expect(PersistentCache::get('tagged2'))->toBeNull();
            expect(PersistentCache::get('untagged'))->toBe('value3');
        });
    });

    describe('Configuration', function () {
        it('can set and get default store', function () {
            $originalStore = PersistentCache::getDefaultStore();
            
            PersistentCache::setDefaultStore('redis');
            expect(PersistentCache::getDefaultStore())->toBe('redis');
            
            PersistentCache::setDefaultStore($originalStore);
        });

        it('can set and get default TTL', function () {
            $originalTtl = PersistentCache::getDefaultTtl();
            
            PersistentCache::setDefaultTtl(7200);
            expect(PersistentCache::getDefaultTtl())->toBe(7200);
            
            PersistentCache::setDefaultTtl($originalTtl);
        });

        it('can set and get key prefix', function () {
            $originalPrefix = PersistentCache::getKeyPrefix();
            
            PersistentCache::setKeyPrefix('custom');
            expect(PersistentCache::getKeyPrefix())->toBe('custom');
            
            PersistentCache::setKeyPrefix($originalPrefix);
        });
    });

    describe('Enable/Disable Functionality', function () {
        it('can be disabled and enabled', function () {
            expect(PersistentCache::isEnabled())->toBeTrue();
            
            PersistentCache::disable();
            expect(PersistentCache::isEnabled())->toBeFalse();
            
            // Operations should return false/null when disabled
            expect(PersistentCache::put('test_key', 'test_value', 60))->toBeFalse();
            expect(PersistentCache::get('test_key'))->toBeNull();
            expect(PersistentCache::has('test_key'))->toBeFalse();
            
            PersistentCache::enable();
            expect(PersistentCache::isEnabled())->toBeTrue();
            
            // Operations should work again when enabled
            expect(PersistentCache::put('test_key', 'test_value', 60))->toBeTrue();
            expect(PersistentCache::get('test_key'))->toBe('test_value');
        });
    });

    describe('Error Handling', function () {
        it('handles cache errors gracefully', function () {
            // Simulate cache error by using invalid store
            $result = PersistentCache::put('test_key', 'value', 60, [], 'invalid_store');
            expect($result)->toBeFalse();
            
            $value = PersistentCache::get('test_key', 'default', 'invalid_store');
            expect($value)->toBe('default');
        });
    });
});
