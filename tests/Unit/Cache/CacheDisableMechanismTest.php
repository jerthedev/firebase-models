<?php

use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Cache\PersistentCache;

describe('Cache Disable Mechanism', function () {
    beforeEach(function () {
        // Reset cache states
        RequestCache::clear();
        RequestCache::resetStats();
        PersistentCache::resetStats();
        
        // Reset to default enabled state
        RequestCache::enable();
        PersistentCache::enable();
        
        // Reset cache manager configuration
        CacheManager::configure([
            'request_cache_enabled' => true,
            'persistent_cache_enabled' => true,
            'default_ttl' => 3600,
            'default_store' => null,
            'auto_promote' => true,
        ]);
    });

    describe('Global Cache Disable', function () {
        it('disables both request and persistent cache when global cache is disabled', function () {
            // Simulate global cache disabled configuration
            config(['firebase-models.cache.enabled' => false]);
            
            // Simulate service provider configuration logic
            $globalEnabled = config('firebase-models.cache.enabled', true);
            $requestEnabled = $globalEnabled && config('firebase-models.cache.request_enabled', true);
            $persistentEnabled = $globalEnabled && config('firebase-models.cache.persistent_enabled', true);

            // Configure cache manager with proper enabled states
            CacheManager::configure([
                'request_cache_enabled' => $requestEnabled,
                'persistent_cache_enabled' => $persistentEnabled,
                'default_ttl' => 3600,
                'default_store' => null,
                'auto_promote' => true,
            ]);

            // Enable/disable individual cache components
            if ($requestEnabled) {
                RequestCache::enable();
            } else {
                RequestCache::disable();
            }
            
            if ($persistentEnabled) {
                PersistentCache::enable();
            } else {
                PersistentCache::disable();
            }
            
            // Verify both caches are disabled
            expect(RequestCache::isEnabled())->toBeFalse();
            expect(PersistentCache::isEnabled())->toBeFalse();
            
            // Verify cache manager configuration reflects disabled state
            $config = CacheManager::getConfig();
            expect($config['request_cache_enabled'])->toBeFalse();
            expect($config['persistent_cache_enabled'])->toBeFalse();
        });

        it('respects individual cache settings when global cache is enabled', function () {
            // Simulate global cache enabled but individual components disabled
            config([
                'firebase-models.cache.enabled' => true,
                'firebase-models.cache.request_enabled' => false,
                'firebase-models.cache.persistent_enabled' => true,
            ]);
            
            // Simulate service provider configuration logic
            $globalEnabled = config('firebase-models.cache.enabled', true);
            $requestEnabled = $globalEnabled && config('firebase-models.cache.request_enabled', true);
            $persistentEnabled = $globalEnabled && config('firebase-models.cache.persistent_enabled', true);

            // Configure cache manager
            CacheManager::configure([
                'request_cache_enabled' => $requestEnabled,
                'persistent_cache_enabled' => $persistentEnabled,
                'default_ttl' => 3600,
                'default_store' => null,
                'auto_promote' => true,
            ]);

            // Enable/disable individual cache components
            if ($requestEnabled) {
                RequestCache::enable();
            } else {
                RequestCache::disable();
            }
            
            if ($persistentEnabled) {
                PersistentCache::enable();
            } else {
                PersistentCache::disable();
            }
            
            // Verify request cache is disabled, persistent cache is enabled
            expect(RequestCache::isEnabled())->toBeFalse();
            expect(PersistentCache::isEnabled())->toBeTrue();
            
            // Verify cache manager configuration
            $config = CacheManager::getConfig();
            expect($config['request_cache_enabled'])->toBeFalse();
            expect($config['persistent_cache_enabled'])->toBeTrue();
        });
    });

    describe('Cache Manager Operations with Disabled Cache', function () {
        it('returns default value when both caches are disabled', function () {
            // Disable both caches
            CacheManager::configure([
                'request_cache_enabled' => false,
                'persistent_cache_enabled' => false,
                'default_ttl' => 3600,
                'default_store' => null,
                'auto_promote' => true,
            ]);
            
            RequestCache::disable();
            PersistentCache::disable();
            
            // Try to get a value that doesn't exist
            $result = CacheManager::get('test_key', 'default_value');
            
            expect($result)->toBe('default_value');
        });

        it('does not store values when both caches are disabled', function () {
            // Disable both caches
            CacheManager::configure([
                'request_cache_enabled' => false,
                'persistent_cache_enabled' => false,
                'default_ttl' => 3600,
                'default_store' => null,
                'auto_promote' => true,
            ]);
            
            RequestCache::disable();
            PersistentCache::disable();
            
            // Try to store a value
            $result = CacheManager::put('test_key', 'test_value');
            
            // Put should still return true (no error)
            expect($result)->toBeTrue();
            
            // But the value should not be retrievable
            $retrieved = CacheManager::get('test_key', 'default');
            expect($retrieved)->toBe('default');
        });

        it('uses remember callback when caches are disabled', function () {
            // Disable both caches
            CacheManager::configure([
                'request_cache_enabled' => false,
                'persistent_cache_enabled' => false,
                'default_ttl' => 3600,
                'default_store' => null,
                'auto_promote' => true,
            ]);
            
            RequestCache::disable();
            PersistentCache::disable();
            
            $callbackExecuted = false;
            
            // Use remember method
            $result = CacheManager::remember('test_key', function () use (&$callbackExecuted) {
                $callbackExecuted = true;
                return 'callback_result';
            });
            
            // Callback should be executed and result returned
            expect($callbackExecuted)->toBeTrue();
            expect($result)->toBe('callback_result');
            
            // Second call should execute callback again (no caching)
            $callbackExecuted = false;
            $result2 = CacheManager::remember('test_key', function () use (&$callbackExecuted) {
                $callbackExecuted = true;
                return 'callback_result_2';
            });
            
            expect($callbackExecuted)->toBeTrue();
            expect($result2)->toBe('callback_result_2');
        });
    });
});
