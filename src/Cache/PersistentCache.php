<?php

namespace JTD\FirebaseModels\Cache;

use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Illuminate\Support\Facades\Cache;

/**
 * Persistent cache for Firestore operations using Laravel's cache system.
 *
 * This cache provides cross-request persistence using Laravel's cache drivers
 * (Redis, Memcached, etc.) with intelligent invalidation strategies.
 */
class PersistentCache
{
    /**
     * Default cache store to use.
     */
    protected static ?string $defaultStore = null;

    /**
     * Default TTL in seconds.
     */
    protected static int $defaultTtl = 3600; // 1 hour

    /**
     * Cache key prefix for Firestore operations.
     */
    protected static string $keyPrefix = 'firestore';

    /**
     * Whether caching is enabled.
     */
    protected static bool $enabled = true;

    /**
     * Cache statistics for monitoring.
     */
    protected static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'flushes' => 0,
    ];

    /**
     * Get a cache repository instance.
     */
    protected static function cache(?string $store = null): Repository
    {
        $store = $store ?? static::$defaultStore ?? config('firebase-models.cache.store');

        if ($store) {
            return Cache::store($store);
        }

        return Cache::store();
    }

    /**
     * Get a tagged cache instance if supported.
     */
    protected static function taggedCache(array $tags = [], ?string $store = null): Repository|TaggedCache
    {
        $cache = static::cache($store);

        if (empty($tags)) {
            return $cache;
        }

        // Add default Firestore tag
        $tags = array_merge([static::$keyPrefix], $tags);

        try {
            return $cache->tags($tags);
        } catch (\BadMethodCallException $e) {
            // Cache driver doesn't support tagging, return regular cache
            return $cache;
        }
    }

    /**
     * Generate a prefixed cache key.
     */
    protected static function key(string $key): string
    {
        return static::$keyPrefix.':'.$key;
    }

    /**
     * Get an item from the cache.
     */
    public static function get(string $key, mixed $default = null, ?string $store = null): mixed
    {
        if (!static::$enabled) {
            return $default;
        }

        try {
            $cache = static::cache($store);
            $prefixedKey = static::key($key);

            $value = $cache->get($prefixedKey, $default);

            if ($value !== $default) {
                static::$stats['hits']++;
            } else {
                static::$stats['misses']++;
            }

            return $value;
        } catch (\Exception $e) {
            static::$stats['misses']++;

            return $default;
        }
    }

    /**
     * Store an item in the cache.
     */
    public static function put(string $key, mixed $value, ?int $ttl = null, array $tags = [], ?string $store = null): bool
    {
        if (!static::$enabled) {
            return false;
        }

        try {
            $cache = static::taggedCache($tags, $store);
            $prefixedKey = static::key($key);
            $ttl = $ttl ?? static::$defaultTtl;

            $result = $cache->put($prefixedKey, $value, $ttl);

            if ($result) {
                static::$stats['sets']++;
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Store an item in the cache forever.
     */
    public static function forever(string $key, mixed $value, array $tags = [], ?string $store = null): bool
    {
        if (!static::$enabled) {
            return false;
        }

        try {
            $cache = static::taggedCache($tags, $store);
            $prefixedKey = static::key($key);

            $result = $cache->forever($prefixedKey, $value);

            if ($result) {
                static::$stats['sets']++;
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     */
    public static function remember(string $key, \Closure $callback, ?int $ttl = null, array $tags = [], ?string $store = null): mixed
    {
        if (!static::$enabled) {
            return $callback();
        }

        try {
            $cache = static::taggedCache($tags, $store);
            $prefixedKey = static::key($key);
            $ttl = $ttl ?? static::$defaultTtl;

            $value = $cache->get($prefixedKey);

            if ($value !== null) {
                static::$stats['hits']++;

                return $value;
            }

            static::$stats['misses']++;
            $value = $callback();

            if ($value !== null) {
                $cache->put($prefixedKey, $value, $ttl);
                static::$stats['sets']++;
            }

            return $value;
        } catch (\Exception $e) {
            static::$stats['misses']++;

            return $callback();
        }
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     */
    public static function rememberForever(string $key, \Closure $callback, array $tags = [], ?string $store = null): mixed
    {
        if (!static::$enabled) {
            return $callback();
        }

        try {
            $cache = static::taggedCache($tags, $store);
            $prefixedKey = static::key($key);

            $value = $cache->get($prefixedKey);

            if ($value !== null) {
                static::$stats['hits']++;

                return $value;
            }

            static::$stats['misses']++;
            $value = $callback();

            if ($value !== null) {
                $cache->forever($prefixedKey, $value);
                static::$stats['sets']++;
            }

            return $value;
        } catch (\Exception $e) {
            static::$stats['misses']++;

            return $callback();
        }
    }

    /**
     * Check if an item exists in the cache.
     */
    public static function has(string $key, ?string $store = null): bool
    {
        if (!static::$enabled) {
            return false;
        }

        try {
            $cache = static::cache($store);
            $prefixedKey = static::key($key);

            return $cache->has($prefixedKey);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove an item from the cache.
     */
    public static function forget(string $key, ?string $store = null): bool
    {
        try {
            $cache = static::cache($store);
            $prefixedKey = static::key($key);

            $result = $cache->forget($prefixedKey);

            if ($result) {
                static::$stats['deletes']++;
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove multiple items from the cache.
     */
    public static function forgetMany(array $keys, ?string $store = null): bool
    {
        try {
            $cache = static::cache($store);
            $prefixedKeys = array_map([static::class, 'key'], $keys);

            $result = true;
            foreach ($prefixedKeys as $key) {
                if (!$cache->forget($key)) {
                    $result = false;
                } else {
                    static::$stats['deletes']++;
                }
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Flush all items with specific tags.
     */
    public static function flushTags(array $tags, ?string $store = null): bool
    {
        try {
            $cache = static::taggedCache($tags, $store);

            $result = $cache->flush();

            if ($result) {
                static::$stats['flushes']++;
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Flush all Firestore cache items.
     */
    public static function flush(?string $store = null): bool
    {
        try {
            $cache = static::cache($store);

            return $cache->flush();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get cache statistics.
     */
    public static function getStats(): array
    {
        return static::$stats + [
            'hit_rate' => static::getHitRate(),
        ];
    }

    /**
     * Get the cache hit rate as a percentage.
     */
    public static function getHitRate(): float
    {
        $total = static::$stats['hits'] + static::$stats['misses'];

        if ($total === 0) {
            return 0.0;
        }

        return round((static::$stats['hits'] / $total) * 100, 2);
    }

    /**
     * Reset cache statistics.
     */
    public static function resetStats(): void
    {
        static::$stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'flushes' => 0,
        ];
    }

    /**
     * Enable persistent caching.
     */
    public static function enable(): void
    {
        static::$enabled = true;
    }

    /**
     * Disable persistent caching.
     */
    public static function disable(): void
    {
        static::$enabled = false;
    }

    /**
     * Check if persistent caching is enabled.
     */
    public static function isEnabled(): bool
    {
        return static::$enabled;
    }

    /**
     * Set the default cache store.
     */
    public static function setDefaultStore(?string $store): void
    {
        static::$defaultStore = $store;
    }

    /**
     * Get the default cache store.
     */
    public static function getDefaultStore(): ?string
    {
        return static::$defaultStore;
    }

    /**
     * Set the default TTL.
     */
    public static function setDefaultTtl(int $ttl): void
    {
        static::$defaultTtl = max(1, $ttl);
    }

    /**
     * Get the default TTL.
     */
    public static function getDefaultTtl(): int
    {
        return static::$defaultTtl;
    }

    /**
     * Set the cache key prefix.
     */
    public static function setKeyPrefix(string $prefix): void
    {
        static::$keyPrefix = $prefix;
    }

    /**
     * Get the cache key prefix.
     */
    public static function getKeyPrefix(): string
    {
        return static::$keyPrefix;
    }
}
