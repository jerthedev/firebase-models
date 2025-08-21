<?php

namespace JTD\FirebaseModels\Tests\Performance;

use JTD\FirebaseModels\Tests\TestSuites\PerformanceTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive Memory Optimization Performance Test
 * 
 * Migrated from:
 * - tests/Unit/MemoryOptimizationTest.php
 * 
 * Uses new PerformanceTestSuite for comprehensive memory and performance monitoring.
 */

class MemoryOptimizationTest extends PerformanceTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for memory optimization testing
        $this->setTestRequirements([
            'document_count' => 1000,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        // Set custom memory thresholds for optimization testing
        $this->setMemoryThresholds([
            'warning' => 10 * 1024 * 1024,  // 10MB
            'critical' => 20 * 1024 * 1024, // 20MB
        ]);

        parent::setUp();
    }

    // ========================================
    // MOCK SYSTEM MEMORY USAGE TESTS
    // ========================================

    #[Test]
    public function it_uses_firestore_mock_for_memory_efficiency()
    {
        // Initialize firestore mock directly
        FirestoreMock::clear();
        FirestoreMock::initialize();

        $this->addPerformanceCheckpoint('firestore_mock_init');

        // Test memory usage with document creation
        $results = $this->measureOperation('firestore_mock_document_creation', function () {
            for ($i = 0; $i < 100; $i++) {
                $testData = TestDataFactory::createUser([
                    'id' => "doc_{$i}",
                    'name' => "Test Document {$i}",
                    'value' => $i,
                    'active' => $i % 2 === 0,
                ]);

                FirestoreMock::createDocument('test_collection', "doc_{$i}", $testData);
            }

            return FirestoreMock::getInstance();
        });

        $this->addPerformanceCheckpoint('firestore_mock_complete');

        // Verify documents were created
        $mock = $results;
        expect(count($mock->getDocuments()['test_collection']))->toBe(100);

        // Assert performance within limits (should be very fast and memory efficient)
        $this->assertPerformanceWithinLimits('firestore_mock_document_creation', 0.1, 5 * 1024 * 1024);

        // Clear and verify cleanup
        FirestoreMock::clear();
        expect($mock->getDocuments())->toBeEmpty();
    }

    #[Test]
    public function it_properly_cleans_up_memory_between_tests()
    {
        $this->addPerformanceCheckpoint('cleanup_test_start');
        
        // Test memory cleanup efficiency
        $cleanupResults = $this->measureOperation('memory_cleanup_test', function () {
            // Initialize and create test data
            FirestoreMock::initialize();

            for ($i = 0; $i < 50; $i++) {
                $testData = TestDataFactory::createPost([
                    'id' => "doc_{$i}",
                    'data' => str_repeat('x', 1000), // 1KB per document
                ]);

                FirestoreMock::createDocument('memory_test', "doc_{$i}", $testData);
            }

            // Force cleanup
            FirestoreMock::clear();
            $this->forceGarbageCollection();

            return 'cleanup_complete';
        });
        
        $this->addPerformanceCheckpoint('cleanup_test_complete');
        
        // Assert cleanup performance (allow more memory for cleanup operations)
        $this->assertPerformanceWithinLimits('memory_cleanup_test', 0.1, 8 * 1024 * 1024);
        
        // Verify memory is properly cleaned up
        $this->assertMemoryWithinThresholds();
    }

    #[Test]
    public function it_handles_large_datasets_without_memory_exhaustion()
    {
        // Initialize firestore mock
        FirestoreMock::clear();
        FirestoreMock::initialize();

        $this->addPerformanceCheckpoint('large_dataset_start');

        // Test large dataset handling
        $largeDatasetResults = $this->measureOperation('large_dataset_creation', function () {
            for ($i = 0; $i < 1000; $i++) {
                $testData = TestDataFactory::createProduct([
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

                FirestoreMock::createDocument('large_test', "doc_{$i}", $testData);

                // Add checkpoint every 200 documents
                if ($i % 200 === 0) {
                    $this->addPerformanceCheckpoint("large_dataset_checkpoint_{$i}");
                }
            }

            return FirestoreMock::getInstance();
        });

        $this->addPerformanceCheckpoint('large_dataset_complete');

        // Verify all documents were created
        $mock = $largeDatasetResults;
        expect(count($mock->getDocuments()['large_test']))->toBe(1000);

        // Assert performance within limits (should handle 1000 documents efficiently)
        $this->assertPerformanceWithinLimits('large_dataset_creation', 1.0, 15 * 1024 * 1024);

        // Test query performance on large dataset
        $queryResults = $this->measureOperation('large_dataset_query', function () use ($mock) {
            $documents = $mock->getDocuments()['large_test'];

            // Simulate filtering
            $filtered = array_filter($documents, function ($doc) {
                return ($doc['metadata']['active'] ?? false) === true && $doc['id'] % 2 === 0;
            });

            return $filtered;
        });

        // Query should be fast even on large dataset
        $this->assertPerformanceWithinLimits('large_dataset_query', 0.01, 1 * 1024 * 1024);

        // Cleanup
        FirestoreMock::clear();
    }

    // ========================================
    // MOCK CLEANUP VERIFICATION TESTS
    // ========================================

    #[Test]
    public function it_properly_resets_static_instances()
    {
        $this->addPerformanceCheckpoint('static_reset_start');
        
        // Test static instance reset performance
        $resetResults = $this->measureOperation('static_instance_reset', function () {
            // Create some data
            $testData = TestDataFactory::createUser(['name' => 'Test']);
            FirestoreMock::createDocument('test', 'doc1', $testData);
            
            $mock1 = FirestoreMock::getInstance();
            expect($mock1->getDocuments())->not->toBeEmpty();
            
            // Clear mocks
            FirestoreMock::clear();
            
            // Get new instance - should be clean
            $mock2 = FirestoreMock::getInstance();
            expect($mock2->getDocuments())->toBeEmpty();
            
            // Should be a new instance
            expect($mock1)->not->toBe($mock2);
            
            return [$mock1, $mock2];
        });
        
        $this->addPerformanceCheckpoint('static_reset_complete');
        
        // Static reset should be very fast (allow more time for instance recreation)
        $this->assertPerformanceWithinLimits('static_instance_reset', 0.05, 1024 * 1024);
    }

    #[Test]
    public function it_clears_laravel_container_bindings_efficiently()
    {
        $this->addPerformanceCheckpoint('container_binding_start');
        
        // Test container binding cleanup performance
        $bindingResults = $this->measureOperation('container_binding_cleanup', function () {
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
            
            return 'bindings_cleared';
        });
        
        $this->addPerformanceCheckpoint('container_binding_complete');
        
        // Container binding operations should be fast
        $this->assertPerformanceWithinLimits('container_binding_cleanup', 0.05, 1 * 1024 * 1024);
    }

    // ========================================
    // MEMORY LEAK PREVENTION TESTS
    // ========================================

    #[Test]
    public function it_prevents_mock_object_accumulation()
    {
        $this->addPerformanceCheckpoint('leak_prevention_start');
        
        // Test memory leak prevention over multiple cycles
        $leakPreventionResults = $this->measureOperation('memory_leak_prevention', function () {
            // Run multiple test cycles
            for ($cycle = 0; $cycle < 10; $cycle++) {
                // Set up mocks
                FirestoreMock::initialize();

                // Create some data
                for ($i = 0; $i < 10; $i++) {
                    $testData = TestDataFactory::createUser([
                        'cycle' => $cycle,
                        'index' => $i,
                    ]);

                    FirestoreMock::createDocument('cycle_test', "doc_{$cycle}_{$i}", $testData);
                }

                // Clear mocks and force cleanup
                FirestoreMock::clear();
                $this->forceGarbageCollection();

                // Add checkpoint every few cycles
                if ($cycle % 3 === 0) {
                    $this->addPerformanceCheckpoint("leak_prevention_cycle_{$cycle}");
                }
            }

            return 'cycles_complete';
        });
        
        $this->addPerformanceCheckpoint('leak_prevention_complete');
        
        // Memory leak prevention should be efficient (allow more memory for mock operations)
        $this->assertPerformanceWithinLimits('memory_leak_prevention', 0.5, 8 * 1024 * 1024);
        
        // Overall memory usage should be within thresholds
        $this->assertMemoryWithinThresholds();
    }

    // ========================================
    // PERFORMANCE COMPARISON TESTS
    // ========================================

    #[Test]
    public function it_tests_firestore_mock_performance()
    {
        $this->addPerformanceCheckpoint('mock_performance_start');

        // Test FirestoreMock performance
        $mockResults = $this->measureOperation('firestore_mock_test', function () {
            FirestoreMock::clear();
            FirestoreMock::initialize();

            for ($i = 0; $i < 100; $i++) {
                $testData = TestDataFactory::createUser(['id' => "test_{$i}"]);
                FirestoreMock::createDocument('comparison', "test_{$i}", $testData);
            }

            FirestoreMock::clear();
            return 'firestore_mock_complete';
        });

        $this->addPerformanceCheckpoint('mock_performance_complete');

        // Verify mock performs reasonably well
        $mockOp = $this->performanceMetrics['operations']['firestore_mock_test'];

        // Operation should complete within reasonable time limits
        expect($mockOp['duration'])->toBeLessThanOrEqual(0.2); // 200ms max

        // Memory usage should be reasonable (allow both positive and negative deltas)
        expect(abs($mockOp['memory_delta']))->toBeLessThanOrEqual(5 * 1024 * 1024); // 5MB max

        // Should be within reasonable limits
        $this->assertPerformanceWithinLimits('firestore_mock_test', 0.2, 5 * 1024 * 1024);
    }

    #[Test]
    public function it_generates_comprehensive_performance_report()
    {
        // Perform various operations to generate metrics
        $this->performBulkOperations('performance_test', 200);
        
        // Get performance report
        $report = $this->getPerformanceReport();
        
        // Verify report structure
        expect($report)->toHaveKeys(['summary', 'operations', 'checkpoints', 'mock_type', 'memory_stats']);
        expect($report['summary'])->toHaveKeys(['total_time', 'memory_delta', 'peak_memory_delta']);
        expect($report['operations'])->toHaveKey('bulk_operations_200');
        
        // Verify performance is within acceptable limits
        expect($report['summary']['total_time'])->toBeLessThan(2.0);
        expect($report['summary']['memory_delta'])->toBeLessThan(10 * 1024 * 1024);
        
        // Log performance report for analysis
        error_log('Memory Optimization Performance Report: ' . json_encode($report['summary']));
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Force garbage collection to free memory.
     */
    protected function forceGarbageCollection(): void
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
}
