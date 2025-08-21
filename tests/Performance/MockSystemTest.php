<?php

namespace JTD\FirebaseModels\Tests\Performance;

use JTD\FirebaseModels\Tests\TestSuites\PerformanceTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive FirestoreMock Performance Test
 *
 * Migrated from:
 * - tests/Unit/MockSystemConsolidationTest.php
 *
 * Tests the consolidated FirestoreMock system for performance and functionality.
 * Previously tested multiple mock types, now focuses on single FirestoreMock implementation.
 */

class MockSystemTest extends PerformanceTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for mock system performance testing
        $this->setTestRequirements([
            'document_count' => 500,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        // Set custom memory thresholds for mock system testing
        $this->setMemoryThresholds([
            'warning' => 20 * 1024 * 1024,  // 20MB
            'critical' => 50 * 1024 * 1024, // 50MB
        ]);

        // Skip automatic mock setup to test directly
        $this->createApplication();
    }

    protected function tearDown(): void
    {
        FirestoreMock::clear();
        parent::tearDown();
    }

    // ========================================
    // FIRESTORE MOCK PERFORMANCE TESTS
    // ========================================

    #[Test]
    public function it_creates_firestore_mock_efficiently()
    {
        $this->addPerformanceCheckpoint('mock_creation_start');

        // Test FirestoreMock creation performance
        $mockCreationResults = $this->measureOperation('firestore_mock_creation', function () {
            FirestoreMock::clear();
            FirestoreMock::initialize();

            $mock = FirestoreMock::getInstance();
            expect($mock)->toBeInstanceOf(FirestoreMock::class);

            return $mock;
        });

        $this->addPerformanceCheckpoint('mock_creation_complete');

        // Mock creation should be very fast
        $this->assertPerformanceWithinLimits('firestore_mock_creation', 0.01, 1 * 1024 * 1024);
    }

    #[Test]
    public function it_provides_consistent_interface_performance()
    {
        $this->addPerformanceCheckpoint('interface_test_start');

        // Test interface consistency and performance
        $interfaceResults = $this->measureOperation('mock_interface_consistency', function () {
            FirestoreMock::clear();
            FirestoreMock::initialize();
            $mock = FirestoreMock::getInstance();

            // Test basic document operations with performance tracking
            for ($i = 0; $i < 100; $i++) {
                $testData = TestDataFactory::createUser([
                    'id' => "user{$i}",
                    'name' => "User {$i}",
                    'email' => "user{$i}@example.com"
                ]);

                $mock->storeDocument('users', "user{$i}", $testData);
            }

            // Test retrieval performance
            $retrievedDocs = [];
            for ($i = 0; $i < 100; $i++) {
                $document = $mock->getDocument('users', "user{$i}");
                $retrievedDocs[] = $document;

                expect($mock->documentExists('users', "user{$i}"))->toBeTrue();
            }

            return $retrievedDocs;
        });

        $this->addPerformanceCheckpoint('interface_test_complete');

        // Interface operations should be fast and memory efficient
        $this->assertPerformanceWithinLimits('mock_interface_consistency', 0.1, 5 * 1024 * 1024);
    }

    #[Test]
    public function it_handles_bulk_operations_efficiently()
    {
        $this->addPerformanceCheckpoint('bulk_operations_start');

        // Test bulk operations performance
        $bulkResults = $this->measureOperation('bulk_operations_test', function () {
            FirestoreMock::clear();
            FirestoreMock::initialize();
            $mock = FirestoreMock::getInstance();

            // Create bulk test data
            for ($i = 0; $i < 1000; $i++) {
                $testData = TestDataFactory::createProduct([
                    'id' => $i,
                    'name' => "Product {$i}",
                    'price' => rand(10, 1000),
                    'category' => 'test'
                ]);

                $mock->storeDocument('products', "product_{$i}", $testData);
            }

            // Verify bulk operations
            $documents = $mock->getDocuments();
            expect(count($documents['products']))->toBe(1000);

            return $documents;
        });

        $this->addPerformanceCheckpoint('bulk_operations_complete');

        // Bulk operations should complete within reasonable time and memory limits
        $this->assertPerformanceWithinLimits('bulk_operations_test', 1.0, 20 * 1024 * 1024);
    }

    #[Test]
    public function it_validates_memory_efficiency()
    {
        $this->addPerformanceCheckpoint('memory_efficiency_start');

        // Test memory efficiency with large datasets
        $memoryResults = $this->measureOperation('memory_efficiency_validation', function () {
            FirestoreMock::clear();
            FirestoreMock::initialize();
            $mock = FirestoreMock::getInstance();

            // Create large dataset
            for ($i = 0; $i < 2000; $i++) {
                $testData = TestDataFactory::createUser([
                    'id' => $i,
                    'name' => "User {$i}",
                    'email' => "user{$i}@example.com",
                    'metadata' => [
                        'created' => date('Y-m-d H:i:s'),
                        'index' => $i,
                        'active' => $i % 2 === 0
                    ]
                ]);

                $mock->storeDocument('large_users', "user_{$i}", $testData);
            }

            // Test memory usage
            $documents = $mock->getDocuments();
            expect(count($documents['large_users']))->toBe(2000);

            return $documents;
        });

        $this->addPerformanceCheckpoint('memory_efficiency_complete');

        // Memory efficiency should be maintained even with large datasets
        $this->assertPerformanceWithinLimits('memory_efficiency_validation', 2.0, 30 * 1024 * 1024);
    }
}
