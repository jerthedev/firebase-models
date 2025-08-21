<?php

use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use JTD\FirebaseModels\Tests\Helpers\LightweightFirestoreMock;
use JTD\FirebaseModels\Tests\Helpers\UltraLightFirestoreMock;

describe('Memory Optimization', function () {
    describe('Mock System Memory Usage', function () {
        it('can use ultra-light mock for memory efficiency', function () {
            // Initialize ultra-light mock directly
            UltraLightFirestoreMock::clear();
            UltraLightFirestoreMock::initialize();
            
            // Get initial memory usage
            $initialMemory = memory_get_usage(true);
            
            // Create many documents to test memory usage
            for ($i = 0; $i < 100; $i++) {
                UltraLightFirestoreMock::createDocument('test_collection', "doc_{$i}", [
                    'name' => "Test Document {$i}",
                    'value' => $i,
                    'active' => $i % 2 === 0,
                ]);
            }
            
            // Check memory usage after operations
            $afterMemory = memory_get_usage(true);
            $memoryUsed = $afterMemory - $initialMemory;
            
            // Memory usage should be reasonable (less than 5MB for 100 documents)
            expect($memoryUsed)->toBeLessThan(5 * 1024 * 1024);
            
            // Verify documents were created
            $mock = UltraLightFirestoreMock::getInstance();
            expect(count($mock->getDocuments()['test_collection']))->toBe(100);
            
            // Clear and verify cleanup
            UltraLightFirestoreMock::clear();
            expect($mock->getDocuments())->toBeEmpty();
        });

        it('properly cleans up memory between tests', function () {
            // Get initial memory
            $initialMemory = memory_get_usage(true);

            // Initialize and create some test data
            UltraLightFirestoreMock::initialize();
            for ($i = 0; $i < 50; $i++) {
                UltraLightFirestoreMock::createDocument('memory_test', "doc_{$i}", [
                    'data' => str_repeat('x', 1000), // 1KB per document
                ]);
            }

            // Force cleanup
            UltraLightFirestoreMock::clear();
            forceGarbageCollection();

            // Memory should be close to initial (within 1MB)
            $finalMemory = memory_get_usage(true);
            $memoryDiff = abs($finalMemory - $initialMemory);

            expect($memoryDiff)->toBeLessThan(1 * 1024 * 1024);
        });

        it('can handle large datasets without memory exhaustion', function () {
            // Initialize ultra-light mock directly
            UltraLightFirestoreMock::clear();
            UltraLightFirestoreMock::initialize();
            
            $initialMemory = memory_get_usage(true);
            
            // Create a large dataset
            for ($i = 0; $i < 1000; $i++) {
                UltraLightFirestoreMock::createDocument('large_test', "doc_{$i}", [
                    'id' => $i,
                    'name' => "Document {$i}",
                    'description' => "This is test document number {$i}",
                    'tags' => ['tag1', 'tag2', 'tag3'],
                    'metadata' => [
                        'created' => date('Y-m-d H:i:s'),
                        'version' => 1,
                        'active' => true,
                    ],
                ]);
                
                // Periodically check memory usage
                if ($i % 100 === 0) {
                    $currentMemory = memory_get_usage(true);
                    $memoryUsed = $currentMemory - $initialMemory;
                    
                    // Should not exceed 10MB even with 1000 documents
                    expect($memoryUsed)->toBeLessThan(10 * 1024 * 1024);
                }
            }
            
            // Verify all documents were created
            $mock = UltraLightFirestoreMock::getInstance();
            expect(count($mock->getDocuments()['large_test']))->toBe(1000);
            
            // Test querying the large dataset
            $results = $mock->executeQuery('large_test', [
                ['field' => 'metadata.active', 'operator' => '==', 'value' => true]
            ]);
            
            expect(count($results))->toBe(1000);
        });
    });

    describe('Mock Cleanup Verification', function () {
        it('properly resets static instances', function () {
            // Create some data
            FirestoreMock::createDocument('test', 'doc1', ['name' => 'Test']);
            
            $mock1 = FirestoreMock::getInstance();
            expect($mock1->getDocuments())->not->toBeEmpty();
            
            // Clear mocks
            FirestoreMock::clear();
            
            // Get new instance - should be clean
            $mock2 = FirestoreMock::getInstance();
            expect($mock2->getDocuments())->toBeEmpty();
            
            // Should be a new instance
            expect($mock1)->not->toBe($mock2);
        });

        it('clears Laravel container bindings', function () {
            // Set up mocking
            FirestoreMock::initialize();
            
            // Verify bindings exist
            expect(app()->bound(\Google\Cloud\Firestore\FirestoreClient::class))->toBeTrue();
            expect(app()->bound(\Kreait\Firebase\Contract\Firestore::class))->toBeTrue();
            
            // Clear mocks
            FirestoreMock::clear();
            
            // Bindings should be cleared
            expect(app()->bound(\Google\Cloud\Firestore\FirestoreClient::class))->toBeFalse();
            expect(app()->bound(\Kreait\Firebase\Contract\Firestore::class))->toBeFalse();
        });
    });

    describe('Memory Leak Prevention', function () {
        it('does not accumulate mock objects', function () {
            $initialMemory = memory_get_usage(true);

            // Run multiple test cycles
            for ($cycle = 0; $cycle < 10; $cycle++) {
                // Set up mocks
                UltraLightFirestoreMock::initialize();

                // Create some data
                for ($i = 0; $i < 10; $i++) {
                    UltraLightFirestoreMock::createDocument('cycle_test', "doc_{$cycle}_{$i}", [
                        'cycle' => $cycle,
                        'index' => $i,
                    ]);
                }

                // Clear mocks and force cleanup
                UltraLightFirestoreMock::clear();
                forceGarbageCollection();
            }

            $finalMemory = memory_get_usage(true);
            $memoryGrowth = $finalMemory - $initialMemory;

            // Memory growth should be minimal (less than 2MB after 10 cycles)
            expect($memoryGrowth)->toBeLessThan(2 * 1024 * 1024);
        });
    });
});

// Helper method to access protected method
function forceGarbageCollection(): void
{
    // Close Mockery to free mock objects
    if (class_exists(\Mockery::class)) {
        \Mockery::close();
    }

    // Force PHP garbage collection
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}
