<?php

namespace JTD\FirebaseModels\Testing;

use JTD\FirebaseModels\Firestore\Batch\BatchManager;
use JTD\FirebaseModels\Firestore\Batch\BatchOperation;
use JTD\FirebaseModels\Firestore\Batch\BatchResult;
use JTD\FirebaseModels\Firestore\Batch\BatchValidator;

/**
 * Testing utilities for batch operations.
 */
class BatchTestHelper
{
    /**
     * Create test data for batch operations.
     */
    public static function createTestData(int $count = 10, array $template = []): array
    {
        $data = [];
        $defaultTemplate = [
            'title' => 'Test Document',
            'content' => 'Test content',
            'status' => 'active',
            'created_at' => now(),
        ];

        $template = array_merge($defaultTemplate, $template);

        for ($i = 1; $i <= $count; $i++) {
            $record = $template;
            
            // Add unique identifiers
            foreach ($record as $key => $value) {
                if (is_string($value) && strpos($value, '{i}') !== false) {
                    $record[$key] = str_replace('{i}', $i, $value);
                } elseif ($key === 'title' && $value === 'Test Document') {
                    $record[$key] = "Test Document {$i}";
                }
            }

            $data[] = $record;
        }

        return $data;
    }

    /**
     * Create test updates for batch operations.
     */
    public static function createTestUpdates(array $documentIds, array $updateData = []): array
    {
        $updates = [];
        $defaultUpdate = [
            'updated_at' => now(),
            'status' => 'updated',
        ];

        $updateData = array_merge($defaultUpdate, $updateData);

        foreach ($documentIds as $id) {
            $updates[$id] = $updateData;
        }

        return $updates;
    }

    /**
     * Assert batch result is successful.
     */
    public static function assertBatchSuccess(BatchResult $result, ?int $expectedOperations = null): void
    {
        if (!$result->isSuccess()) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                'Batch operation failed: ' . $result->getError()
            );
        }

        if ($expectedOperations !== null) {
            $actualOperations = $result->getOperationCount();
            if ($actualOperations !== $expectedOperations) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Expected {$expectedOperations} operations, got {$actualOperations}"
                );
            }
        }
    }

    /**
     * Assert batch result failed.
     */
    public static function assertBatchFailure(BatchResult $result, ?string $expectedError = null): void
    {
        if ($result->isSuccess()) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                'Expected batch operation to fail, but it succeeded'
            );
        }

        if ($expectedError !== null) {
            $actualError = $result->getError();
            if (strpos($actualError, $expectedError) === false) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Expected error to contain '{$expectedError}', got '{$actualError}'"
                );
            }
        }
    }

    /**
     * Assert batch performance metrics.
     */
    public static function assertBatchPerformance(BatchResult $result, array $expectations): void
    {
        $metrics = $result->getPerformanceMetrics();

        foreach ($expectations as $metric => $expected) {
            if (!isset($metrics[$metric])) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Performance metric '{$metric}' not found"
                );
            }

            $actual = $metrics[$metric];

            if (is_array($expected)) {
                // Range check
                [$min, $max] = $expected;
                if ($actual < $min || $actual > $max) {
                    throw new \PHPUnit\Framework\AssertionFailedError(
                        "Performance metric '{$metric}' ({$actual}) not in range [{$min}, {$max}]"
                    );
                }
            } else {
                // Exact check
                if ($actual !== $expected) {
                    throw new \PHPUnit\Framework\AssertionFailedError(
                        "Performance metric '{$metric}' expected {$expected}, got {$actual}"
                    );
                }
            }
        }
    }

    /**
     * Create a mock batch operation for testing.
     */
    public static function createMockBatch(array $operations = []): BatchOperation
    {
        $batch = BatchManager::create(['validate_operations' => false]);

        foreach ($operations as $operation) {
            switch ($operation['type']) {
                case 'create':
                    $batch->create(
                        $operation['collection'],
                        $operation['data'],
                        $operation['id'] ?? null
                    );
                    break;

                case 'update':
                    $batch->update(
                        $operation['collection'],
                        $operation['id'],
                        $operation['data']
                    );
                    break;

                case 'delete':
                    $batch->delete(
                        $operation['collection'],
                        $operation['id']
                    );
                    break;

                case 'set':
                    $batch->set(
                        $operation['collection'],
                        $operation['id'],
                        $operation['data'],
                        $operation['options'] ?? []
                    );
                    break;
            }
        }

        return $batch;
    }

    /**
     * Validate batch operation without executing.
     */
    public static function validateBatch(BatchOperation $batch): array
    {
        return BatchValidator::validateBatchOperation($batch);
    }

    /**
     * Create test data that will fail validation.
     */
    public static function createInvalidTestData(): array
    {
        return [
            'empty_collection' => [
                'type' => 'create',
                'collection' => '',
                'data' => ['test' => 'data']
            ],
            'invalid_id' => [
                'type' => 'update',
                'collection' => 'test',
                'id' => 'invalid/id',
                'data' => ['test' => 'data']
            ],
            'large_document' => [
                'type' => 'create',
                'collection' => 'test',
                'data' => ['large_field' => str_repeat('x', 2000000)] // 2MB
            ],
            'missing_data' => [
                'type' => 'create',
                'collection' => 'test'
                // Missing 'data' field
            ],
        ];
    }

    /**
     * Measure batch operation performance.
     */
    public static function measureBatchPerformance(callable $batchOperation): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $batchOperation();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        return [
            'duration_seconds' => $endTime - $startTime,
            'duration_ms' => round(($endTime - $startTime) * 1000, 2),
            'memory_used_bytes' => $endMemory - $startMemory,
            'memory_used_mb' => round(($endMemory - $startMemory) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'result' => $result,
        ];
    }

    /**
     * Create a stress test for batch operations.
     */
    public static function stressTest(string $collection, int $operationCount = 1000, array $options = []): array
    {
        $results = [];
        $chunkSize = $options['chunk_size'] ?? 100;
        $chunks = ceil($operationCount / $chunkSize);

        for ($i = 0; $i < $chunks; $i++) {
            $currentChunkSize = min($chunkSize, $operationCount - ($i * $chunkSize));
            $testData = static::createTestData($currentChunkSize);

            $performance = static::measureBatchPerformance(function () use ($collection, $testData) {
                return BatchManager::bulkInsert($collection, $testData);
            });

            $results[] = [
                'chunk' => $i + 1,
                'operations' => $currentChunkSize,
                'performance' => $performance,
            ];
        }

        return [
            'total_operations' => $operationCount,
            'total_chunks' => $chunks,
            'chunk_size' => $chunkSize,
            'chunks' => $results,
            'summary' => static::summarizeStressTest($results),
        ];
    }

    /**
     * Summarize stress test results.
     */
    protected static function summarizeStressTest(array $results): array
    {
        $durations = array_column(array_column($results, 'performance'), 'duration_ms');
        $memoryUsage = array_column(array_column($results, 'performance'), 'memory_used_mb');

        return [
            'total_duration_ms' => array_sum($durations),
            'average_duration_ms' => round(array_sum($durations) / count($durations), 2),
            'min_duration_ms' => min($durations),
            'max_duration_ms' => max($durations),
            'total_memory_mb' => array_sum($memoryUsage),
            'average_memory_mb' => round(array_sum($memoryUsage) / count($memoryUsage), 2),
            'peak_memory_mb' => max($memoryUsage),
        ];
    }

    /**
     * Clean up test data.
     */
    public static function cleanupTestData(string $collection, array $documentIds): BatchResult
    {
        return BatchManager::bulkDelete($collection, $documentIds, [
            'log_operations' => false
        ]);
    }

    /**
     * Generate random test data.
     */
    public static function generateRandomData(int $count = 10): array
    {
        $data = [];
        $statuses = ['active', 'inactive', 'pending', 'archived'];
        $categories = ['news', 'blog', 'tutorial', 'announcement'];

        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'title' => 'Random Document ' . uniqid(),
                'content' => 'Random content ' . str_repeat('x', rand(10, 100)),
                'status' => $statuses[array_rand($statuses)],
                'category' => $categories[array_rand($categories)],
                'score' => rand(1, 100),
                'tags' => array_slice($categories, 0, rand(1, 3)),
                'created_at' => now()->subDays(rand(0, 30)),
                'metadata' => [
                    'views' => rand(0, 1000),
                    'likes' => rand(0, 100),
                    'featured' => (bool) rand(0, 1),
                ],
            ];
        }

        return $data;
    }
}
