<?php

namespace JTD\FirebaseModels\Optimization;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Intelligent Query Optimizer for Firestore operations.
 *
 * Analyzes query patterns, suggests optimizations, and provides
 * automatic index recommendations for improved performance.
 */
class QueryOptimizer
{
    protected static array $queryStats = [];

    protected static array $indexSuggestions = [];

    protected static bool $enabled = true;

    protected static array $config = [
        'track_queries' => true,
        'suggest_indexes' => true,
        'cache_suggestions' => true,
        'log_slow_queries' => true,
        'slow_query_threshold_ms' => 1000,
        'max_tracked_queries' => 1000,
    ];

    /**
     * Analyze and optimize a query before execution.
     */
    public static function optimizeQuery(string $collection, array $wheres = [], array $orders = [], ?int $limit = null): array
    {
        if (!static::$enabled) {
            return compact('collection', 'wheres', 'orders', 'limit');
        }

        $queryHash = static::generateQueryHash($collection, $wheres, $orders, $limit);
        $startTime = microtime(true);

        // Track query execution
        static::trackQuery($queryHash, $collection, $wheres, $orders, $limit);

        // Analyze query for optimization opportunities
        $optimizations = static::analyzeQuery($collection, $wheres, $orders, $limit);

        // Apply optimizations
        $optimizedQuery = static::applyOptimizations($collection, $wheres, $orders, $limit, $optimizations);

        // Check for index requirements
        static::checkIndexRequirements($collection, $wheres, $orders);

        return $optimizedQuery;
    }

    /**
     * Track query execution statistics.
     */
    public static function trackQueryExecution(string $queryHash, float $executionTimeMs, int $resultCount, bool $fromCache = false): void
    {
        if (!static::$config['track_queries']) {
            return;
        }

        if (!isset(static::$queryStats[$queryHash])) {
            static::$queryStats[$queryHash] = [
                'executions' => 0,
                'total_time_ms' => 0,
                'avg_time_ms' => 0,
                'min_time_ms' => PHP_FLOAT_MAX,
                'max_time_ms' => 0,
                'total_results' => 0,
                'avg_results' => 0,
                'cache_hits' => 0,
                'last_executed' => null,
            ];
        }

        $stats = &static::$queryStats[$queryHash];
        $stats['executions']++;
        $stats['total_time_ms'] += $executionTimeMs;
        $stats['avg_time_ms'] = $stats['total_time_ms'] / $stats['executions'];
        $stats['min_time_ms'] = min($stats['min_time_ms'], $executionTimeMs);
        $stats['max_time_ms'] = max($stats['max_time_ms'], $executionTimeMs);
        $stats['total_results'] += $resultCount;
        $stats['avg_results'] = $stats['total_results'] / $stats['executions'];
        $stats['last_executed'] = now()->toISOString();

        if ($fromCache) {
            $stats['cache_hits']++;
        }

        // Log slow queries
        if (static::$config['log_slow_queries'] && $executionTimeMs > static::$config['slow_query_threshold_ms']) {
            static::logSlowQuery($queryHash, $executionTimeMs, $resultCount);
        }

        // Cleanup old stats if we exceed the limit
        if (count(static::$queryStats) > static::$config['max_tracked_queries']) {
            static::cleanupOldStats();
        }
    }

    /**
     * Analyze query for optimization opportunities.
     */
    protected static function analyzeQuery(string $collection, array $wheres, array $orders, ?int $limit): array
    {
        $optimizations = [];

        // Check for inefficient query patterns
        if (count($wheres) > 3) {
            $optimizations[] = [
                'type' => 'too_many_filters',
                'message' => 'Query has many filters, consider using composite indexes',
                'severity' => 'medium',
                'suggestion' => 'Create a composite index for frequently used filter combinations',
            ];
        }

        // Check for missing limits on large collections
        if (is_null($limit) && static::isLargeCollection($collection)) {
            $optimizations[] = [
                'type' => 'missing_limit',
                'message' => 'Query on large collection without limit may be slow',
                'severity' => 'high',
                'suggestion' => 'Add a limit() clause to improve performance',
            ];
        }

        // Check for inefficient ordering
        if (!empty($orders) && !empty($wheres)) {
            $orderField = $orders[0]['field'] ?? null;
            $hasMatchingFilter = false;

            foreach ($wheres as $where) {
                if ($where['field'] === $orderField) {
                    $hasMatchingFilter = true;
                    break;
                }
            }

            if (!$hasMatchingFilter) {
                $optimizations[] = [
                    'type' => 'inefficient_ordering',
                    'message' => 'Ordering by field not used in filters requires composite index',
                    'severity' => 'medium',
                    'suggestion' => "Create composite index with filters + {$orderField}",
                ];
            }
        }

        // Check for array queries
        foreach ($wheres as $where) {
            if (in_array($where['operator'], ['array-contains', 'array-contains-any'])) {
                $optimizations[] = [
                    'type' => 'array_query',
                    'message' => 'Array queries can be expensive, consider denormalization',
                    'severity' => 'low',
                    'suggestion' => 'Consider storing array elements as separate documents for better performance',
                ];
                break;
            }
        }

        return $optimizations;
    }

    /**
     * Apply query optimizations.
     */
    protected static function applyOptimizations(string $collection, array $wheres, array $orders, ?int $limit, array $optimizations): array
    {
        $optimizedWheres = $wheres;
        $optimizedOrders = $orders;
        $optimizedLimit = $limit;

        // Auto-add limit for large collections if missing
        foreach ($optimizations as $optimization) {
            if ($optimization['type'] === 'missing_limit' && is_null($optimizedLimit)) {
                $optimizedLimit = 100; // Default safe limit
                Log::info('QueryOptimizer: Auto-added limit to query', [
                    'collection' => $collection,
                    'limit' => $optimizedLimit,
                ]);
            }
        }

        // Reorder filters for better performance (equality filters first)
        $optimizedWheres = static::optimizeFilterOrder($optimizedWheres);

        return [
            'collection' => $collection,
            'wheres' => $optimizedWheres,
            'orders' => $optimizedOrders,
            'limit' => $optimizedLimit,
            'optimizations_applied' => count($optimizations),
        ];
    }

    /**
     * Check and suggest required indexes.
     */
    protected static function checkIndexRequirements(string $collection, array $wheres, array $orders): void
    {
        if (!static::$config['suggest_indexes']) {
            return;
        }

        $indexKey = static::generateIndexKey($collection, $wheres, $orders);

        if (isset(static::$indexSuggestions[$indexKey])) {
            return; // Already suggested
        }

        $needsIndex = static::queryNeedsIndex($wheres, $orders);

        if ($needsIndex) {
            $suggestion = static::generateIndexSuggestion($collection, $wheres, $orders);
            static::$indexSuggestions[$indexKey] = $suggestion;

            if (static::$config['cache_suggestions']) {
                Cache::put("firestore_index_suggestion_{$indexKey}", $suggestion, 3600);
            }

            Log::info('QueryOptimizer: Index suggestion generated', [
                'collection' => $collection,
                'suggestion' => $suggestion,
            ]);
        }
    }

    /**
     * Generate index suggestion for a query.
     */
    protected static function generateIndexSuggestion(string $collection, array $wheres, array $orders): array
    {
        $fields = [];

        // Add equality filters first
        foreach ($wheres as $where) {
            if ($where['operator'] === '=') {
                $fields[] = [
                    'field' => $where['field'],
                    'order' => 'ASCENDING',
                ];
            }
        }

        // Add range filters
        foreach ($wheres as $where) {
            if (in_array($where['operator'], ['>', '>=', '<', '<='])) {
                $fields[] = [
                    'field' => $where['field'],
                    'order' => 'ASCENDING',
                ];
                break; // Only one range filter allowed
            }
        }

        // Add order by fields
        foreach ($orders as $order) {
            $direction = strtoupper($order['direction'] ?? 'ASC');
            $fields[] = [
                'field' => $order['field'],
                'order' => $direction === 'DESC' ? 'DESCENDING' : 'ASCENDING',
            ];
        }

        return [
            'collection' => $collection,
            'fields' => $fields,
            'query_scope' => 'COLLECTION',
            'firebase_url' => static::generateFirebaseIndexUrl($collection, $fields),
            'priority' => static::calculateIndexPriority($wheres, $orders),
            'estimated_benefit' => static::estimateIndexBenefit($wheres, $orders),
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get query performance statistics.
     */
    public static function getQueryStats(): array
    {
        return [
            'total_queries_tracked' => count(static::$queryStats),
            'total_executions' => array_sum(array_column(static::$queryStats, 'executions')),
            'avg_execution_time_ms' => static::calculateAverageExecutionTime(),
            'slow_queries' => static::getSlowQueries(),
            'most_frequent_queries' => static::getMostFrequentQueries(),
            'cache_hit_rate' => static::calculateCacheHitRate(),
            'index_suggestions' => count(static::$indexSuggestions),
        ];
    }

    /**
     * Get index suggestions.
     */
    public static function getIndexSuggestions(): array
    {
        return array_values(static::$indexSuggestions);
    }

    /**
     * Get optimization recommendations.
     */
    public static function getOptimizationRecommendations(): array
    {
        $recommendations = [];

        // Analyze query patterns for recommendations
        foreach (static::$queryStats as $queryHash => $stats) {
            if ($stats['avg_time_ms'] > static::$config['slow_query_threshold_ms']) {
                $recommendations[] = [
                    'type' => 'slow_query',
                    'query_hash' => $queryHash,
                    'avg_time_ms' => $stats['avg_time_ms'],
                    'executions' => $stats['executions'],
                    'recommendation' => 'Consider adding appropriate indexes or optimizing query structure',
                ];
            }

            if ($stats['cache_hits'] / $stats['executions'] < 0.5 && $stats['executions'] > 10) {
                $recommendations[] = [
                    'type' => 'low_cache_hit_rate',
                    'query_hash' => $queryHash,
                    'cache_hit_rate' => $stats['cache_hits'] / $stats['executions'],
                    'recommendation' => 'Consider increasing cache TTL or reviewing cache strategy',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Generate query hash for tracking.
     */
    protected static function generateQueryHash(string $collection, array $wheres, array $orders, ?int $limit): string
    {
        return md5(serialize(compact('collection', 'wheres', 'orders', 'limit')));
    }

    /**
     * Generate index key for caching suggestions.
     */
    protected static function generateIndexKey(string $collection, array $wheres, array $orders): string
    {
        $fields = array_merge(
            array_column($wheres, 'field'),
            array_column($orders, 'field')
        );

        return md5($collection.'_'.implode('_', $fields));
    }

    /**
     * Check if query needs an index.
     */
    protected static function queryNeedsIndex(array $wheres, array $orders): bool
    {
        // Multiple filters need composite index
        if (count($wheres) > 1) {
            return true;
        }

        // Filter + order by different field needs composite index
        if (!empty($wheres) && !empty($orders)) {
            $whereField = $wheres[0]['field'] ?? null;
            $orderField = $orders[0]['field'] ?? null;

            return $whereField !== $orderField;
        }

        // Array queries with other conditions need index
        foreach ($wheres as $where) {
            if (in_array($where['operator'], ['array-contains', 'array-contains-any'])) {
                return count($wheres) > 1 || !empty($orders);
            }
        }

        return false;
    }

    /**
     * Optimize filter order for better performance.
     */
    protected static function optimizeFilterOrder(array $wheres): array
    {
        // Sort filters: equality first, then range, then array operations
        usort($wheres, function ($a, $b) {
            $orderA = static::getFilterPriority($a['operator']);
            $orderB = static::getFilterPriority($b['operator']);

            return $orderA - $orderB;
        });

        return $wheres;
    }

    /**
     * Get filter priority for optimization.
     */
    protected static function getFilterPriority(string $operator): int
    {
        return match ($operator) {
            '=', '==' => 1,
            'in', 'not-in' => 2,
            '>', '>=', '<', '<=' => 3,
            'array-contains', 'array-contains-any' => 4,
            default => 5,
        };
    }

    /**
     * Check if collection is considered large.
     */
    protected static function isLargeCollection(string $collection): bool
    {
        // This could be enhanced with actual collection size data
        $largeCollections = ['posts', 'users', 'events', 'logs', 'analytics'];

        return in_array($collection, $largeCollections);
    }

    /**
     * Generate Firebase Console URL for index creation.
     */
    protected static function generateFirebaseIndexUrl(string $collection, array $fields): string
    {
        $projectId = config('firebase.project_id', 'your-project-id');
        $indexData = [
            'collection' => $collection,
            'fields' => $fields,
        ];

        return "https://console.firebase.google.com/project/{$projectId}/firestore/indexes?".
               'create_composite='.urlencode(json_encode($indexData));
    }

    /**
     * Calculate index priority based on query complexity.
     */
    protected static function calculateIndexPriority(array $wheres, array $orders): string
    {
        $score = count($wheres) + count($orders);

        return match (true) {
            $score >= 4 => 'high',
            $score >= 2 => 'medium',
            default => 'low',
        };
    }

    /**
     * Estimate performance benefit of creating an index.
     */
    protected static function estimateIndexBenefit(array $wheres, array $orders): string
    {
        $complexity = count($wheres) + count($orders);

        return match (true) {
            $complexity >= 3 => 'significant',
            $complexity >= 2 => 'moderate',
            default => 'minor',
        };
    }

    /**
     * Configure the query optimizer.
     */
    public static function configure(array $config): void
    {
        static::$config = array_merge(static::$config, $config);
    }

    /**
     * Enable or disable the optimizer.
     */
    public static function setEnabled(bool $enabled): void
    {
        static::$enabled = $enabled;
    }

    /**
     * Clear all tracked statistics.
     */
    public static function clearStats(): void
    {
        static::$queryStats = [];
        static::$indexSuggestions = [];
    }

    // Additional helper methods for statistics calculation...

    protected static function calculateAverageExecutionTime(): float
    {
        if (empty(static::$queryStats)) {
            return 0;
        }

        $totalTime = array_sum(array_column(static::$queryStats, 'total_time_ms'));
        $totalExecutions = array_sum(array_column(static::$queryStats, 'executions'));

        return $totalExecutions > 0 ? $totalTime / $totalExecutions : 0;
    }

    protected static function getSlowQueries(int $limit = 10): array
    {
        $slowQueries = array_filter(static::$queryStats, function ($stats) {
            return $stats['avg_time_ms'] > static::$config['slow_query_threshold_ms'];
        });

        uasort($slowQueries, function ($a, $b) {
            return $b['avg_time_ms'] <=> $a['avg_time_ms'];
        });

        return array_slice($slowQueries, 0, $limit, true);
    }

    protected static function getMostFrequentQueries(int $limit = 10): array
    {
        uasort(static::$queryStats, function ($a, $b) {
            return $b['executions'] <=> $a['executions'];
        });

        return array_slice(static::$queryStats, 0, $limit, true);
    }

    protected static function calculateCacheHitRate(): float
    {
        $totalHits = array_sum(array_column(static::$queryStats, 'cache_hits'));
        $totalExecutions = array_sum(array_column(static::$queryStats, 'executions'));

        return $totalExecutions > 0 ? $totalHits / $totalExecutions : 0;
    }

    protected static function logSlowQuery(string $queryHash, float $executionTimeMs, int $resultCount): void
    {
        Log::warning('Slow Firestore query detected', [
            'query_hash' => $queryHash,
            'execution_time_ms' => $executionTimeMs,
            'result_count' => $resultCount,
            'threshold_ms' => static::$config['slow_query_threshold_ms'],
        ]);
    }

    protected static function trackQuery(string $queryHash, string $collection, array $wheres, array $orders, ?int $limit): void
    {
        // Store query metadata for analysis
        // This could be enhanced to store more detailed query information
    }

    protected static function cleanupOldStats(): void
    {
        // Remove oldest 10% of tracked queries
        $removeCount = (int) (count(static::$queryStats) * 0.1);
        $oldestQueries = array_slice(static::$queryStats, 0, $removeCount, true);

        foreach (array_keys($oldestQueries) as $queryHash) {
            unset(static::$queryStats[$queryHash]);
        }
    }
}
