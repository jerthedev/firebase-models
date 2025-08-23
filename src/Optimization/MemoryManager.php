<?php

namespace JTD\FirebaseModels\Optimization;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Memory Manager for Firestore operations.
 * 
 * Monitors memory usage, prevents memory leaks, and optimizes
 * resource allocation for large-scale Firestore operations.
 */
class MemoryManager
{
    protected static array $memoryStats = [];
    protected static array $resourcePools = [];
    protected static array $config = [
        'memory_limit_mb' => 128,
        'warning_threshold_mb' => 100,
        'cleanup_threshold_mb' => 110,
        'enable_monitoring' => true,
        'enable_auto_cleanup' => true,
        'log_memory_warnings' => true,
        'track_allocations' => true,
    ];

    protected static array $allocations = [];
    protected static int $allocationCounter = 0;

    /**
     * Monitor memory usage for an operation.
     */
    public static function monitor(string $operation, callable $callback): mixed
    {
        if (!static::$config['enable_monitoring']) {
            return $callback();
        }

        $startMemory = memory_get_usage(true);
        $startPeakMemory = memory_get_peak_usage(true);
        $startTime = microtime(true);

        try {
            $result = $callback();
            
            $endMemory = memory_get_usage(true);
            $endPeakMemory = memory_get_peak_usage(true);
            $endTime = microtime(true);

            static::recordMemoryStats($operation, [
                'start_memory_mb' => $startMemory / 1024 / 1024,
                'end_memory_mb' => $endMemory / 1024 / 1024,
                'memory_delta_mb' => ($endMemory - $startMemory) / 1024 / 1024,
                'peak_memory_mb' => $endPeakMemory / 1024 / 1024,
                'peak_delta_mb' => ($endPeakMemory - $startPeakMemory) / 1024 / 1024,
                'duration_ms' => ($endTime - $startTime) * 1000,
                'timestamp' => now()->toISOString(),
            ]);

            static::checkMemoryThresholds($endMemory);

            return $result;

        } catch (\Throwable $e) {
            static::handleMemoryError($operation, $e);
            throw $e;
        }
    }

    /**
     * Allocate tracked memory for large operations.
     */
    public static function allocate(string $context, int $sizeBytes): string
    {
        if (!static::$config['track_allocations']) {
            return uniqid('alloc_');
        }

        $allocationId = 'alloc_' . (++static::$allocationCounter);
        $currentMemory = memory_get_usage(true);

        static::$allocations[$allocationId] = [
            'context' => $context,
            'size_bytes' => $sizeBytes,
            'size_mb' => $sizeBytes / 1024 / 1024,
            'allocated_at' => microtime(true),
            'memory_before' => $currentMemory,
            'active' => true,
        ];

        // Check if allocation would exceed limits
        $projectedMemory = $currentMemory + $sizeBytes;
        if ($projectedMemory > static::$config['memory_limit_mb'] * 1024 * 1024) {
            static::triggerMemoryCleanup();
        }

        return $allocationId;
    }

    /**
     * Deallocate tracked memory.
     */
    public static function deallocate(string $allocationId): bool
    {
        if (!isset(static::$allocations[$allocationId])) {
            return false;
        }

        $allocation = &static::$allocations[$allocationId];
        $allocation['active'] = false;
        $allocation['deallocated_at'] = microtime(true);
        $allocation['lifetime_ms'] = ($allocation['deallocated_at'] - $allocation['allocated_at']) * 1000;

        // Clean up old allocations periodically
        static::cleanupOldAllocations();

        return true;
    }

    /**
     * Process large collections in memory-efficient chunks.
     */
    public static function processInChunks(Collection $collection, int $chunkSize, callable $processor): array
    {
        $results = [];
        $totalItems = $collection->count();
        $processedItems = 0;

        $allocationId = static::allocate('chunk_processing', $chunkSize * 1024); // Estimate 1KB per item

        try {
            foreach ($collection->chunk($chunkSize) as $chunkIndex => $chunk) {
                $chunkResult = static::monitor("chunk_{$chunkIndex}", function () use ($chunk, $processor) {
                    return $processor($chunk);
                });

                $results[] = $chunkResult;
                $processedItems += $chunk->count();

                // Force garbage collection between chunks
                if (static::$config['enable_auto_cleanup']) {
                    static::forceGarbageCollection();
                }

                // Check memory usage
                $currentMemory = memory_get_usage(true) / 1024 / 1024;
                if ($currentMemory > static::$config['cleanup_threshold_mb']) {
                    static::triggerMemoryCleanup();
                }

                // Log progress for large operations
                if ($totalItems > 1000 && $chunkIndex % 10 === 0) {
                    Log::info('Memory-efficient processing progress', [
                        'processed' => $processedItems,
                        'total' => $totalItems,
                        'progress_percent' => round(($processedItems / $totalItems) * 100, 2),
                        'current_memory_mb' => $currentMemory,
                    ]);
                }
            }

        } finally {
            static::deallocate($allocationId);
        }

        return $results;
    }

    /**
     * Create a memory-efficient resource pool.
     */
    public static function createResourcePool(string $poolName, int $maxSize, callable $factory): void
    {
        static::$resourcePools[$poolName] = [
            'max_size' => $maxSize,
            'factory' => $factory,
            'pool' => [],
            'active' => [],
            'stats' => [
                'created' => 0,
                'reused' => 0,
                'destroyed' => 0,
                'peak_usage' => 0,
            ],
        ];
    }

    /**
     * Get resource from pool.
     */
    public static function getResource(string $poolName): mixed
    {
        if (!isset(static::$resourcePools[$poolName])) {
            throw new \InvalidArgumentException("Resource pool '{$poolName}' does not exist");
        }

        $pool = &static::$resourcePools[$poolName];

        // Try to reuse from pool
        if (!empty($pool['pool'])) {
            $resource = array_pop($pool['pool']);
            $resourceId = uniqid('resource_');
            $pool['active'][$resourceId] = $resource;
            $pool['stats']['reused']++;
            return $resource;
        }

        // Create new resource if under limit
        if (count($pool['active']) < $pool['max_size']) {
            $resource = $pool['factory']();
            $resourceId = uniqid('resource_');
            $pool['active'][$resourceId] = $resource;
            $pool['stats']['created']++;
            $pool['stats']['peak_usage'] = max($pool['stats']['peak_usage'], count($pool['active']));
            return $resource;
        }

        throw new \RuntimeException("Resource pool '{$poolName}' is at maximum capacity");
    }

    /**
     * Return resource to pool.
     */
    public static function returnResource(string $poolName, mixed $resource): bool
    {
        if (!isset(static::$resourcePools[$poolName])) {
            return false;
        }

        $pool = &static::$resourcePools[$poolName];

        // Find and remove from active
        $resourceId = array_search($resource, $pool['active'], true);
        if ($resourceId !== false) {
            unset($pool['active'][$resourceId]);
            
            // Add back to pool if there's space
            if (count($pool['pool']) < $pool['max_size']) {
                $pool['pool'][] = $resource;
                return true;
            }
        }

        // Destroy resource if pool is full
        $pool['stats']['destroyed']++;
        return true;
    }

    /**
     * Get memory statistics.
     */
    public static function getMemoryStats(): array
    {
        return [
            'current_usage_mb' => memory_get_usage(true) / 1024 / 1024,
            'peak_usage_mb' => memory_get_peak_usage(true) / 1024 / 1024,
            'limit_mb' => static::$config['memory_limit_mb'],
            'usage_percentage' => (memory_get_usage(true) / 1024 / 1024) / static::$config['memory_limit_mb'] * 100,
            'operations_tracked' => count(static::$memoryStats),
            'active_allocations' => count(array_filter(static::$allocations, fn($a) => $a['active'])),
            'total_allocations' => count(static::$allocations),
            'resource_pools' => count(static::$resourcePools),
            'recent_operations' => array_slice(static::$memoryStats, -10),
        ];
    }

    /**
     * Get allocation details.
     */
    public static function getAllocationStats(): array
    {
        $activeAllocations = array_filter(static::$allocations, fn($a) => $a['active']);
        $totalActiveMemory = array_sum(array_column($activeAllocations, 'size_bytes'));

        return [
            'active_allocations' => count($activeAllocations),
            'total_allocations' => count(static::$allocations),
            'active_memory_mb' => $totalActiveMemory / 1024 / 1024,
            'allocations_by_context' => static::groupAllocationsByContext(),
            'longest_lived_allocations' => static::getLongestLivedAllocations(),
            'largest_allocations' => static::getLargestAllocations(),
        ];
    }

    /**
     * Get resource pool statistics.
     */
    public static function getResourcePoolStats(): array
    {
        $stats = [];
        
        foreach (static::$resourcePools as $poolName => $pool) {
            $stats[$poolName] = [
                'max_size' => $pool['max_size'],
                'active_count' => count($pool['active']),
                'pool_count' => count($pool['pool']),
                'utilization_percent' => (count($pool['active']) / $pool['max_size']) * 100,
                'stats' => $pool['stats'],
            ];
        }

        return $stats;
    }

    /**
     * Trigger memory cleanup.
     */
    public static function triggerMemoryCleanup(): void
    {
        if (!static::$config['enable_auto_cleanup']) {
            return;
        }

        $beforeMemory = memory_get_usage(true);

        // Clean up old allocations
        static::cleanupOldAllocations();

        // Clean up old memory stats
        static::cleanupOldMemoryStats();

        // Force garbage collection
        static::forceGarbageCollection();

        // Clean up resource pools
        static::cleanupResourcePools();

        $afterMemory = memory_get_usage(true);
        $freedMemory = ($beforeMemory - $afterMemory) / 1024 / 1024;

        if (static::$config['log_memory_warnings']) {
            Log::info('Memory cleanup completed', [
                'freed_memory_mb' => $freedMemory,
                'before_memory_mb' => $beforeMemory / 1024 / 1024,
                'after_memory_mb' => $afterMemory / 1024 / 1024,
            ]);
        }
    }

    /**
     * Force garbage collection.
     */
    public static function forceGarbageCollection(): int
    {
        if (function_exists('gc_collect_cycles')) {
            return gc_collect_cycles();
        }
        return 0;
    }

    /**
     * Configure memory manager.
     */
    public static function configure(array $config): void
    {
        static::$config = array_merge(static::$config, $config);
    }

    /**
     * Reset all tracking data.
     */
    public static function reset(): void
    {
        static::$memoryStats = [];
        static::$allocations = [];
        static::$resourcePools = [];
        static::$allocationCounter = 0;
    }

    // Protected helper methods

    protected static function recordMemoryStats(string $operation, array $stats): void
    {
        static::$memoryStats[] = array_merge(['operation' => $operation], $stats);

        // Keep only recent stats to prevent memory bloat
        if (count(static::$memoryStats) > 1000) {
            static::$memoryStats = array_slice(static::$memoryStats, -500);
        }
    }

    protected static function checkMemoryThresholds(int $currentMemory): void
    {
        $currentMemoryMb = $currentMemory / 1024 / 1024;

        if ($currentMemoryMb > static::$config['warning_threshold_mb']) {
            if (static::$config['log_memory_warnings']) {
                Log::warning('Memory usage approaching limit', [
                    'current_memory_mb' => $currentMemoryMb,
                    'warning_threshold_mb' => static::$config['warning_threshold_mb'],
                    'limit_mb' => static::$config['memory_limit_mb'],
                ]);
            }
        }

        if ($currentMemoryMb > static::$config['cleanup_threshold_mb']) {
            static::triggerMemoryCleanup();
        }
    }

    protected static function handleMemoryError(string $operation, \Throwable $error): void
    {
        $currentMemory = memory_get_usage(true) / 1024 / 1024;
        
        Log::error('Memory error during operation', [
            'operation' => $operation,
            'error' => $error->getMessage(),
            'current_memory_mb' => $currentMemory,
            'memory_limit_mb' => static::$config['memory_limit_mb'],
        ]);

        // Attempt emergency cleanup
        static::triggerMemoryCleanup();
    }

    protected static function cleanupOldAllocations(): void
    {
        $cutoffTime = microtime(true) - 3600; // 1 hour ago
        
        static::$allocations = array_filter(static::$allocations, function ($allocation) use ($cutoffTime) {
            return $allocation['active'] || $allocation['allocated_at'] > $cutoffTime;
        });
    }

    protected static function cleanupOldMemoryStats(): void
    {
        if (count(static::$memoryStats) > 500) {
            static::$memoryStats = array_slice(static::$memoryStats, -250);
        }
    }

    protected static function cleanupResourcePools(): void
    {
        foreach (static::$resourcePools as $poolName => &$pool) {
            // Clear unused resources from pool
            $pool['pool'] = array_slice($pool['pool'], 0, $pool['max_size'] / 2);
        }
    }

    protected static function groupAllocationsByContext(): array
    {
        $grouped = [];
        
        foreach (static::$allocations as $allocation) {
            $context = $allocation['context'];
            if (!isset($grouped[$context])) {
                $grouped[$context] = ['count' => 0, 'total_mb' => 0];
            }
            $grouped[$context]['count']++;
            $grouped[$context]['total_mb'] += $allocation['size_mb'];
        }

        return $grouped;
    }

    protected static function getLongestLivedAllocations(int $limit = 10): array
    {
        $allocations = static::$allocations;
        $currentTime = microtime(true);

        foreach ($allocations as &$allocation) {
            if ($allocation['active']) {
                $allocation['lifetime_ms'] = ($currentTime - $allocation['allocated_at']) * 1000;
            }
        }

        usort($allocations, fn($a, $b) => ($b['lifetime_ms'] ?? 0) <=> ($a['lifetime_ms'] ?? 0));

        return array_slice($allocations, 0, $limit);
    }

    protected static function getLargestAllocations(int $limit = 10): array
    {
        $allocations = static::$allocations;
        usort($allocations, fn($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);
        return array_slice($allocations, 0, $limit);
    }
}
