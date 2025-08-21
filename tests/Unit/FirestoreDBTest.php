<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Firestore\FirestoreQueryBuilder;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Test;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;

/**
 * Comprehensive FirestoreDB Facade Test
 * 
 * Migrated from:
 * - tests/Unit/FirestoreDBTest.php
 * 
 * Uses new UnitTestSuite for optimized performance and memory management.
 */

class FirestoreDBTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for DB facade operations
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();
    }

    // ========================================
    // BASIC OPERATIONS AND QUERY BUILDING
    // ========================================

    #[Test]
    public function it_provides_basic_operations_and_query_building()
    {
        // Test query builder creation
        $builder = FirestoreDB::table('users');
        expect($builder)->toBeInstanceOf(FirestoreQueryBuilder::class);
        
        // Test client access
        $client = FirestoreDB::client();
        expect($client)->toBeInstanceOf(\Google\Cloud\Firestore\FirestoreClient::class);
        
        // Test collection reference
        $collection = FirestoreDB::collection('users');
        expect($collection)->toBeInstanceOf(\Google\Cloud\Firestore\CollectionReference::class);
        
        // Test document reference
        $document = FirestoreDB::document('users/123');
        expect($document)->toBeInstanceOf(\Google\Cloud\Firestore\DocumentReference::class);
        
        // Test where query building
        $whereBuilder = FirestoreDB::table('users')
            ->where('active', '==', true);
        
        expect($whereBuilder)->toBeInstanceOf(FirestoreQueryBuilder::class);
        expect($whereBuilder->wheres)->toHaveCount(1);
        expect($whereBuilder->wheres[0]['field'])->toBe('active');
        expect($whereBuilder->wheres[0]['operator'])->toBe('==');
        expect($whereBuilder->wheres[0]['value'])->toBe(true);
        
        // Test orderBy query building
        $orderBuilder = FirestoreDB::table('users')
            ->orderBy('created_at', 'desc');
        
        expect($orderBuilder->orders)->toHaveCount(1);
        expect($orderBuilder->orders[0]['field'])->toBe('created_at');
        expect($orderBuilder->orders[0]['direction'])->toBe('desc');
        
        // Test limit query building
        $limitBuilder = FirestoreDB::table('users')
            ->limit(10);
        
        expect($limitBuilder->limitValue)->toBe(10);
        
        // Test method chaining
        $chainedBuilder = FirestoreDB::table('users')
            ->where('active', '==', true)
            ->orderBy('name')
            ->limit(5);
        
        expect($chainedBuilder->wheres)->toHaveCount(1);
        expect($chainedBuilder->orders)->toHaveCount(1);
        expect($chainedBuilder->limitValue)->toBe(5);
        
        // Performance test for query building
        $executionTime = $this->benchmark(function () {
            return FirestoreDB::table('users')
                ->where('active', true)
                ->where('role', 'admin')
                ->orderBy('created_at', 'desc')
                ->orderBy('name', 'asc')
                ->limit(50);
        });
        
        expect($executionTime)->toBeLessThan(0.005); // Query building should be very fast
    }

    // ========================================
    // DOCUMENT OPERATIONS
    // ========================================

    #[Test]
    public function it_handles_document_operations()
    {
        // Test document creation
        $this->mockFirestoreCreate('users', '123');
        
        $userData = TestDataFactory::createUser([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $result = FirestoreDB::table('users')->insert($userData);
        
        expect($result)->toBeTrue();
        $this->assertFirestoreOperationCalled('create', 'users');
        
        // Test document update
        $this->mockFirestoreUpdate('users', '123');
        
        $updateResult = FirestoreDB::table('users')
            ->where('id', '==', '123')
            ->update(['name' => 'Jane Doe']);
        
        expect($updateResult)->toBe(1);
        $this->assertFirestoreOperationCalled('update', 'users', '123');
        
        // Test document deletion
        $this->mockFirestoreDelete('users', '123');
        
        $deleteResult = FirestoreDB::table('users')
            ->where('id', '==', '123')
            ->delete();
        
        expect($deleteResult)->toBe(1);
        $this->assertFirestoreOperationCalled('delete', 'users', '123');
        
        // Performance test for document operations
        $executionTime = $this->benchmark(function () {
            $this->mockFirestoreCreate('users', 'perf-test');
            return FirestoreDB::table('users')->insert(TestDataFactory::createUser());
        });
        
        expect($executionTime)->toBeLessThan(0.01); // Document operations should be fast
    }

    // ========================================
    // DATA RETRIEVAL OPERATIONS
    // ========================================

    #[Test]
    public function it_handles_data_retrieval_operations()
    {
        // Mock test data using TestDataFactory
        $testUsers = [
            TestDataFactory::createUser(['id' => '1', 'name' => 'John Doe', 'active' => true]),
            TestDataFactory::createUser(['id' => '2', 'name' => 'Jane Smith', 'active' => false]),
            TestDataFactory::createUser(['id' => '3', 'name' => 'Bob Johnson', 'active' => true]),
        ];
        
        $this->mockFirestoreQuery('users', $testUsers);
        
        // Test get all documents
        $results = FirestoreDB::table('users')->get();
        
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->toHaveCount(3);
        
        // Test get first document
        $first = FirestoreDB::table('users')->first();
        
        expect($first)->not->toBeNull();
        expect($first->name)->toBe('John Doe');
        
        // Test count documents
        $count = FirestoreDB::table('users')->count();
        expect($count)->toBe(3);
        
        // Test exists check
        $exists = FirestoreDB::table('users')
            ->where('name', '==', 'John Doe')
            ->exists();
        
        expect($exists)->toBeTrue();
        
        $notExists = FirestoreDB::table('users')
            ->where('name', '==', 'Nonexistent User')
            ->exists();
        
        expect($notExists)->toBeFalse();
        
        // Performance test for data retrieval
        $executionTime = $this->benchmark(function () use ($testUsers) {
            $this->mockFirestoreQuery('users', $testUsers);
            return FirestoreDB::table('users')
                ->where('active', true)
                ->orderBy('name')
                ->limit(10)
                ->get();
        });
        
        expect($executionTime)->toBeLessThan(0.01); // Data retrieval should be fast
    }

    // ========================================
    // BATCH OPERATIONS AND TRANSACTIONS
    // ========================================

    #[Test]
    public function it_handles_batch_operations_and_transactions()
    {
        // Test batch operations
        $batch = FirestoreDB::batch();
        expect($batch)->toBeInstanceOf(\Google\Cloud\Firestore\WriteBatch::class);
        
        // Test transactions
        $result = FirestoreDB::runTransaction(function ($transaction) {
            // Mock transaction operations
            return 'success';
        });
        
        expect($result)->toBe('success');
        
        // Performance test for batch operations
        $executionTime = $this->benchmark(function () {
            $batch = FirestoreDB::batch();
            
            // Simulate batch operations
            for ($i = 0; $i < 10; $i++) {
                $userData = TestDataFactory::createUser(['id' => "batch-{$i}"]);
                // In real scenario, would add to batch
            }
            
            return $batch;
        });
        
        expect($executionTime)->toBeLessThan(0.01); // Batch preparation should be fast
    }

    // ========================================
    // ERROR HANDLING AND CONFIGURATION
    // ========================================

    #[Test]
    public function it_handles_errors_and_configuration_correctly()
    {
        // Test error handling for invalid collection names
        expect(fn() => FirestoreDB::table(''))
            ->toThrow(\InvalidArgumentException::class);
        
        // Test error handling for invalid document paths
        expect(fn() => FirestoreDB::document('invalid'))
            ->toThrow(\InvalidArgumentException::class);
        
        // Test configuration access
        $projectId = FirestoreDB::getProjectId();
        expect($projectId)->toBe('test-project');
        
        // Test mock mode detection
        $isMocked = FirestoreDB::isMocked();
        expect($isMocked)->toBeTrue();
        
        // Performance test for configuration access
        $executionTime = $this->benchmark(function () {
            $projectId = FirestoreDB::getProjectId();
            $isMocked = FirestoreDB::isMocked();
            $client = FirestoreDB::client();
            return [$projectId, $isMocked, $client];
        });
        
        expect($executionTime)->toBeLessThan(0.005); // Configuration access should be very fast
    }

    // ========================================
    // COMPLEX QUERY SCENARIOS
    // ========================================

    #[Test]
    public function it_handles_complex_query_scenarios()
    {
        // Create complex test data
        $complexUsers = [];
        for ($i = 1; $i <= 20; $i++) {
            $complexUsers[] = TestDataFactory::createUser([
                'id' => "user-{$i}",
                'name' => "User {$i}",
                'active' => $i % 2 === 0,
                'role' => $i <= 10 ? 'admin' : 'user',
                'score' => rand(50, 100),
            ]);
        }
        
        $this->mockFirestoreQuery('users', $complexUsers);
        
        // Test complex query building
        $complexQuery = FirestoreDB::table('users')
            ->where('active', '==', true)
            ->where('role', '==', 'admin')
            ->where('score', '>', 75)
            ->orderBy('score', 'desc')
            ->orderBy('name', 'asc')
            ->limit(5)
            ->offset(2);
        
        expect($complexQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);
        expect($complexQuery->wheres)->toHaveCount(3);
        expect($complexQuery->orders)->toHaveCount(2);
        expect($complexQuery->limitValue)->toBe(5);
        expect($complexQuery->offsetValue)->toBe(2);
        
        // Test query execution
        $results = $complexQuery->get();
        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        
        // Memory efficiency test
        $this->enableMemoryMonitoring();
        
        // Create and execute multiple complex queries
        for ($i = 0; $i < 5; $i++) {
            $query = FirestoreDB::table('users')
                ->where('active', true)
                ->orderBy('created_at', 'desc')
                ->limit(10);
            
            $query->get();
        }
        
        $this->assertMemoryUsageWithinThreshold(2 * 1024 * 1024); // 2MB threshold
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create test data
        $testData = [];
        for ($i = 0; $i < 5; $i++) {
            $testData[] = TestDataFactory::createUser(['id' => "cleanup-{$i}"]);
        }
        
        $this->mockFirestoreQuery('users', $testData);
        
        // Perform operations
        $results = FirestoreDB::table('users')->get();
        expect($results)->toHaveCount(5);
        
        // Clear test data
        $this->clearTestData();
        
        // Verify cleanup
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
    }
}
