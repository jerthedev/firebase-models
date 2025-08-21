<?php

namespace JTD\FirebaseModels\Tests\TestSuites;

/**
 * FeatureTestSuite is designed for feature tests that require
 * comprehensive end-to-end testing with full mock capabilities.
 */
abstract class FeatureTestSuite extends BaseTestSuite
{
    protected bool $autoCleanup = true;

    /**
     * Configure feature test requirements.
     */
    protected function setUp(): void
    {
        // Set default requirements for feature tests
        $this->setTestRequirements([
            'document_count' => 300,
            'memory_constraint' => false,
            'needs_full_mockery' => true,
        ]);

        parent::setUp();
    }

    /**
     * Create multiple test models for feature testing scenarios.
     */
    protected function createTestModels(string $modelClass, int $count = 5, array $overrides = []): array
    {
        $models = [];
        
        for ($i = 0; $i < $count; $i++) {
            $data = array_merge([
                'id' => "test-model-{$i}",
                'name' => "Test Model {$i}",
                'created_at' => now()->subMinutes($i),
            ], $overrides);
            
            $models[] = $this->createTestModel($modelClass, $data);
        }
        
        return $models;
    }

    /**
     * Assert that a model exists in the mocked Firestore.
     */
    protected function assertModelExistsInCollection(string $collection, string $id): void
    {
        $this->assertFirestoreDocumentExists($collection, $id);
    }

    /**
     * Assert that a model does not exist in the mocked Firestore.
     */
    protected function assertModelDoesNotExist(string $collection, string $id): void
    {
        $this->assertFirestoreDocumentDoesNotExist($collection, $id);
    }

    /**
     * Assert that a collection has a specific number of documents.
     */
    protected function assertCollectionHasCount(string $collection, int $expectedCount): void
    {
        $this->assertCollectionCount($collection, $expectedCount);
    }

    /**
     * Mock a complex query scenario for feature testing.
     */
    protected function mockComplexQueryScenario(string $collection, array $documents, array $filters = [], array $orders = []): void
    {
        $this->mockComplexQuery($collection, $filters, $orders, null, $documents);
    }

    /**
     * Mock complex query scenarios.
     */
    protected function mockComplexQuery(string $collection, array $filters = [], array $orderBy = [], ?int $limit = null, array $documents = []): array
    {
        $mock = $this->getFirestoreMock();

        // Store documents if provided
        foreach ($documents as $document) {
            $id = $document['id'] ?? uniqid();
            $mock->storeDocument($collection, $id, $document);
        }

        // Return the documents for further processing
        return $documents;
    }

    /**
     * Create test data for feature scenarios.
     */
    protected function createFeatureTestData(string $type, int $count = 10): array
    {
        $data = [];
        
        for ($i = 0; $i < $count; $i++) {
            switch ($type) {
                case 'products':
                    $data[] = [
                        'id' => "product-{$i}",
                        'name' => "Product {$i}",
                        'price' => rand(10, 100) + (rand(0, 99) / 100),
                        'active' => $i % 2 === 0,
                        'category_id' => rand(1, 5),
                        'created_at' => now()->subDays(rand(0, 30))->toISOString(),
                    ];
                    break;
                    
                case 'users':
                    $data[] = [
                        'id' => "user-{$i}",
                        'name' => "User {$i}",
                        'email' => "user{$i}@example.com",
                        'active' => $i % 3 !== 0,
                        'role' => $i < 3 ? 'admin' : 'user',
                        'created_at' => now()->subDays(rand(0, 60))->toISOString(),
                    ];
                    break;
                    
                case 'posts':
                    $data[] = [
                        'id' => "post-{$i}",
                        'title' => "Post Title {$i}",
                        'content' => "This is the content for post {$i}",
                        'published' => $i % 4 !== 0,
                        'author_id' => "user-" . rand(0, 4),
                        'views' => rand(0, 1000),
                        'created_at' => now()->subDays(rand(0, 90))->toISOString(),
                    ];
                    break;
                    
                default:
                    $data[] = [
                        'id' => "item-{$i}",
                        'name' => "Item {$i}",
                        'value' => $i,
                        'active' => true,
                        'created_at' => now()->subMinutes($i)->toISOString(),
                    ];
            }
        }
        
        return $data;
    }

    /**
     * Perform a feature test scenario with multiple operations.
     */
    protected function performFeatureScenario(string $scenarioName, callable $scenario): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $result = $scenario();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $metrics = [
            'scenario' => $scenarioName,
            'duration' => $endTime - $startTime,
            'memory_used' => $endMemory - $startMemory,
            'result' => $result,
        ];
        
        // Log performance if scenario takes too long
        if ($metrics['duration'] > 1.0) {
            error_log("Feature scenario '{$scenarioName}' took {$metrics['duration']}s");
        }
        
        return $metrics;
    }

    /**
     * Assert that a feature scenario completed within acceptable limits.
     */
    protected function assertFeaturePerformance(array $metrics, float $maxDuration = 2.0, int $maxMemory = 10 * 1024 * 1024): void
    {
        expect($metrics['duration'])->toBeLessThan($maxDuration, 
            "Feature scenario '{$metrics['scenario']}' took {$metrics['duration']}s, expected < {$maxDuration}s");
            
        expect($metrics['memory_used'])->toBeLessThan($maxMemory,
            "Feature scenario '{$metrics['scenario']}' used {$metrics['memory_used']} bytes, expected < {$maxMemory} bytes");
    }

    /**
     * Create a realistic dataset for testing complex feature scenarios.
     */
    protected function createRealisticDataset(string $collection, int $size = 100): array
    {
        $dataset = [];
        
        for ($i = 0; $i < $size; $i++) {
            $dataset[] = [
                'id' => "realistic-{$i}",
                'name' => "Realistic Item {$i}",
                'description' => "This is a realistic description for item {$i} with some detailed content.",
                'value' => rand(1, 1000),
                'category' => ['electronics', 'clothing', 'books', 'home', 'sports'][rand(0, 4)],
                'tags' => array_slice(['popular', 'new', 'sale', 'featured', 'limited'], 0, rand(1, 3)),
                'active' => rand(0, 100) > 20, // 80% active
                'rating' => rand(10, 50) / 10, // 1.0 to 5.0
                'created_at' => now()->subDays(rand(0, 365))->toISOString(),
                'updated_at' => now()->subDays(rand(0, 30))->toISOString(),
            ];
        }
        
        return $dataset;
    }

    /**
     * Test model relationships and associations.
     */
    protected function assertModelRelationships(array $models, string $relationshipType = 'belongs_to'): void
    {
        foreach ($models as $model) {
            expect($model)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModel::class);
            expect($model->exists)->toBeTrue();
            
            // Additional relationship-specific assertions can be added here
            switch ($relationshipType) {
                case 'belongs_to':
                    // Assert parent relationship exists
                    break;
                case 'has_many':
                    // Assert child relationships exist
                    break;
                case 'many_to_many':
                    // Assert pivot relationships exist
                    break;
            }
        }
    }

    /**
     * Clean up feature test data with comprehensive cleanup.
     */
    protected function cleanupFeatureData(): void
    {
        // Clear all test data
        $this->clearTestData();
        
        // Force garbage collection for feature tests
        $this->forceGarbageCollection();
        
        // Reset any static state
        $this->resetStaticState();
    }

    /**
     * Reset static state that might persist between feature tests.
     */
    protected function resetStaticState(): void
    {
        // Clear any static caches or state that might affect feature tests
        if (method_exists(\JTD\FirebaseModels\Firestore\FirestoreModel::class, 'clearBootedModels')) {
            \JTD\FirebaseModels\Firestore\FirestoreModel::clearBootedModels();
        }
    }

    /**
     * Force garbage collection for memory-intensive feature tests.
     */
    protected function forceGarbageCollection(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Clear all test data.
     */
    protected function clearTestData(): void
    {
        \JTD\FirebaseModels\Tests\Helpers\FirestoreMock::clear();
    }

    /**
     * Assert that a Firestore operation was called.
     */
    protected function assertFirestoreOperationCalled(string $operation, string $collection): void
    {
        $operations = $this->getFirestoreMock()->getOperations();
        $found = false;

        foreach ($operations as $op) {
            if ($op['operation'] === $operation && $op['collection'] === $collection) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, "Expected Firestore {$operation} operation on {$collection} was not called");
    }

    /**
     * Mock Firestore get operations.
     */
    protected function mockFirestoreGet(string $collection, string $id, array $data): void
    {
        $this->getFirestoreMock()->storeDocument($collection, $id, $data);
    }

    /**
     * Mock Firestore update operations.
     */
    protected function mockFirestoreUpdate(string $collection, string $id): void
    {
        // The mock is already set up to handle update operations
        // No additional setup needed
    }

    /**
     * Get operations performed by the mock system.
     */
    protected function getPerformedOperations(): array
    {
        return $this->getFirestoreMock()->getOperations();
    }
}
