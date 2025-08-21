<?php

namespace JTD\FirebaseModels\Tests\TestSuites;

use JTD\FirebaseModels\Tests\Helpers\FirestoreMockFactory;

/**
 * UnitTestSuite is optimized for fast, isolated unit tests
 * with minimal memory footprint and maximum execution speed.
 */
abstract class UnitTestSuite extends BaseTestSuite
{
    protected string $mockType = FirestoreMockFactory::TYPE_ULTRA;
    protected bool $autoCleanup = true;

    /**
     * Configure unit test requirements for optimal performance.
     */
    protected function setUp(): void
    {
        // Set default requirements for unit tests
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();
    }

    /**
     * Create a test model with minimal data for unit testing.
     */
    protected function createTestModel(string $modelClass, array $data = []): object
    {
        $defaultData = [
            'id' => 'test_' . uniqid(),
            'name' => 'Test Model',
            'created_at' => now(),
        ];

        $modelData = array_merge($defaultData, $data);
        
        // Store in mock database
        $tempModel = new $modelClass();
        $this->getFirestoreMock()->storeDocument(
            $tempModel->getCollection(),
            $modelData['id'],
            $modelData
        );

        return new $modelClass($modelData);
    }

    /**
     * Create multiple test models for batch testing.
     */
    protected function createTestModels(string $modelClass, int $count = 5, array $baseData = []): array
    {
        $models = [];
        
        for ($i = 0; $i < $count; $i++) {
            $data = array_merge($baseData, [
                'id' => 'test_' . $i . '_' . uniqid(),
                'sequence' => $i,
            ]);
            
            $models[] = $this->createTestModel($modelClass, $data);
        }
        
        return $models;
    }

    /**
     * Assert that a document exists in the mock database.
     */
    protected function assertDocumentExists(string $collection, string $id): void
    {
        $this->assertTrue(
            $this->getFirestoreMock()->documentExists($collection, $id),
            "Document {$id} should exist in collection {$collection}"
        );
    }

    /**
     * Assert that a document does not exist in the mock database.
     */
    protected function assertDocumentNotExists(string $collection, string $id): void
    {
        $this->assertFalse(
            $this->getFirestoreMock()->documentExists($collection, $id),
            "Document {$id} should not exist in collection {$collection}"
        );
    }

    /**
     * Assert that a collection has the expected number of documents.
     */
    protected function assertCollectionCount(string $collection, int $expectedCount): void
    {
        $actualCount = $this->getFirestoreMock()->getCollectionCount($collection);
        $this->assertEquals(
            $expectedCount,
            $actualCount,
            "Collection {$collection} should have {$expectedCount} documents, but has {$actualCount}"
        );
    }

    /**
     * Get all operations performed on the mock database.
     */
    protected function getPerformedOperations(): array
    {
        return $this->getFirestoreMock()->getOperations();
    }

    /**
     * Get operations of a specific type.
     */
    protected function getOperationsByType(string $type): array
    {
        return $this->getFirestoreMock()->getOperationsByType($type);
    }

    /**
     * Assert that a specific operation was performed.
     */
    protected function assertOperationPerformed(string $type, string $collection, string $id): void
    {
        $operations = $this->getOperationsByType($type);
        
        $found = false;
        foreach ($operations as $operation) {
            if ($operation['collection'] === $collection && $operation['id'] === $id) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue(
            $found,
            "Operation {$type} should have been performed on {$collection}/{$id}"
        );
    }

    /**
     * Clear all test data from the mock database.
     */
    protected function clearTestData(): void
    {
        $this->clearFirestoreMocks();
    }

    /**
     * Enable memory monitoring for performance-sensitive tests.
     */
    protected function enableMemoryMonitoring(): void
    {
        $this->memorySnapshots['test_start'] = memory_get_usage();
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
     * Benchmark a callable and return execution time.
     */
    protected function benchmark(callable $callback): float
    {
        $startTime = microtime(true);
        $callback();
        return microtime(true) - $startTime;
    }

    /**
     * Assert that an operation completes within a time threshold.
     */
    protected function assertExecutionTimeWithinThreshold(callable $callback, float $maxSeconds): void
    {
        $executionTime = $this->benchmark($callback);
        
        $this->assertLessThanOrEqual(
            $maxSeconds,
            $executionTime,
            "Execution time ({$executionTime}s) exceeds threshold ({$maxSeconds}s)"
        );
    }
}
