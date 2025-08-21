<?php

namespace JTD\FirebaseModels\Tests\TestSuites;

/**
 * IntegrationTestSuite is designed for integration tests that require
 * more comprehensive mocking and realistic data scenarios.
 */
abstract class IntegrationTestSuite extends BaseTestSuite
{
    protected bool $autoCleanup = true;

    /**
     * Configure integration test requirements.
     */
    protected function setUp(): void
    {
        // Set default requirements for integration tests
        $this->setTestRequirements([
            'document_count' => 200,
            'memory_constraint' => false,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();
    }

    /**
     * Create a realistic test dataset for integration testing.
     */
    protected function createTestDataset(array $collections = []): array
    {
        $dataset = [];
        
        foreach ($collections as $collection => $config) {
            $count = $config['count'] ?? 10;
            $factory = $config['factory'] ?? null;
            
            $dataset[$collection] = [];
            
            for ($i = 0; $i < $count; $i++) {
                $data = $factory ? $factory($i) : $this->generateDefaultData($collection, $i);
                $id = $data['id'] ?? 'doc_' . $i;
                
                $this->getFirestoreMock()->storeDocument($collection, $id, $data);
                $dataset[$collection][$id] = $data;
            }
        }
        
        return $dataset;
    }

    /**
     * Generate default test data for a collection.
     */
    protected function generateDefaultData(string $collection, int $index): array
    {
        return [
            'id' => $collection . '_' . $index,
            'name' => ucfirst($collection) . ' ' . $index,
            'index' => $index,
            'created_at' => now()->subMinutes($index),
            'updated_at' => now()->subMinutes($index / 2),
            'status' => $index % 2 === 0 ? 'active' : 'inactive',
            'category' => ['A', 'B', 'C'][$index % 3],
            'metadata' => [
                'source' => 'test',
                'batch' => floor($index / 5),
                'tags' => ['tag' . ($index % 3), 'tag' . ($index % 5)],
            ],
        ];
    }

    /**
     * Mock complex query scenarios.
     */
    protected function mockComplexQuery(string $collection, array $filters = [], array $orderBy = [], ?int $limit = null, ?array $testData = null): array
    {
        // If test data is provided, store it in the mock first
        if ($testData !== null) {
            foreach ($testData as $data) {
                $id = $data['id'] ?? uniqid();
                $this->getFirestoreMock()->storeDocument($collection, $id, $data);
            }
        }

        $documents = $this->getFirestoreMock()->getCollectionDocuments($collection);
        
        // Apply filters
        foreach ($filters as $filter) {
            $documents = array_filter($documents, function($doc) use ($filter) {
                return $this->applyFilter($doc, $filter);
            });
        }
        
        // Apply ordering
        if (!empty($orderBy)) {
            usort($documents, function($a, $b) use ($orderBy) {
                foreach ($orderBy as $order) {
                    $field = $order['field'];
                    $direction = $order['direction'] ?? 'asc';
                    
                    $aVal = $a[$field] ?? null;
                    $bVal = $b[$field] ?? null;
                    
                    $cmp = $aVal <=> $bVal;
                    if ($cmp !== 0) {
                        return $direction === 'desc' ? -$cmp : $cmp;
                    }
                }
                return 0;
            });
        }
        
        // Apply limit
        if ($limit !== null) {
            $documents = array_slice($documents, 0, $limit);
        }
        
        return array_values($documents);
    }

    /**
     * Apply a filter to a document.
     */
    protected function applyFilter($document, array $filter): bool
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $value = $filter['value'];

        // Handle both array and DocumentSnapshot formats
        if (is_array($document)) {
            $docValue = $document[$field] ?? null;
        } else {
            // Assume it's a DocumentSnapshot object
            $data = $document->data();
            $docValue = $data[$field] ?? null;
        }

        // Handle boolean comparisons with type coercion
        if (is_bool($value)) {
            // Convert stored value to boolean for comparison
            $docValueBool = $this->convertToBoolean($docValue);
            $value = (bool) $value;

            return match ($operator) {
                '=' => $docValueBool === $value,
                '!=' => $docValueBool !== $value,
                '==' => $docValueBool === $value,
                default => false,
            };
        }

        return match ($operator) {
            '=' => $docValue == $value,
            '!=' => $docValue != $value,
            '>' => $docValue > $value,
            '>=' => $docValue >= $value,
            '<' => $docValue < $value,
            '<=' => $docValue <= $value,
            'in' => is_array($value) && in_array($docValue, $value),
            'not-in' => is_array($value) && !in_array($docValue, $value),
            'array-contains' => is_array($docValue) && in_array($value, $docValue),
            'array-contains-any' => is_array($docValue) && !empty(array_intersect($docValue, $value)),
            default => false,
        };
    }

    /**
     * Convert a value to boolean using Laravel-style casting.
     */
    protected function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Simulate batch operations for testing.
     */
    protected function simulateBatchOperations(array $operations): array
    {
        $results = [];
        
        foreach ($operations as $operation) {
            $type = $operation['type'];
            $collection = $operation['collection'];
            $id = $operation['id'];
            $data = $operation['data'] ?? [];
            
            switch ($type) {
                case 'create':
                    $this->getFirestoreMock()->storeDocument($collection, $id, $data);
                    $results[] = ['type' => 'create', 'id' => $id, 'success' => true];
                    break;
                    
                case 'update':
                    $existing = $this->getFirestoreMock()->getDocument($collection, $id);
                    if ($existing) {
                        $merged = array_merge($existing, $data);
                        $this->getFirestoreMock()->storeDocument($collection, $id, $merged);
                        $results[] = ['type' => 'update', 'id' => $id, 'success' => true];
                    } else {
                        $results[] = ['type' => 'update', 'id' => $id, 'success' => false, 'error' => 'Document not found'];
                    }
                    break;
                    
                case 'delete':
                    $this->getFirestoreMock()->deleteDocument($collection, $id);
                    $results[] = ['type' => 'delete', 'id' => $id, 'success' => true];
                    break;
            }
        }
        
        return $results;
    }

    /**
     * Assert that a complex query returns expected results.
     */
    protected function assertQueryResults(string $collection, array $filters, array $expectedIds): void
    {
        $results = $this->mockComplexQuery($collection, $filters);
        $actualIds = array_column($results, 'id');
        
        $this->assertEquals(
            sort($expectedIds),
            sort($actualIds),
            "Query results do not match expected IDs"
        );
    }

    /**
     * Assert that batch operations completed successfully.
     */
    protected function assertBatchOperationsSuccessful(array $results): void
    {
        $failures = array_filter($results, fn($result) => !$result['success']);
        
        $this->assertEmpty(
            $failures,
            "Some batch operations failed: " . json_encode($failures)
        );
    }

    /**
     * Create test relationships between collections.
     */
    protected function createTestRelationships(array $relationships): void
    {
        foreach ($relationships as $relationship) {
            $parentCollection = $relationship['parent_collection'];
            $childCollection = $relationship['child_collection'];
            $parentId = $relationship['parent_id'];
            $childIds = $relationship['child_ids'];
            
            // Update parent with child references
            $parent = $this->getFirestoreMock()->getDocument($parentCollection, $parentId);
            if ($parent) {
                $parent['children'] = $childIds;
                $this->getFirestoreMock()->storeDocument($parentCollection, $parentId, $parent);
            }
            
            // Update children with parent reference
            foreach ($childIds as $childId) {
                $child = $this->getFirestoreMock()->getDocument($childCollection, $childId);
                if ($child) {
                    $child['parent_id'] = $parentId;
                    $this->getFirestoreMock()->storeDocument($childCollection, $childId, $child);
                }
            }
        }
    }

    /**
     * Create multiple test models for batch testing.
     */
    protected function createTestModels(string $modelClass, int $count = 5, array $baseData = []): array
    {
        $models = [];

        for ($i = 0; $i < $count; $i++) {
            $data = array_merge($baseData, [
                'id' => 'test-model-' . $i,
                'name' => "Test Model {$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Store in mock
            $this->getFirestoreMock()->storeDocument(
                $modelClass::getCollectionName(),
                $data['id'],
                $data
            );

            $models[] = new $modelClass($data);
        }

        return $models;
    }

    /**
     * Enable memory monitoring for performance-sensitive tests.
     */
    protected function enableMemoryMonitoring(): void
    {
        $this->memorySnapshots['test_start'] = memory_get_usage();
    }

    /**
     * Benchmark a callable and return execution time.
     */
    protected function benchmark(callable $callback): float
    {
        $startTime = microtime(true);
        $callback();
        return microtime(true) - $startTime;
    }

    /**
     * Check memory usage and fail if it exceeds threshold.
     */
    protected function assertMemoryUsageWithinThreshold(int $maxBytes): void
    {
        $currentUsage = memory_get_usage();

        $this->assertLessThanOrEqual(
            $maxBytes,
            $currentUsage,
            "Memory usage ({$this->formatBytes($currentUsage)}) exceeds threshold ({$this->formatBytes($maxBytes)})"
        );
    }

    /**
     * Clear test data from the mock.
     */
    protected function clearTestData(): void
    {
        \JTD\FirebaseModels\Tests\Helpers\FirestoreMock::clear();
    }

    /**
     * Format bytes for human-readable output.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get operations performed by the mock system.
     */
    protected function getPerformedOperations(): array
    {
        return $this->getFirestoreMock()->getOperations();
    }
}
