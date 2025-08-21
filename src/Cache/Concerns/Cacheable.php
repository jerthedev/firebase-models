<?php

namespace JTD\FirebaseModels\Cache\Concerns;

use JTD\FirebaseModels\Cache\RequestCache;
use JTD\FirebaseModels\Cache\PersistentCache;
use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Cache\QueryCacheKey;
use Illuminate\Support\Collection;

/**
 * Trait for adding request-scoped caching to query builders.
 * 
 * This trait provides caching functionality that can be mixed into
 * Firestore query builders to automatically cache query results
 * for the duration of a single request.
 */
trait Cacheable
{
    /**
     * Whether caching is enabled for this query.
     */
    protected bool $cacheEnabled = true;

    /**
     * Custom cache key for this query.
     */
    protected ?string $customCacheKey = null;

    /**
     * Cache tags for this query.
     */
    protected array $cacheTags = [];

    /**
     * Cached keys for different methods.
     */
    protected array $cachedKeys = [];

    /**
     * Cache TTL for persistent cache.
     */
    protected ?int $cacheTtl = null;

    /**
     * Cache store for persistent cache.
     */
    protected ?string $cacheStore = null;

    /**
     * Whether to use persistent cache.
     */
    protected bool $persistentCacheEnabled = true;

    /**
     * Execute the query with caching.
     */
    protected function getCached(string $method, array $arguments = []): mixed
    {
        if (!$this->shouldCache()) {
            return $this->executeQuery($method, $arguments);
        }

        // Generate cache key before any query modifications
        $cacheKey = $this->getCacheKey($method, $arguments);

        // Store the cache key for this method
        $this->cachedKeys[$method] = $cacheKey;

        // Use appropriate caching strategy based on configuration
        if ($this->persistentCacheEnabled && PersistentCache::isEnabled()) {
            // Use cache manager for hierarchical caching
            return CacheManager::remember($cacheKey, function () use ($method, $arguments) {
                return $this->executeQuery($method, $arguments);
            }, $this->cacheTtl, $this->cacheTags, $this->cacheStore);
        } else {
            // Use only request cache
            return RequestCache::remember($cacheKey, function () use ($method, $arguments) {
                return $this->executeQuery($method, $arguments);
            });
        }
    }

    /**
     * Execute the actual query without caching.
     */
    protected function executeQuery(string $method, array $arguments = []): mixed
    {
        // Call the parent method (e.g., parentGet, parentFirst, etc.)
        $parentMethod = "parent" . ucfirst($method);

        if (method_exists($this, $parentMethod)) {
            return call_user_func_array([$this, $parentMethod], $arguments);
        }

        // Fallback to calling the method directly if no parent method exists
        return call_user_func_array([$this, $method], $arguments);
    }

    /**
     * Determine if this query should be cached.
     */
    protected function shouldCache(): bool
    {
        return $this->cacheEnabled && (
            RequestCache::isEnabled() ||
            ($this->persistentCacheEnabled && PersistentCache::isEnabled())
        );
    }

    /**
     * Get the cache key for this query.
     */
    protected function getCacheKey(string $method, array $arguments = []): string
    {
        if ($this->customCacheKey !== null) {
            return $this->customCacheKey;
        }

        // Generate cache key based on query type and parameters
        if ($this instanceof \JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder) {
            $columns = $arguments[0] ?? ['*'];
            // Ensure columns is always an array
            if (!is_array($columns)) {
                $columns = [$columns];
            }
            return QueryCacheKey::forModelQuery($this, $columns, $method);
        } elseif ($this instanceof \JTD\FirebaseModels\Firestore\FirestoreQueryBuilder) {
            return QueryCacheKey::forQueryBuilder($this, $method, $arguments);
        }

        // Fallback to generic key generation
        $queryData = [
            'method' => $method,
            'arguments' => $arguments,
            'class' => get_class($this),
        ];

        // Try to extract query state for better cache keys
        try {
            $queryData = array_merge($queryData, $this->extractQueryState());
        } catch (\Exception $e) {
            // If extraction fails, use basic data
        }

        return QueryCacheKey::generate($this->getCollection(), $queryData);
    }

    /**
     * Extract query state for cache key generation.
     */
    protected function extractQueryState(): array
    {
        $state = [];

        // Try to extract common query properties
        $properties = ['wheres', 'orders', 'limitValue', 'offsetValue', 'selects', 'distinct'];

        foreach ($properties as $property) {
            if (property_exists($this, $property)) {
                $state[$property] = $this->$property;
            }
        }

        return $state;
    }

    /**
     * Get the collection name for this query.
     */
    protected function getCollection(): string
    {
        if (property_exists($this, 'collection')) {
            return $this->collection;
        }

        // Try to extract from query property
        if (property_exists($this, 'query') && method_exists($this->query, 'getCollection')) {
            return $this->query->getCollection();
        }

        return 'unknown';
    }

    /**
     * Disable caching for this query.
     */
    public function withoutCache(): static
    {
        $this->cacheEnabled = false;
        return $this;
    }

    /**
     * Enable caching for this query.
     */
    public function withCache(): static
    {
        $this->cacheEnabled = true;
        return $this;
    }

    /**
     * Set a custom cache key for this query.
     */
    public function cacheKey(string $key): static
    {
        $this->customCacheKey = $key;
        return $this;
    }

    /**
     * Add cache tags to this query.
     */
    public function cacheTags(array $tags): static
    {
        $this->cacheTags = array_merge($this->cacheTags, $tags);
        return $this;
    }

    /**
     * Set cache TTL for persistent cache.
     */
    public function cacheTtl(int $ttl): static
    {
        $this->cacheTtl = $ttl;
        return $this;
    }

    /**
     * Set cache store for persistent cache.
     */
    public function cacheStore(string $store): static
    {
        $this->cacheStore = $store;
        return $this;
    }

    /**
     * Enable persistent cache for this query.
     */
    public function withPersistentCache(): static
    {
        $this->persistentCacheEnabled = true;
        return $this;
    }

    /**
     * Disable persistent cache for this query.
     */
    public function withoutPersistentCache(): static
    {
        $this->persistentCacheEnabled = false;
        return $this;
    }

    /**
     * Get the cache tags for this query.
     */
    public function getCacheTags(): array
    {
        return $this->cacheTags;
    }

    /**
     * Clear cache for this query.
     */
    public function clearCache(): void
    {
        if ($this->customCacheKey !== null) {
            RequestCache::forget($this->customCacheKey);
            CacheManager::forget($this->customCacheKey, $this->cacheStore);
            return;
        }

        // Clear cache for specific operations that were cached
        foreach ($this->cachedKeys as $method => $cacheKey) {
            RequestCache::forget($cacheKey);
            CacheManager::forget($cacheKey, $this->cacheStore);
        }

        // Clear the stored cache keys
        $this->cachedKeys = [];
    }

    /**
     * Invalidate cache for specific operations.
     */
    public function invalidateCache(array $operations = ['get', 'first', 'count', 'exists']): void
    {
        foreach ($operations as $operation) {
            // Use stored cache key if available, otherwise generate new one
            $cacheKey = $this->cachedKeys[$operation] ?? $this->getCacheKey($operation);
            RequestCache::forget($cacheKey);

            // Remove from stored keys
            unset($this->cachedKeys[$operation]);
        }
    }

    /**
     * Get cache statistics for this query.
     */
    public function getCacheStats(): array
    {
        $stats = CacheManager::getStats();
        return $stats['combined'] ?? [];
    }

    /**
     * Check if a query result is cached.
     */
    public function isCached(string $method = 'get', array $arguments = []): bool
    {
        if (!$this->shouldCache()) {
            return false;
        }

        // Use stored cache key if available, otherwise generate new one
        $cacheKey = $this->cachedKeys[$method] ?? $this->getCacheKey($method, $arguments);

        // Check request cache first (most common case)
        if (RequestCache::has($cacheKey)) {
            return true;
        }

        // Check cache manager for persistent cache
        return CacheManager::has($cacheKey, $this->cacheStore);
    }

    /**
     * Warm the cache by executing the query.
     */
    public function warmCache(string $method = 'get', array $arguments = []): mixed
    {
        return $this->getCached($method, $arguments);
    }

    /**
     * Execute query and cache result with custom TTL.
     */
    public function remember(int $ttl, string $method = 'get', array $arguments = []): mixed
    {
        $originalTtl = $this->cacheTtl;
        $this->cacheTtl = $ttl;

        $result = $this->getCached($method, $arguments);

        $this->cacheTtl = $originalTtl;
        return $result;
    }

    /**
     * Execute query and cache result forever.
     */
    public function rememberForever(string $method = 'get', array $arguments = []): mixed
    {
        $cacheKey = $this->getCacheKey($method, $arguments);
        $this->cachedKeys[$method] = $cacheKey;

        if ($this->persistentCacheEnabled && PersistentCache::isEnabled()) {
            return CacheManager::rememberForever($cacheKey, function () use ($method, $arguments) {
                return $this->executeQuery($method, $arguments);
            }, $this->cacheTags, $this->cacheStore);
        } else {
            // Use only request cache (no "forever" for request cache)
            return RequestCache::remember($cacheKey, function () use ($method, $arguments) {
                return $this->executeQuery($method, $arguments);
            });
        }
    }

    /**
     * Flush all cache for this collection.
     */
    public function flushCache(): void
    {
        $collection = $this->getCollection();

        // Clear cached keys for this specific query instance
        foreach ($this->cachedKeys as $method => $cacheKey) {
            RequestCache::forget($cacheKey);
            if ($this->persistentCacheEnabled && PersistentCache::isEnabled()) {
                CacheManager::forget($cacheKey, $this->cacheStore);
            }
        }
        $this->cachedKeys = [];

        // Also clear from request cache - get all keys and remove matching ones
        foreach (RequestCache::keys() as $key) {
            $extractedCollection = QueryCacheKey::extractCollection($key);
            if ($extractedCollection === $collection) {
                RequestCache::forget($key);
            }
        }

        // Clear from persistent cache using tags
        if ($this->persistentCacheEnabled && PersistentCache::isEnabled()) {
            PersistentCache::flushTags([$collection], $this->cacheStore);
        }
    }

    /**
     * Get debug information about caching for this query.
     */
    public function getCacheDebugInfo(): array
    {
        return [
            'cache_enabled' => $this->cacheEnabled,
            'should_cache' => $this->shouldCache(),
            'custom_cache_key' => $this->customCacheKey,
            'cache_tags' => $this->cacheTags,
            'collection' => $this->getCollection(),
            'cache_key_get' => $this->getCacheKey('get'),
            'is_cached_get' => $this->isCached('get'),
            'cache_stats' => RequestCache::getStats(),
        ];
    }
}
