<?php

namespace JTD\FirebaseModels\Cache;

use Illuminate\Support\Collection;

/**
 * Request-scoped cache for Firestore operations.
 *
 * This cache stores query results for the duration of a single HTTP request,
 * reducing redundant Firestore API calls and improving performance for
 * operations that might query the same data multiple times.
 */
class RequestCache
{
    /**
     * The cache storage for the current request.
     */
    protected static array $cache = [];

    /**
     * Cache statistics for monitoring.
     */
    protected static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
        'clears' => 0,
    ];

    /**
     * Whether caching is enabled.
     */
    protected static bool $enabled = true;

    /**
     * Maximum number of items to cache.
     */
    protected static int $maxItems = 1000;

    /**
     * Get an item from the cache.
     */
    public static function get(string $key): mixed
    {
        if (!static::$enabled) {
            return null;
        }

        if (array_key_exists($key, static::$cache)) {
            static::$stats['hits']++;

            return static::$cache[$key];
        }

        static::$stats['misses']++;

        return null;
    }

    /**
     * Store an item in the cache.
     */
    public static function put(string $key, mixed $value): void
    {
        if (!static::$enabled) {
            return;
        }

        // Prevent cache from growing too large
        if (count(static::$cache) >= static::$maxItems) {
            // Remove oldest entries (simple FIFO)
            $keysToRemove = array_slice(array_keys(static::$cache), 0, 100);
            foreach ($keysToRemove as $keyToRemove) {
                unset(static::$cache[$keyToRemove]);
            }
        }

        static::$cache[$key] = $value;
        static::$stats['sets']++;
    }

    /**
     * Check if an item exists in the cache.
     */
    public static function has(string $key): bool
    {
        return static::$enabled && array_key_exists($key, static::$cache);
    }

    /**
     * Remove an item from the cache.
     */
    public static function forget(string $key): void
    {
        if (array_key_exists($key, static::$cache)) {
            unset(static::$cache[$key]);
            static::$stats['deletes']++;
        }
    }

    /**
     * Clear all cached items.
     */
    public static function clear(): void
    {
        static::$cache = [];
        static::$stats['clears']++;
    }

    /**
     * Get cache statistics.
     */
    public static function getStats(): array
    {
        return static::$stats + [
            'size' => count(static::$cache),
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
            'clears' => 0,
        ];
    }

    /**
     * Enable caching.
     */
    public static function enable(): void
    {
        static::$enabled = true;
    }

    /**
     * Disable caching.
     */
    public static function disable(): void
    {
        static::$enabled = false;
    }

    /**
     * Check if caching is enabled.
     */
    public static function isEnabled(): bool
    {
        return static::$enabled;
    }

    /**
     * Set the maximum number of items to cache.
     */
    public static function setMaxItems(int $maxItems): void
    {
        static::$maxItems = max(1, $maxItems);
    }

    /**
     * Get the maximum number of items to cache.
     */
    public static function getMaxItems(): int
    {
        return static::$maxItems;
    }

    /**
     * Get all cached keys.
     */
    public static function keys(): array
    {
        return array_keys(static::$cache);
    }

    /**
     * Get the current cache size.
     */
    public static function size(): int
    {
        return count(static::$cache);
    }

    /**
     * Flush cache if it's getting too large.
     */
    public static function flush(): void
    {
        if (count(static::$cache) > static::$maxItems * 0.8) {
            // Remove 20% of oldest entries
            $removeCount = (int) (count(static::$cache) * 0.2);
            $keysToRemove = array_slice(array_keys(static::$cache), 0, $removeCount);

            foreach ($keysToRemove as $key) {
                unset(static::$cache[$key]);
            }
        }
    }

    /**
     * Generate a cache key for a query.
     */
    public static function generateKey(string $operation, string $collection, array $parameters = []): string
    {
        // Create a deterministic key based on operation, collection, and parameters
        $keyData = [
            'op' => $operation,
            'collection' => $collection,
            'params' => $parameters,
        ];

        // Sort parameters to ensure consistent keys
        if (isset($keyData['params'])) {
            ksort($keyData['params']);
        }

        return 'firestore:'.md5(serialize($keyData));
    }

    /**
     * Remember a value using a callback.
     */
    public static function remember(string $key, \Closure $callback): mixed
    {
        $value = static::get($key);
        if ($value !== null || static::has($key)) {
            return $value;
        }

        $value = $callback();
        static::put($key, $value);

        return $value;
    }

    /**
     * Get cache contents for debugging.
     */
    public static function dump(): array
    {
        return [
            'cache' => static::$cache,
            'stats' => static::getStats(),
            'enabled' => static::$enabled,
            'max_items' => static::$maxItems,
        ];
    }
}
