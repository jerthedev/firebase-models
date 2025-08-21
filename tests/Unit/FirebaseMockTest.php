<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use PHPUnit\Framework\Attributes\Test;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use JTD\FirebaseModels\Facades\FirestoreDB;

/**
 * Comprehensive Firebase Mock System Test
 * 
 * Migrated from:
 * - tests/Unit/FirebaseMockTest.php
 * 
 * Uses new UnitTestSuite for optimized performance and memory management.
 */

class FirebaseMockTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for mock system operations
        $this->setTestRequirements([
            'document_count' => 100,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();
        
        // Clear mock state before each test
        FirebaseAuthMock::clear();
    }

    // ========================================
    // FIRESTORE MOCK OPERATIONS
    // ========================================

    #[Test]
    public function it_mocks_firestore_document_operations()
    {
        // Test document creation
        $collection = FirestoreDB::collection('posts');
        $docRef = $collection->document('post-123');
        $docRef->set(['title' => 'Test Post']);

        $this->assertFirestoreOperationCalled('set', 'posts', 'post-123');

        // Test document retrieval
        $testData = TestDataFactory::createPost([
            'id' => 'post-123',
            'title' => 'Test Post',
            'content' => 'Test content',
            'published' => true
        ]);

        $this->mockFirestoreGet('posts', 'post-123', $testData);

        $snapshot = $docRef->snapshot();
        expect($snapshot->exists())->toBeTrue();
        expect($snapshot->data())->toBe($testData);

        // Test document updates
        $docRef->update(['title' => 'Updated Title']);
        $this->assertFirestoreOperationCalled('update', 'posts', 'post-123');

        // Test document deletion
        $docRef->delete();
        $this->assertFirestoreOperationCalled('delete', 'posts', 'post-123');

        // Performance test for document operations
        $executionTime = $this->benchmark(function () {
            $collection = FirestoreDB::collection('test');
            for ($i = 0; $i < 10; $i++) {
                $docRef = $collection->document("doc-{$i}");
                $docRef->set(TestDataFactory::createPost(['id' => "doc-{$i}"]));
            }
        });

        expect($executionTime)->toBeLessThan(0.01); // Mock operations should be very fast
    }

    #[Test]
    public function it_mocks_firestore_query_operations()
    {
        // Test basic query operations
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'Post 1', 'published' => true]),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Post 2', 'published' => false]),
            TestDataFactory::createPost(['id' => '3', 'title' => 'Post 3', 'published' => true]),
        ];

        $this->mockFirestoreQuery('posts', $testData);

        $collection = FirestoreDB::collection('posts');
        $query = $collection->where('published', '==', true);
        $documents = $query->documents();

        expect($documents)->toBeArray();

        $this->assertFirestoreQueryExecuted('posts', [
            ['field' => 'published', 'operator' => '==', 'value' => true]
        ]);

        // Test query filtering
        $filterTestData = [
            TestDataFactory::createPost(['id' => '1', 'published' => true, 'views' => 100]),
            TestDataFactory::createPost(['id' => '2', 'published' => false, 'views' => 50]),
            TestDataFactory::createPost(['id' => '3', 'published' => true, 'views' => 200]),
        ];
        
        $mock = FirestoreMock::getInstance();
        $filtered = $mock->filterDocuments($filterTestData, [
            ['field' => 'published', 'operator' => '==', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 75]
        ]);
        
        expect($filtered)->toHaveCount(2);
        expect($filtered[0]['id'])->toBe('1');
        expect($filtered[1]['id'])->toBe('3');
    }

    #[Test]
    public function it_handles_query_ordering_and_limiting()
    {
        // Test query ordering
        $orderTestData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'Post 1', 'views' => 100]),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Post 2', 'views' => 300]),
            TestDataFactory::createPost(['id' => '3', 'title' => 'Post 3', 'views' => 200]),
        ];
        
        $mock = FirestoreMock::getInstance();
        
        // Test ascending order
        $orderedAsc = $mock->orderDocuments($orderTestData, [
            ['field' => 'views', 'direction' => 'asc']
        ]);
        
        expect($orderedAsc[0]['views'])->toBe(100);
        expect($orderedAsc[1]['views'])->toBe(200);
        expect($orderedAsc[2]['views'])->toBe(300);
        
        // Test descending order
        $orderedDesc = $mock->orderDocuments($orderTestData, [
            ['field' => 'views', 'direction' => 'desc']
        ]);
        
        expect($orderedDesc[0]['views'])->toBe(300);
        expect($orderedDesc[1]['views'])->toBe(200);
        expect($orderedDesc[2]['views'])->toBe(100);

        // Test query limiting
        $limitTestData = [];
        for ($i = 1; $i <= 5; $i++) {
            $limitTestData[] = TestDataFactory::createPost(['id' => (string)$i, 'title' => "Post {$i}"]);
        }
        
        $limited = $mock->limitDocuments($limitTestData, 3);
        expect($limited)->toHaveCount(3);
        expect($limited[0]['id'])->toBe('1');
        expect($limited[2]['id'])->toBe('3');
    }

    // ========================================
    // FIREBASE AUTH MOCK OPERATIONS
    // ========================================

    #[Test]
    public function it_mocks_firebase_auth_operations()
    {
        // Test user creation
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'test@example.com',
            'displayName' => 'Test User'
        ]);
        
        expect($userData['email'])->toBe('test@example.com');
        expect($userData['displayName'])->toBe('Test User');
        expect($userData['uid'])->toBeString();

        // Test token creation
        $token = FirebaseAuthMock::createTestToken($userData['uid'], ['admin' => true]);
        
        expect($token)->toBeString();
        expect($token)->toContain('mock_custom_token_');

        // Test auth operations tracking
        $newUserData = FirebaseAuthMock::createTestUser();
        $newToken = FirebaseAuthMock::createTestToken($newUserData['uid']);

        expect($newToken)->toBeString();
        expect($newUserData['uid'])->toBeString();

        // Test auth mock state clearing
        $mock = FirebaseAuthMock::getInstance();
        expect($mock->getUsers())->not->toBeEmpty();
        
        FirebaseAuthMock::clear();
        expect($mock->getUsers())->toBeEmpty();

        // Performance test for auth operations
        $executionTime = $this->benchmark(function () {
            for ($i = 0; $i < 10; $i++) {
                $userData = FirebaseAuthMock::createTestUser(['email' => "user{$i}@example.com"]);
                FirebaseAuthMock::createTestToken($userData['uid']);
            }
        });

        expect($executionTime)->toBeLessThan(0.01); // Auth mock operations should be fast
    }

    // ========================================
    // MOCK INTEGRATION AND ISOLATION
    // ========================================

    #[Test]
    public function it_integrates_firestore_and_auth_mocks()
    {
        // Create a test user
        $userData = FirebaseAuthMock::createTestUser([
            'email' => 'author@example.com',
            'displayName' => 'Post Author'
        ]);

        // Create a post associated with the user
        $postData = TestDataFactory::createPost([
            'title' => 'User Post',
            'author_id' => $userData['uid'],
            'published' => true
        ]);

        $collection = FirestoreDB::collection('posts');
        $docRef = $collection->document('user-post-123');
        $docRef->set($postData);

        // Verify both mocks work together
        $this->assertFirestoreOperationCalled('set', 'posts', 'user-post-123');
        
        $authMock = FirebaseAuthMock::getInstance();
        $users = $authMock->getUsers();
        expect($users)->toHaveCount(1);
        expect($users[0]['email'])->toBe('author@example.com');

        // Test isolation between test runs
        $this->clearTestData();
        FirebaseAuthMock::clear();

        // Verify clean state
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
        
        $cleanUsers = $authMock->getUsers();
        expect($cleanUsers)->toBeEmpty();
    }

    // ========================================
    // PERFORMANCE AND MEMORY TESTS
    // ========================================

    #[Test]
    public function it_optimizes_mock_performance_and_memory()
    {
        $this->enableMemoryMonitoring();

        // Test performance with multiple operations
        $executionTime = $this->benchmark(function () {
            $collection = FirestoreDB::collection('posts');
            for ($i = 0; $i < 50; $i++) {
                $docRef = $collection->document("post-{$i}");
                $docRef->set(TestDataFactory::createPost(['id' => "post-{$i}"]));
            }
        });

        expect($executionTime)->toBeLessThan(0.05); // 50 operations should be fast

        // Test memory efficiency with large datasets
        $largeDataset = [];
        for ($i = 0; $i < 100; $i++) {
            $largeDataset[] = TestDataFactory::createPost([
                'id' => "doc-{$i}",
                'title' => "Document {$i}",
                'views' => rand(1, 1000),
                'published' => $i % 2 === 0
            ]);
        }

        $this->mockFirestoreQuery('large_collection', $largeDataset);

        $mock = FirestoreMock::getInstance();
        
        // Test filtering performance
        $filterTime = $this->benchmark(function () use ($mock, $largeDataset) {
            return $mock->filterDocuments($largeDataset, [
                ['field' => 'published', 'operator' => '==', 'value' => true],
                ['field' => 'views', 'operator' => '>', 'value' => 500]
            ]);
        });

        expect($filterTime)->toBeLessThan(0.01); // Filtering should be fast

        // Test ordering performance
        $orderTime = $this->benchmark(function () use ($mock, $largeDataset) {
            return $mock->orderDocuments($largeDataset, [
                ['field' => 'views', 'direction' => 'desc']
            ]);
        });

        expect($orderTime)->toBeLessThan(0.01); // Ordering should be fast

        $this->assertMemoryUsageWithinThreshold(50 * 1024 * 1024); // 50MB threshold
    }

    #[Test]
    public function it_tracks_and_clears_operations_correctly()
    {
        // Perform multiple operations
        $collection = FirestoreDB::collection('posts');

        $collection->document('post-1')->set(['title' => 'Post 1']);
        $collection->document('post-2')->set(['title' => 'Post 2']);
        $collection->document('post-1')->update(['title' => 'Updated Post 1']);
        $collection->document('post-2')->delete();

        // Verify operations were tracked
        $operations = $this->getPerformedOperations();
        expect($operations)->toHaveCount(4);

        // Test clearing mock state
        $this->clearTestData();

        // Verify state is cleared
        $clearedOperations = $this->getPerformedOperations();
        expect($clearedOperations)->toBeEmpty();

        // Test that new operations are tracked correctly after clearing
        $collection->document('new-post')->set(['title' => 'New Post']);
        
        $newOperations = $this->getPerformedOperations();
        expect($newOperations)->toHaveCount(1);
        expect($newOperations[0]['type'])->toBe('set');
        expect($newOperations[0]['collection'])->toBe('posts');
        expect($newOperations[0]['id'])->toBe('new-post');
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create test data in both mocks
        $userData = FirebaseAuthMock::createTestUser(['email' => 'cleanup@example.com']);
        
        $collection = FirestoreDB::collection('cleanup_test');
        $collection->document('test-doc')->set(TestDataFactory::createPost());

        // Verify data exists
        $authMock = FirebaseAuthMock::getInstance();
        expect($authMock->getUsers())->toHaveCount(1);
        
        $operations = $this->getPerformedOperations();
        expect($operations)->toHaveCount(1);

        // Clear test data
        $this->clearTestData();
        FirebaseAuthMock::clear();

        // Verify cleanup
        expect($authMock->getUsers())->toBeEmpty();
        expect($this->getPerformedOperations())->toBeEmpty();
    }
}
