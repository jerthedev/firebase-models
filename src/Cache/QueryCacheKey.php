<?php

namespace JTD\FirebaseModels\Cache;

/**
 * Generates consistent cache keys for Firestore queries.
 *
 * This class creates deterministic cache keys based on query parameters,
 * ensuring that identical queries produce the same cache key while
 * different queries produce different keys.
 */
class QueryCacheKey
{
    /**
     * Generate a cache key for a Firestore query.
     */
    public static function generate(string $collection, array $queryData): string
    {
        // Normalize the query data to ensure consistent keys
        $normalized = static::normalizeQueryData($queryData);

        // Create the key components
        $keyData = [
            'collection' => $collection,
            'query' => $normalized,
            'version' => '1.0', // For cache invalidation if key format changes
        ];

        // Generate a hash of the key data
        $hash = hash('sha256', serialize($keyData));

        return "firestore_query:{$collection}:{$hash}";
    }

    /**
     * Generate a cache key for a document retrieval.
     */
    public static function generateForDocument(string $collection, string $documentId): string
    {
        return "firestore_doc:{$collection}:{$documentId}";
    }

    /**
     * Generate a cache key for a count query.
     */
    public static function generateForCount(string $collection, array $queryData): string
    {
        $normalized = static::normalizeQueryData($queryData);
        $hash = hash('sha256', serialize($normalized));

        return "firestore_count:{$collection}:{$hash}";
    }

    /**
     * Generate a cache key for an exists query.
     */
    public static function generateForExists(string $collection, array $queryData): string
    {
        $normalized = static::normalizeQueryData($queryData);
        $hash = hash('sha256', serialize($normalized));

        return "firestore_exists:{$collection}:{$hash}";
    }

    /**
     * Generate a cache key for a batch document retrieval.
     */
    public static function generateForBatch(array $documentPaths): string
    {
        // Sort paths to ensure consistent ordering
        sort($documentPaths);
        $hash = hash('sha256', serialize($documentPaths));

        return "firestore_batch:{$hash}";
    }

    /**
     * Normalize query data to ensure consistent cache keys.
     */
    protected static function normalizeQueryData(array $queryData, int $depth = 0, array &$seen = []): array
    {
        // Prevent infinite recursion
        if ($depth > 10) {
            return ['__max_depth_reached__' => true];
        }

        $normalized = [];

        // Sort all array keys to ensure consistent ordering
        ksort($queryData);

        foreach ($queryData as $key => $value) {
            if (is_array($value)) {
                // Recursively normalize nested arrays
                $normalized[$key] = static::normalizeQueryData($value, $depth + 1, $seen);
            } elseif (is_object($value)) {
                // Check for circular references
                $objectId = spl_object_id($value);
                if (isset($seen[$objectId])) {
                    $normalized[$key] = ['__circular_reference__' => get_class($value)];
                } else {
                    $seen[$objectId] = true;
                    // Convert objects to arrays for consistent serialization
                    $normalized[$key] = static::normalizeQueryData((array) $value, $depth + 1, $seen);
                    unset($seen[$objectId]);
                }
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Extract cache key components from a query builder.
     */
    public static function extractQueryData(\JTD\FirebaseModels\Firestore\FirestoreQueryBuilder $query): array
    {
        // Use reflection to access protected properties
        $reflection = new \ReflectionClass($query);

        $data = [];

        // Extract where conditions
        if ($reflection->hasProperty('wheres')) {
            $wheresProperty = $reflection->getProperty('wheres');
            $wheresProperty->setAccessible(true);
            $data['wheres'] = $wheresProperty->getValue($query);
        }

        // Extract order by conditions
        if ($reflection->hasProperty('orders')) {
            $ordersProperty = $reflection->getProperty('orders');
            $ordersProperty->setAccessible(true);
            $data['orders'] = $ordersProperty->getValue($query);
        }

        // Extract limit
        if ($reflection->hasProperty('limitValue')) {
            $limitProperty = $reflection->getProperty('limitValue');
            $limitProperty->setAccessible(true);
            $data['limit'] = $limitProperty->getValue($query);
        }

        // Extract offset
        if ($reflection->hasProperty('offsetValue')) {
            $offsetProperty = $reflection->getProperty('offsetValue');
            $offsetProperty->setAccessible(true);
            $data['offset'] = $offsetProperty->getValue($query);
        }

        // Extract select columns
        if ($reflection->hasProperty('selects')) {
            $selectsProperty = $reflection->getProperty('selects');
            $selectsProperty->setAccessible(true);
            $data['selects'] = $selectsProperty->getValue($query);
        }

        // Extract distinct flag
        if ($reflection->hasProperty('distinct')) {
            $distinctProperty = $reflection->getProperty('distinct');
            $distinctProperty->setAccessible(true);
            $data['distinct'] = $distinctProperty->getValue($query);
        }

        // Extract cursor pagination
        if ($reflection->hasProperty('cursorAfter')) {
            $cursorAfterProperty = $reflection->getProperty('cursorAfter');
            $cursorAfterProperty->setAccessible(true);
            $data['cursor_after'] = $cursorAfterProperty->getValue($query);
        }

        if ($reflection->hasProperty('cursorBefore')) {
            $cursorBeforeProperty = $reflection->getProperty('cursorBefore');
            $cursorBeforeProperty->setAccessible(true);
            $data['cursor_before'] = $cursorBeforeProperty->getValue($query);
        }

        // Extract random order flag
        if ($reflection->hasProperty('randomOrder')) {
            $randomOrderProperty = $reflection->getProperty('randomOrder');
            $randomOrderProperty->setAccessible(true);
            $data['random_order'] = $randomOrderProperty->getValue($query);
        }

        return $data;
    }

    /**
     * Create a cache key for a specific query builder instance.
     */
    public static function forQueryBuilder(\JTD\FirebaseModels\Firestore\FirestoreQueryBuilder $query, string $method = 'get', array $arguments = []): string
    {
        // Get collection name
        $reflection = new \ReflectionClass($query);
        $collectionProperty = $reflection->getProperty('collection');
        $collectionProperty->setAccessible(true);
        $collection = $collectionProperty->getValue($query);

        // Extract query data
        $queryData = static::extractQueryData($query);

        // Include method and arguments to ensure different operations have different cache keys
        $queryData['method'] = $method;
        $queryData['arguments'] = $arguments;

        return static::generate($collection, $queryData);
    }

    /**
     * Create a cache key for a model query.
     */
    public static function forModelQuery(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder $query, array $columns = ['*'], string $method = 'get'): string
    {
        // Get model class for additional context
        $reflection = new \ReflectionClass($query);
        $modelProperty = $reflection->getProperty('model');
        $modelProperty->setAccessible(true);
        $model = $modelProperty->getValue($query);

        // Extract query data directly from the model query builder (which is also a FirestoreQueryBuilder)
        $queryData = static::extractQueryData($query);
        $queryData['model_class'] = get_class($model);
        $queryData['columns'] = $columns;
        $queryData['method'] = $method;

        // Get collection from model query builder
        $collectionProperty = $reflection->getProperty('collection');
        $collectionProperty->setAccessible(true);
        $collection = $collectionProperty->getValue($query);

        return static::generate($collection, $queryData);
    }

    /**
     * Validate that a cache key is properly formatted.
     */
    public static function isValid(string $key): bool
    {
        // Check if key matches expected patterns
        $patterns = [
            '/^firestore_query:[a-zA-Z0-9_\-\/]+:[a-f0-9]{64}$/',
            '/^firestore_doc:[a-zA-Z0-9_\-\/]+:[a-zA-Z0-9_\-]+$/',
            '/^firestore_count:[a-zA-Z0-9_\-\/]+:[a-f0-9]{64}$/',
            '/^firestore_exists:[a-zA-Z0-9_\-\/]+:[a-f0-9]{64}$/',
            '/^firestore_batch:[a-f0-9]{64}$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract collection name from a cache key.
     */
    public static function extractCollection(string $key): ?string
    {
        if (preg_match('/^firestore_(?:query|doc|count|exists):([a-zA-Z0-9_\-\/]+):/', $key, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get cache key prefix for a collection.
     */
    public static function getCollectionPrefix(string $collection): string
    {
        return "firestore_*:{$collection}:*";
    }
}
