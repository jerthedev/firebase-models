<?php

use JTD\FirebaseModels\Cache\RequestCache;

describe('RequestCache', function () {
    beforeEach(function () {
        // Clear cache and reset stats before each test
        RequestCache::clear();
        RequestCache::resetStats();
        RequestCache::enable();
    });

    afterEach(function () {
        // Clean up after each test
        RequestCache::clear();
        RequestCache::resetStats();
    });

    describe('Basic Cache Operations', function () {
        it('can store and retrieve values', function () {
            RequestCache::put('test_key', 'test_value');
            
            expect(RequestCache::get('test_key'))->toBe('test_value');
            expect(RequestCache::has('test_key'))->toBeTrue();
        });

        it('returns null for non-existent keys', function () {
            expect(RequestCache::get('non_existent'))->toBeNull();
            expect(RequestCache::has('non_existent'))->toBeFalse();
        });

        it('can store complex data types', function () {
            $data = [
                'array' => [1, 2, 3],
                'object' => (object) ['prop' => 'value'],
                'null' => null,
                'boolean' => true,
                'number' => 42.5,
            ];

            RequestCache::put('complex_data', $data);
            
            $retrieved = RequestCache::get('complex_data');
            expect($retrieved)->toBe($data);
        });

        it('can forget cached values', function () {
            RequestCache::put('forget_me', 'value');
            expect(RequestCache::has('forget_me'))->toBeTrue();
            
            RequestCache::forget('forget_me');
            expect(RequestCache::has('forget_me'))->toBeFalse();
            expect(RequestCache::get('forget_me'))->toBeNull();
        });

        it('can clear all cached values', function () {
            RequestCache::put('key1', 'value1');
            RequestCache::put('key2', 'value2');
            RequestCache::put('key3', 'value3');
            
            expect(RequestCache::size())->toBe(3);
            
            RequestCache::clear();
            
            expect(RequestCache::size())->toBe(0);
            expect(RequestCache::get('key1'))->toBeNull();
            expect(RequestCache::get('key2'))->toBeNull();
            expect(RequestCache::get('key3'))->toBeNull();
        });
    });

    describe('Cache Statistics', function () {
        it('tracks cache hits and misses', function () {
            // Initial stats should be zero
            $stats = RequestCache::getStats();
            expect($stats['hits'])->toBe(0);
            expect($stats['misses'])->toBe(0);

            // Miss on non-existent key
            RequestCache::get('non_existent');
            $stats = RequestCache::getStats();
            expect($stats['misses'])->toBe(1);
            expect($stats['hits'])->toBe(0);

            // Store and hit
            RequestCache::put('test_key', 'test_value');
            RequestCache::get('test_key');
            $stats = RequestCache::getStats();
            expect($stats['hits'])->toBe(1);
            expect($stats['misses'])->toBe(1);

            // Another hit
            RequestCache::get('test_key');
            $stats = RequestCache::getStats();
            expect($stats['hits'])->toBe(2);
            expect($stats['misses'])->toBe(1);
        });

        it('tracks sets and deletes', function () {
            RequestCache::put('key1', 'value1');
            RequestCache::put('key2', 'value2');
            
            $stats = RequestCache::getStats();
            expect($stats['sets'])->toBe(2);
            expect($stats['deletes'])->toBe(0);

            RequestCache::forget('key1');
            
            $stats = RequestCache::getStats();
            expect($stats['deletes'])->toBe(1);
        });

        it('calculates hit rate correctly', function () {
            // No operations yet
            expect(RequestCache::getHitRate())->toBe(0.0);

            // 1 miss, 0 hits = 0% hit rate
            RequestCache::get('non_existent');
            expect(RequestCache::getHitRate())->toBe(0.0);

            // 1 miss, 1 hit = 50% hit rate
            RequestCache::put('test_key', 'value');
            RequestCache::get('test_key');
            expect(RequestCache::getHitRate())->toBe(50.0);

            // 1 miss, 2 hits = 66.67% hit rate
            RequestCache::get('test_key');
            expect(RequestCache::getHitRate())->toBe(66.67);
        });

        it('can reset statistics', function () {
            RequestCache::put('key', 'value');
            RequestCache::get('key');
            RequestCache::get('non_existent');
            
            $stats = RequestCache::getStats();
            expect($stats['hits'])->toBeGreaterThan(0);
            expect($stats['misses'])->toBeGreaterThan(0);
            expect($stats['sets'])->toBeGreaterThan(0);

            RequestCache::resetStats();
            
            $stats = RequestCache::getStats();
            expect($stats['hits'])->toBe(0);
            expect($stats['misses'])->toBe(0);
            expect($stats['sets'])->toBe(0);
            expect($stats['deletes'])->toBe(0);
            expect($stats['clears'])->toBe(0);
        });
    });

    describe('Cache Size Management', function () {
        it('respects maximum cache size', function () {
            // Set a small max size for testing
            RequestCache::setMaxItems(5);
            
            // Fill cache beyond max size
            for ($i = 0; $i < 10; $i++) {
                RequestCache::put("key_{$i}", "value_{$i}");
            }
            
            // Cache should not exceed max size
            expect(RequestCache::size())->toBeLessThanOrEqual(5);
            expect(RequestCache::getMaxItems())->toBe(5);
        });

        it('can get cache size and keys', function () {
            RequestCache::put('key1', 'value1');
            RequestCache::put('key2', 'value2');
            RequestCache::put('key3', 'value3');
            
            expect(RequestCache::size())->toBe(3);
            
            $keys = RequestCache::keys();
            expect($keys)->toContain('key1');
            expect($keys)->toContain('key2');
            expect($keys)->toContain('key3');
            expect(count($keys))->toBe(3);
        });

        it('can flush cache when getting large', function () {
            // Set max items and fill cache
            RequestCache::setMaxItems(10);
            
            for ($i = 0; $i < 8; $i++) {
                RequestCache::put("key_{$i}", "value_{$i}");
            }
            
            $sizeBefore = RequestCache::size();
            RequestCache::flush();
            $sizeAfter = RequestCache::size();
            
            // Flush should reduce size when cache is getting large
            expect($sizeAfter)->toBeLessThanOrEqual($sizeBefore);
        });
    });

    describe('Enable/Disable Functionality', function () {
        it('can be disabled and enabled', function () {
            expect(RequestCache::isEnabled())->toBeTrue();
            
            RequestCache::disable();
            expect(RequestCache::isEnabled())->toBeFalse();
            
            // Operations should be no-ops when disabled
            RequestCache::put('test_key', 'test_value');
            expect(RequestCache::get('test_key'))->toBeNull();
            expect(RequestCache::has('test_key'))->toBeFalse();
            
            RequestCache::enable();
            expect(RequestCache::isEnabled())->toBeTrue();
            
            // Operations should work again when enabled
            RequestCache::put('test_key', 'test_value');
            expect(RequestCache::get('test_key'))->toBe('test_value');
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
            $result1 = RequestCache::remember('remember_key', $callback);
            expect($result1)->toBe('computed_value');
            expect($callCount)->toBe(1);
            
            // Second call should return cached value without executing callback
            $result2 = RequestCache::remember('remember_key', $callback);
            expect($result2)->toBe('computed_value');
            expect($callCount)->toBe(1); // Callback not called again
        });
    });

    describe('Key Generation', function () {
        it('can generate consistent cache keys', function () {
            $key1 = RequestCache::generateKey('get', 'users', ['where' => ['active' => true]]);
            $key2 = RequestCache::generateKey('get', 'users', ['where' => ['active' => true]]);
            
            expect($key1)->toBe($key2);
        });

        it('generates different keys for different parameters', function () {
            $key1 = RequestCache::generateKey('get', 'users', ['where' => ['active' => true]]);
            $key2 = RequestCache::generateKey('get', 'users', ['where' => ['active' => false]]);
            
            expect($key1)->not->toBe($key2);
        });

        it('generates different keys for different operations', function () {
            $key1 = RequestCache::generateKey('get', 'users', []);
            $key2 = RequestCache::generateKey('count', 'users', []);
            
            expect($key1)->not->toBe($key2);
        });

        it('generates different keys for different collections', function () {
            $key1 = RequestCache::generateKey('get', 'users', []);
            $key2 = RequestCache::generateKey('get', 'posts', []);
            
            expect($key1)->not->toBe($key2);
        });
    });

    describe('Debug and Dump', function () {
        it('can dump cache contents for debugging', function () {
            RequestCache::put('debug_key', 'debug_value');
            
            $dump = RequestCache::dump();
            
            expect($dump)->toHaveKey('cache');
            expect($dump)->toHaveKey('stats');
            expect($dump)->toHaveKey('enabled');
            expect($dump)->toHaveKey('max_items');
            
            expect($dump['cache']['debug_key'])->toBe('debug_value');
            expect($dump['enabled'])->toBeTrue();
        });
    });
});
