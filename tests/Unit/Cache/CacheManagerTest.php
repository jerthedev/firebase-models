<?php

namespace JTD\FirebaseModels\Tests\Unit\Cache;

use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Cache\PersistentCache;
use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Test;

/**
 * Cache Manager Test
 *
 * Updated to use UnitTestSuite for optimized performance and memory management.
 */
class CacheManagerTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for cache testing
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();

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
    }

    protected function tearDown(): void
    {
        RequestCache::clear();
        try {
            PersistentCache::flush('array');
        } catch (\Exception $e) {
            // Ignore cache flush errors in tests
        }

        parent::tearDown();
    }

    // ========================================
    // CACHE HIERARCHY TESTS
    // ========================================

    #[Test]
    public function it_stores_in_both_request_and_persistent_cache()
    {
        $result = CacheManager::put('test_key', 'test_value', 60);
        expect($result)->toBeTrue();

        // Should be in both caches
        expect(RequestCache::has('test_key'))->toBeTrue();
        expect(PersistentCache::has('test_key'))->toBeTrue();

        expect(RequestCache::get('test_key'))->toBe('test_value');
        expect(PersistentCache::get('test_key'))->toBe('test_value');
    }

    #[Test]
    public function it_retrieves_from_request_cache_first()
    {
        // Store in persistent cache only
        PersistentCache::put('persistent_key', 'persistent_value', 60);

        // Store different value in request cache
        RequestCache::put('persistent_key', 'request_value');

        // Should return request cache value
        $value = CacheManager::get('persistent_key');
        expect($value)->toBe('request_value');
    }

    #[Test]
    public function it_falls_back_to_persistent_cache_when_request_cache_misses()
    {
        // Store only in persistent cache
        PersistentCache::put('fallback_key', 'fallback_value', 60);

        // Should retrieve from persistent cache
        $value = CacheManager::get('fallback_key');
        expect($value)->toBe('fallback_value');
    }

    #[Test]
    public function it_auto_promotes_persistent_cache_hits_to_request_cache()
    {
        // Store only in persistent cache
        PersistentCache::put('promote_key', 'promote_value', 60);

        // First retrieval should promote to request cache
        $value = CacheManager::get('promote_key');
        expect($value)->toBe('promote_value');

        // Should now be in request cache
        expect(RequestCache::has('promote_key'))->toBeTrue();
        expect(RequestCache::get('promote_key'))->toBe('promote_value');
    }

    #[Test]
    public function it_can_disable_auto_promotion()
    {
        CacheManager::disableAutoPromotion();

        // Store only in persistent cache
        PersistentCache::put('no_promote_key', 'no_promote_value', 60);

        // Retrieval should not promote to request cache
        $value = CacheManager::get('no_promote_key');
        expect($value)->toBe('no_promote_value');

        // Should not be in request cache
        expect(RequestCache::has('no_promote_key'))->toBeFalse();
    }

    // ========================================
    // REMEMBER FUNCTIONALITY TESTS
    // ========================================

    #[Test]
    public function it_can_remember_values_using_callback()
    {
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
    }

    #[Test]
    public function it_can_remember_values_forever()
    {
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
    }

    // ========================================
    // CACHE CONTROL TESTS
    // ========================================

    #[Test]
    public function it_can_check_if_item_exists_in_hierarchy()
    {
        expect(CacheManager::has('non_existent'))->toBeFalse();

        // Store in request cache only
        RequestCache::put('request_only', 'value');
        expect(CacheManager::has('request_only'))->toBeTrue();

        // Store in persistent cache only
        PersistentCache::put('persistent_only', 'value', 60);
        expect(CacheManager::has('persistent_only'))->toBeTrue();
    }

    #[Test]
    public function it_can_forget_from_both_caches()
    {
        CacheManager::put('forget_key', 'forget_value', 60);

        expect(RequestCache::has('forget_key'))->toBeTrue();
        expect(PersistentCache::has('forget_key'))->toBeTrue();

        $result = CacheManager::forget('forget_key');
        expect($result)->toBeTrue();

        expect(RequestCache::has('forget_key'))->toBeFalse();
        expect(PersistentCache::has('forget_key'))->toBeFalse();
    }

    #[Test]
    public function it_can_forget_multiple_items()
    {
        CacheManager::put('key1', 'value1', 60);
        CacheManager::put('key2', 'value2', 60);

        $result = CacheManager::forgetMany(['key1', 'key2']);
        expect($result)->toBeTrue();

        expect(CacheManager::has('key1'))->toBeFalse();
        expect(CacheManager::has('key2'))->toBeFalse();
    }

    #[Test]
    public function it_can_flush_all_caches()
    {
        CacheManager::put('flush_key1', 'value1', 60);
        CacheManager::put('flush_key2', 'value2', 60);

        $result = CacheManager::flush();
        expect($result)->toBeTrue();

        expect(CacheManager::has('flush_key1'))->toBeFalse();
        expect(CacheManager::has('flush_key2'))->toBeFalse();
    }

    // ========================================
    // CACHE STATISTICS TESTS
    // ========================================

    #[Test]
    public function it_provides_combined_statistics()
    {
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
    }

    // ========================================
    // CONFIGURATION TESTS
    // ========================================

    #[Test]
    public function it_can_enable_and_disable_request_cache()
    {
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
    }

    #[Test]
    public function it_can_enable_and_disable_persistent_cache()
    {
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
    }

    #[Test]
    public function it_can_get_and_set_configuration()
    {
        $config = CacheManager::getConfig();
        expect($config)->toBeArray();
        expect($config)->toHaveKey('request_cache_enabled');
        expect($config)->toHaveKey('persistent_cache_enabled');

        CacheManager::configure(['default_ttl' => 7200]);

        $newConfig = CacheManager::getConfig();
        expect($newConfig['default_ttl'])->toBe(7200);
    }

    // ========================================
    // EDGE CASES TESTS
    // ========================================

    #[Test]
    public function it_handles_null_values_correctly()
    {
        $result = CacheManager::put('null_key', null, 60);
        expect($result)->toBeTrue();

        $value = CacheManager::get('null_key', 'default');
        expect($value)->toBeNull();
    }

    #[Test]
    public function it_handles_callback_returning_null()
    {
        $callback = function () {};

        $result = CacheManager::remember('null_callback', $callback, 60);
        expect($result)->toBeNull();
    }

    #[Test]
    public function it_works_when_only_one_cache_type_is_enabled()
    {
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
    }
}
