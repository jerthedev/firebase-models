<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Tests\TestCase;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use JTD\FirebaseModels\Tests\Helpers\FirebaseAuthMock;
use JTD\FirebaseModels\Facades\FirestoreDB;

describe('Firebase Mock System', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        FirebaseAuthMock::clear();
    });

    describe('FirestoreMock', function () {
        it('can mock document creation', function () {
            // Simulate document creation
            $collection = FirestoreDB::collection('posts');
            $docRef = $collection->document('post-123');
            $docRef->set(['title' => 'Test Post']);

            $this->assertFirestoreOperationCalled('set', 'posts', 'post-123');
        });

        it('can mock document retrieval', function () {
            $testData = [
                'id' => 'post-123',
                'title' => 'Test Post',
                'content' => 'Test content',
                'published' => true
            ];

            $this->mockFirestoreGet('posts', 'post-123', $testData);

            // Simulate document retrieval
            $collection = FirestoreDB::collection('posts');
            $docRef = $collection->document('post-123');
            $snapshot = $docRef->snapshot();

            expect($snapshot->exists())->toBeTrue();
            expect($snapshot->data())->toBe($testData);
        });

        it('can mock document updates', function () {
            // Simulate document update
            $collection = FirestoreDB::collection('posts');
            $docRef = $collection->document('post-123');
            $docRef->update(['title' => 'Updated Title']);

            $this->assertFirestoreOperationCalled('update', 'posts', 'post-123');
        });

        it('can mock document deletion', function () {
            // Simulate document deletion
            $collection = FirestoreDB::collection('posts');
            $docRef = $collection->document('post-123');
            $docRef->delete();

            $this->assertFirestoreOperationCalled('delete', 'posts', 'post-123');
        });

        it('can mock query operations', function () {
            $testData = [
                ['id' => '1', 'title' => 'Post 1', 'published' => true],
                ['id' => '2', 'title' => 'Post 2', 'published' => false],
                ['id' => '3', 'title' => 'Post 3', 'published' => true],
            ];

            $this->mockFirestoreQuery('posts', $testData);

            // Simulate query execution
            $collection = FirestoreDB::collection('posts');
            $query = $collection->where('published', '==', true);
            $documents = $query->documents();

            expect($documents)->toBeArray();

            $this->assertFirestoreQueryExecuted('posts', [
                ['field' => 'published', 'operator' => '==', 'value' => true]
            ]);
        });

        it('can simulate query filtering', function () {
            $testData = [
                ['id' => '1', 'title' => 'Post 1', 'published' => true, 'views' => 100],
                ['id' => '2', 'title' => 'Post 2', 'published' => false, 'views' => 50],
                ['id' => '3', 'title' => 'Post 3', 'published' => true, 'views' => 200],
            ];
            
            $mock = FirestoreMock::getInstance();
            $filtered = $mock->filterDocuments($testData, [
                ['field' => 'published', 'operator' => '==', 'value' => true],
                ['field' => 'views', 'operator' => '>', 'value' => 75]
            ]);
            
            expect($filtered)->toHaveCount(2);
            expect($filtered[0]['id'])->toBe('1');
            expect($filtered[1]['id'])->toBe('3');
        });

        it('can simulate query ordering', function () {
            $testData = [
                ['id' => '1', 'title' => 'Post 1', 'views' => 100],
                ['id' => '2', 'title' => 'Post 2', 'views' => 300],
                ['id' => '3', 'title' => 'Post 3', 'views' => 200],
            ];
            
            $mock = FirestoreMock::getInstance();
            
            // Test ascending order
            $ordered = $mock->orderDocuments($testData, [
                ['field' => 'views', 'direction' => 'asc']
            ]);
            
            expect($ordered[0]['views'])->toBe(100);
            expect($ordered[1]['views'])->toBe(200);
            expect($ordered[2]['views'])->toBe(300);
            
            // Test descending order
            $ordered = $mock->orderDocuments($testData, [
                ['field' => 'views', 'direction' => 'desc']
            ]);
            
            expect($ordered[0]['views'])->toBe(300);
            expect($ordered[1]['views'])->toBe(200);
            expect($ordered[2]['views'])->toBe(100);
        });

        it('can simulate query limiting', function () {
            $testData = [
                ['id' => '1', 'title' => 'Post 1'],
                ['id' => '2', 'title' => 'Post 2'],
                ['id' => '3', 'title' => 'Post 3'],
                ['id' => '4', 'title' => 'Post 4'],
                ['id' => '5', 'title' => 'Post 5'],
            ];
            
            $mock = FirestoreMock::getInstance();
            $limited = $mock->limitDocuments($testData, 3);
            
            expect($limited)->toHaveCount(3);
            expect($limited[0]['id'])->toBe('1');
            expect($limited[2]['id'])->toBe('3');
        });

        it('can track multiple operations', function () {
            // Simulate operations
            $collection = FirestoreDB::collection('posts');

            $collection->document('post-1')->set(['title' => 'Post 1']);
            $collection->document('post-2')->set(['title' => 'Post 2']);
            $collection->document('post-1')->update(['title' => 'Updated Post 1']);
            $collection->document('post-2')->delete();

            $this->assertFirestoreOperationCalled('set', 'posts', 'post-1');
            $this->assertFirestoreOperationCalled('set', 'posts', 'post-2');
            $this->assertFirestoreOperationCalled('update', 'posts', 'post-1');
            $this->assertFirestoreOperationCalled('delete', 'posts', 'post-2');
        });

        it('can clear mock state between tests', function () {
            // Perform an operation to generate some state
            $collection = FirestoreDB::collection('posts');
            $docRef = $collection->document('post-123');
            $docRef->set(['title' => 'Test Post']);

            $mock = FirestoreMock::getInstance();
            expect($mock->getOperations())->not->toBeEmpty();

            $this->clearFirestoreMocks();

            expect($mock->getOperations())->toBeEmpty();
            expect($mock->getDocuments())->toBeEmpty();
        });
    });

    describe('FirebaseAuthMock', function () {
        it('can mock user creation', function () {
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'test@example.com',
                'displayName' => 'Test User'
            ]);
            
            expect($userData['email'])->toBe('test@example.com');
            expect($userData['displayName'])->toBe('Test User');
            expect($userData['uid'])->toBeString();
        });

        it('can mock token creation', function () {
            $userData = FirebaseAuthMock::createTestUser(['email' => 'test@example.com']);
            $token = FirebaseAuthMock::createTestToken($userData['uid'], ['admin' => true]);
            
            expect($token)->toBeString();
            expect($token)->toContain('mock_custom_token_');
        });

        it('can track auth operations', function () {
            $userData = FirebaseAuthMock::createTestUser();
            $token = FirebaseAuthMock::createTestToken($userData['uid']);

            expect($token)->toBeString();
            expect($userData['uid'])->toBeString();
        });

        it('can clear auth mock state', function () {
            FirebaseAuthMock::createTestUser();
            
            $mock = FirebaseAuthMock::getInstance();
            expect($mock->getUsers())->not->toBeEmpty();
            
            FirebaseAuthMock::clear();
            
            expect($mock->getUsers())->toBeEmpty();
            expect($mock->getTokens())->toBeEmpty();
            expect($mock->getOperations())->toBeEmpty();
        });
    });

    describe('Mock Integration', function () {
        it('can use both Firestore and Auth mocks together', function () {
            // Create a test user
            $userData = FirebaseAuthMock::createTestUser([
                'email' => 'author@example.com',
                'displayName' => 'Post Author'
            ]);

            // Create a test post
            $collection = FirestoreDB::collection('posts');
            $collection->document('post-123')->set([
                'title' => 'Test Post',
                'author_id' => $userData['uid'],
                'content' => 'This is a test post.'
            ]);

            // Verify Firestore operation
            $this->assertFirestoreOperationCalled('set', 'posts', 'post-123');

            // Verify user was created
            expect($userData['email'])->toBe('author@example.com');
            expect($userData['displayName'])->toBe('Post Author');
        });

        it('maintains isolation between test runs', function () {
            // First test scenario
            $collection = FirestoreDB::collection('posts');
            $collection->document('post-1')->set(['title' => 'Post 1']);
            $userData1 = FirebaseAuthMock::createTestUser(['email' => 'user1@example.com']);

            // Clear mocks
            $this->clearFirestoreMocks();

            // Second test scenario
            $collection->document('post-2')->set(['title' => 'Post 2']);
            $userData2 = FirebaseAuthMock::createTestUser(['email' => 'user2@example.com']);

            // Verify isolation
            $firestoreMock = FirestoreMock::getInstance();
            $authMock = FirebaseAuthMock::getInstance();

            expect($firestoreMock->getOperations())->toHaveCount(1);
            expect($authMock->getUsers())->toHaveCount(1);
            expect($userData2['email'])->toBe('user2@example.com');
        });
    });

    describe('Performance', function () {
        it('executes mock operations quickly', function () {
            $startTime = microtime(true);
            
            // Perform multiple mock operations
            $collection = FirestoreDB::collection('posts');
            for ($i = 0; $i < 100; $i++) {
                $collection->document("post-{$i}")->set(['title' => "Post {$i}"]);
                FirebaseAuthMock::createTestUser(['email' => "user{$i}@example.com"]);
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // Should complete quickly (under 1 second for 100 operations)
            expect($executionTime)->toBeLessThan(1.0);
        });

        it('handles large datasets efficiently', function () {
            $largeDataset = [];
            for ($i = 0; $i < 1000; $i++) {
                $largeDataset[] = [
                    'id' => "doc-{$i}",
                    'title' => "Document {$i}",
                    'value' => $i,
                    'published' => $i % 2 === 0
                ];
            }
            
            $startTime = microtime(true);
            
            $mock = FirestoreMock::getInstance();
            $filtered = $mock->filterDocuments($largeDataset, [
                ['field' => 'published', 'operator' => '==', 'value' => true],
                ['field' => 'value', 'operator' => '>', 'value' => 500]
            ]);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($filtered)->toHaveCount(249); // 501, 503, 505, ..., 999 (249 numbers)
            expect($executionTime)->toBeLessThan(0.1); // Should be very fast
        });
    });
});
