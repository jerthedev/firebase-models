<?php

namespace JTD\FirebaseModels\Cache;

/**
 * Cache manager that coordinates between request cache and persistent cache.
 *
 * Provides a unified interface for caching with intelligent fallback:
 * 1. Check request cache (fastest)
 * 2. Check persistent cache (cross-request)
 * 3. Execute callback and cache results
 */
class CacheManager
{
    /**
     * Cache hierarchy configuration.
     */
    protected static array $config = [
        'request_cache_enabled' => true,
        'persistent_cache_enabled' => true,
        'default_ttl' => 3600,
        'default_store' => null,
        'auto_promote' => true, // Promote persistent cache hits to request cache
    ];

    /**
     * Get an item from cache hierarchy.
     */
    public static function get(string $key, mixed $default = null, ?string $store = null): mixed
    {
        // 1. Try request cache first (fastest)
        if (static::$config['request_cache_enabled'] && RequestCache::isEnabled()) {
            $value = RequestCache::get($key);
            if ($value !== null || RequestCache::has($key)) {
                return $value;
            }
        }

        // 2. Try persistent cache
        if (static::$config['persistent_cache_enabled'] && PersistentCache::isEnabled()) {
            $value = PersistentCache::get($key, null, $store);
            if ($value !== null || PersistentCache::has($key, $store)) {
                // Auto-promote to request cache for faster subsequent access
                if (static::$config['auto_promote'] && static::$config['request_cache_enabled']) {
                    RequestCache::put($key, $value);
                }

                return $value;
            }
        }

        return $default;
    }

    /**
     * Store an item in cache hierarchy.
     */
    public static function put(string $key, mixed $value, ?int $ttl = null, array $tags = [], ?string $store = null): bool
    {
        $success = true;

        // Store in request cache
        if (static::$config['request_cache_enabled'] && RequestCache::isEnabled()) {
            RequestCache::put($key, $value);
        }

        // Store in persistent cache
        if (static::$config['persistent_cache_enabled'] && PersistentCache::isEnabled()) {
            $ttl = $ttl ?? static::$config['default_ttl'];
            $store = $store ?? static::$config['default_store'];

            if (!PersistentCache::put($key, $value, $ttl, $tags, $store)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Store an item in cache hierarchy forever.
     */
    public static function forever(string $key, mixed $value, array $tags = [], ?string $store = null): bool
    {
        $success = true;

        // Store in request cache
        if (static::$config['request_cache_enabled'] && RequestCache::isEnabled()) {
            RequestCache::put($key, $value);
        }

        // Store in persistent cache forever
        if (static::$config['persistent_cache_enabled'] && PersistentCache::isEnabled()) {
            $store = $store ?? static::$config['default_store'];

            if (!PersistentCache::forever($key, $value, $tags, $store)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get an item from cache, or execute callback and store result.
     */
    public static function remember(string $key, \Closure $callback, ?int $ttl = null, array $tags = [], ?string $store = null): mixed
    {
        // Check cache hierarchy first
        if (static::has($key, $store)) {
            return static::get($key, null, $store);
        }

        // Execute callback
        $value = $callback();

        // Store in cache hierarchy
        static::put($key, $value, $ttl, $tags, $store);

        return $value;
    }

    /**
     * Get an item from cache, or execute callback and store result forever.
     */
    public static function rememberForever(string $key, \Closure $callback, array $tags = [], ?string $store = null): mixed
    {
        // Check cache hierarchy first
        if (static::has($key, $store)) {
            return static::get($key, null, $store);
        }

        // Execute callback
        $value = $callback();

        // Store in cache hierarchy forever
        static::forever($key, $value, $tags, $store);

        return $value;
    }

    /**
     * Check if an item exists in cache hierarchy.
     */
    public static function has(string $key, ?string $store = null): bool
    {
        // Check request cache first
        if (static::$config['request_cache_enabled'] && RequestCache::isEnabled()) {
            if (RequestCache::has($key)) {
                return true;
            }
        }

        // Check persistent cache
        if (static::$config['persistent_cache_enabled'] && PersistentCache::isEnabled()) {
            return PersistentCache::has($key, $store);
        }

        return false;
    }

    /**
     * Remove an item from cache hierarchy.
     */
    public static function forget(string $key, ?string $store = null): bool
    {
        $success = true;

        // Remove from request cache
        if (static::$config['request_cache_enabled']) {
            RequestCache::forget($key);
        }

        // Remove from persistent cache
        if (static::$config['persistent_cache_enabled']) {
            if (!PersistentCache::forget($key, $store)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove multiple items from cache hierarchy.
     */
    public static function forgetMany(array $keys, ?string $store = null): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (!static::forget($key, $store)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Flush cache hierarchy with specific tags.
     */
    public static function flushTags(array $tags, ?string $store = null): bool
    {
        $success = true;

        // Clear request cache (doesn't support tags, so clear all)
        if (static::$config['request_cache_enabled']) {
            RequestCache::clear();
        }

        // Flush persistent cache with tags
        if (static::$config['persistent_cache_enabled']) {
            if (!PersistentCache::flushTags($tags, $store)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Flush all cache hierarchy.
     */
    public static function flush(?string $store = null): bool
    {
        $success = true;

        // Clear request cache
        if (static::$config['request_cache_enabled']) {
            RequestCache::clear();
        }

        // Flush persistent cache
        if (static::$config['persistent_cache_enabled']) {
            if (!PersistentCache::flush($store)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get combined cache statistics.
     */
    public static function getStats(): array
    {
        $requestStats = RequestCache::getStats();
        $persistentStats = PersistentCache::getStats();

        return [
            'request_cache' => $requestStats,
            'persistent_cache' => $persistentStats,
            'combined' => [
                'hits' => $requestStats['hits'] + $persistentStats['hits'],
                'misses' => $requestStats['misses'] + $persistentStats['misses'],
                'sets' => $requestStats['sets'] + $persistentStats['sets'],
                'deletes' => $requestStats['deletes'] + $persistentStats['deletes'],
                'hit_rate' => static::getCombinedHitRate($requestStats, $persistentStats),
            ],
        ];
    }

    /**
     * Get detailed cache statistics (alias for getStats with additional metrics).
     */
    public static function getStatistics(): array
    {
        $stats = static::getStats();
        $combined = $stats['combined'];

        return [
            'hit_rate' => $combined['hit_rate'] / 100, // Convert to decimal
            'total_requests' => $combined['hits'] + $combined['misses'],
            'hits' => $combined['hits'],
            'misses' => $combined['misses'],
            'cache_size_bytes' => static::estimateCacheSize(),
            'eviction_rate' => 0.0, // Placeholder for now
            'request_cache' => $stats['request_cache'],
            'persistent_cache' => $stats['persistent_cache'],
        ];
    }

    /**
     * Calculate combined hit rate.
     */
    protected static function getCombinedHitRate(array $requestStats, array $persistentStats): float
    {
        $totalHits = $requestStats['hits'] + $persistentStats['hits'];
        $totalMisses = $requestStats['misses'] + $persistentStats['misses'];
        $total = $totalHits + $totalMisses;

        if ($total === 0) {
            return 0.0;
        }

        return round(($totalHits / $total) * 100, 2);
    }

    /**
     * Configure cache manager.
     */
    public static function configure(array $config): void
    {
        static::$config = array_merge(static::$config, $config);
    }

    /**
     * Get current configuration.
     */
    public static function getConfig(): array
    {
        return static::$config;
    }

    /**
     * Check if a key exists in request cache.
     */
    protected static function hasInRequestCache(string $key): bool
    {
        return RequestCache::isEnabled() && RequestCache::has($key);
    }

    /**
     * Check if a key exists in persistent cache.
     */
    protected static function hasInPersistentCache(string $key, ?string $store = null): bool
    {
        return PersistentCache::isEnabled() && PersistentCache::has($key, $store);
    }

    /**
     * Enable request cache.
     */
    public static function enableRequestCache(): void
    {
        static::$config['request_cache_enabled'] = true;
        RequestCache::enable();
    }

    /**
     * Disable request cache.
     */
    public static function disableRequestCache(): void
    {
        static::$config['request_cache_enabled'] = false;
        RequestCache::disable();
    }

    /**
     * Enable persistent cache.
     */
    public static function enablePersistentCache(): void
    {
        static::$config['persistent_cache_enabled'] = true;
        PersistentCache::enable();
    }

    /**
     * Disable persistent cache.
     */
    public static function disablePersistentCache(): void
    {
        static::$config['persistent_cache_enabled'] = false;
        PersistentCache::disable();
    }

    /**
     * Enable auto-promotion from persistent to request cache.
     */
    public static function enableAutoPromotion(): void
    {
        static::$config['auto_promote'] = true;
    }

    /**
     * Disable auto-promotion from persistent to request cache.
     */
    public static function disableAutoPromotion(): void
    {
        static::$config['auto_promote'] = false;
    }

    /**
     * Estimate the total cache size in bytes.
     */
    protected static function estimateCacheSize(): int
    {
        // Estimate request cache size (rough calculation)
        $requestCacheSize = RequestCache::getStats()['size'] ?? 0;
        $estimatedRequestBytes = $requestCacheSize * 1024; // Rough estimate: 1KB per item

        // For persistent cache, we can't easily get size without Laravel cache store access
        // So we'll provide a conservative estimate
        $estimatedPersistentBytes = 0;

        return $estimatedRequestBytes + $estimatedPersistentBytes;
    }
}
