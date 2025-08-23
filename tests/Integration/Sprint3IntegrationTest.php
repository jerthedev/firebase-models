<?php

namespace JTD\FirebaseModels\Tests\Integration;

use JTD\FirebaseModels\Tests\TestSuites\IntegrationTestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Firestore\Batch\BatchManager;
use JTD\FirebaseModels\Firestore\Transactions\TransactionManager;
use JTD\FirebaseModels\Sync\SyncManager;
use JTD\FirebaseModels\Testing\BatchTestHelper;
use JTD\FirebaseModels\Testing\RelationshipTestHelper;

/**
 * Comprehensive integration tests for Sprint 3 features.
 * Tests the interaction between sync, transactions, batch operations, and relationships.
 */
class Sprint3IntegrationTest extends IntegrationTestSuite
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test environment
        config(['firebase-models.sync.enabled' => true]);
        config(['firebase-models.sync.mode' => 'two_way']);
        
        // Create test models
        $this->createTestModels();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * Test sync mode with transaction support.
     */
    public function test_sync_with_transactions()
    {
        // Create test data in transaction
        $result = TransactionManager::execute(function ($transaction) {
            $userRef = $this->firestore->collection('users')->newDocument();
            $transaction->set($userRef, [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'balance' => 1000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $postRef = $this->firestore->collection('posts')->newDocument();
            $transaction->set($postRef, [
                'title' => 'Test Post',
                'content' => 'Test content',
                'user_id' => $userRef->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['user_id' => $userRef->id(), 'post_id' => $postRef->id()];
        });

        $this->assertIsArray($result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('post_id', $result);

        // Test sync operation
        $syncManager = app(SyncManager::class);
        $syncResult = $syncManager->syncCollection('users');
        
        $this->assertTrue($syncResult->isSuccess());
        $this->assertGreaterThan(0, $syncResult->getSyncedCount());

        // Verify data consistency
        $this->assertDatabaseHas('firebase_sync_users', [
            'firebase_id' => $result['user_id'],
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    /**
     * Test batch operations with relationships.
     */
    public function test_batch_operations_with_relationships()
    {
        // Create parent records in batch
        $userData = BatchTestHelper::createTestData(5, [
            'name' => 'User {i}',
            'email' => 'user{i}@example.com',
            'balance' => 100,
        ]);

        $userResult = BatchManager::bulkInsert('users', $userData);
        $this->assertTrue($userResult->isSuccess());
        $userIds = $userResult->getData()['document_ids'];

        // Create related records in batch
        $postData = [];
        foreach ($userIds as $index => $userId) {
            for ($i = 1; $i <= 3; $i++) {
                $postData[] = [
                    'title' => "Post {$i} by User " . ($index + 1),
                    'content' => 'Test content',
                    'user_id' => $userId,
                    'status' => 'published',
                    'created_at' => now(),
                ];
            }
        }

        $postResult = BatchManager::bulkInsert('posts', $postData);
        $this->assertTrue($postResult->isSuccess());
        $this->assertEquals(15, $postResult->getOperationCount()); // 5 users × 3 posts

        // Test relationship loading
        $users = TestUser::with('posts')->get();
        $this->assertCount(5, $users);
        
        foreach ($users as $user) {
            $this->assertTrue($user->relationLoaded('posts'));
            $this->assertCount(3, $user->posts);
        }
    }

    /**
     * Test conflict resolution with transactions.
     */
    public function test_conflict_resolution_with_transactions()
    {
        // Create initial user
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'balance' => 1000,
            'version' => 1,
        ]);

        // Simulate concurrent transactions
        $results = [];
        
        // Transaction 1: Deduct 100
        $results[] = TransactionManager::executeWithResult(function ($transaction) use ($user) {
            $userRef = $this->firestore->collection('users')->document($user->getKey());
            $userSnapshot = $transaction->snapshot($userRef);
            
            if ($userSnapshot->exists()) {
                $userData = $userSnapshot->data();
                $transaction->update($userRef, [
                    'balance' => $userData['balance'] - 100,
                    'version' => $userData['version'] + 1,
                    'updated_at' => now(),
                ]);
            }
            
            return 'deduct_100';
        });

        // Transaction 2: Deduct 200 (should handle conflict)
        $results[] = TransactionManager::executeWithResult(function ($transaction) use ($user) {
            $userRef = $this->firestore->collection('users')->document($user->getKey());
            $userSnapshot = $transaction->snapshot($userRef);
            
            if ($userSnapshot->exists()) {
                $userData = $userSnapshot->data();
                $transaction->update($userRef, [
                    'balance' => $userData['balance'] - 200,
                    'version' => $userData['version'] + 1,
                    'updated_at' => now(),
                ]);
            }
            
            return 'deduct_200';
        });

        // At least one transaction should succeed
        $successCount = collect($results)->filter(fn($r) => $r->isSuccess())->count();
        $this->assertGreaterThan(0, $successCount);

        // Verify final balance is consistent
        $user->refresh();
        $this->assertLessThan(1000, $user->balance);
        $this->assertGreaterThanOrEqual(700, $user->balance); // At least one deduction applied
    }

    /**
     * Test sync with batch operations and conflict resolution.
     */
    public function test_sync_batch_conflict_resolution()
    {
        // Create initial data
        $users = TestUser::createManyInBatch([
            ['name' => 'Alice', 'email' => 'alice@example.com', 'balance' => 500],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'balance' => 750],
            ['name' => 'Charlie', 'email' => 'charlie@example.com', 'balance' => 1000],
        ]);

        $this->assertTrue($users->isSuccess());

        // Sync to local database
        $syncManager = app(SyncManager::class);
        $syncResult = $syncManager->syncCollection('users');
        $this->assertTrue($syncResult->isSuccess());

        // Simulate local modifications
        $localUpdates = [
            'alice-id' => ['balance' => 600, 'updated_at' => now()],
            'bob-id' => ['balance' => 800, 'updated_at' => now()],
        ];

        // Simulate cloud modifications (conflicts)
        $cloudUpdates = [
            'alice-id' => ['balance' => 550, 'updated_at' => now()->addSeconds(1)],
            'charlie-id' => ['balance' => 1100, 'updated_at' => now()],
        ];

        // Apply cloud updates
        BatchManager::bulkUpdate('users', $cloudUpdates);

        // Sync again to resolve conflicts
        $conflictSyncResult = $syncManager->syncCollection('users', [
            'conflict_resolution' => 'last_write_wins',
        ]);

        $this->assertTrue($conflictSyncResult->isSuccess());

        // Verify conflict resolution (cloud should win for Alice due to later timestamp)
        $this->assertDatabaseHas('firebase_sync_users', [
            'firebase_id' => 'alice-id',
            'balance' => 550, // Cloud value wins
        ]);
    }

    /**
     * Test complex workflow with all Sprint 3 features.
     */
    public function test_complete_workflow()
    {
        // 1. Create users and posts with relationships in transaction
        $workflowResult = TransactionManager::builder()
            ->create('users', [
                'name' => 'Workflow User',
                'email' => 'workflow@example.com',
                'balance' => 2000,
                'status' => 'active',
            ])
            ->create('categories', [
                'name' => 'Technology',
                'description' => 'Tech posts',
            ])
            ->executeWithResult();

        $this->assertTrue($workflowResult->isSuccess());
        $results = $workflowResult->getData();
        $userId = $results[0];
        $categoryId = $results[1];

        // 2. Batch create posts with relationships
        $postData = [];
        for ($i = 1; $i <= 10; $i++) {
            $postData[] = [
                'title' => "Tech Post {$i}",
                'content' => "Content for post {$i}",
                'user_id' => $userId,
                'category_id' => $categoryId,
                'status' => 'published',
                'views' => 0,
                'likes' => 0,
            ];
        }

        $batchResult = BatchManager::bulkInsert('posts', $postData);
        $this->assertTrue($batchResult->isSuccess());
        $this->assertEquals(10, $batchResult->getOperationCount());

        // 3. Sync all data to local database
        $syncManager = app(SyncManager::class);
        
        $userSyncResult = $syncManager->syncCollection('users');
        $this->assertTrue($userSyncResult->isSuccess());
        
        $postSyncResult = $syncManager->syncCollection('posts');
        $this->assertTrue($postSyncResult->isSuccess());

        // 4. Test relationship loading with eager loading
        $user = TestUser::with(['posts', 'posts.category'])->find($userId);
        $this->assertNotNull($user);
        $this->assertTrue($user->relationLoaded('posts'));
        $this->assertCount(10, $user->posts);
        
        foreach ($user->posts as $post) {
            $this->assertTrue($post->relationLoaded('category'));
            $this->assertEquals('Technology', $post->category->name);
        }

        // 5. Batch update posts with transaction safety
        $updateData = [];
        foreach ($user->posts as $post) {
            $updateData[$post->getKey()] = [
                'views' => rand(100, 1000),
                'likes' => rand(10, 100),
                'updated_at' => now(),
            ];
        }

        $updateResult = BatchManager::bulkUpdate('posts', $updateData);
        $this->assertTrue($updateResult->isSuccess());

        // 6. Final sync to ensure consistency
        $finalSyncResult = $syncManager->syncCollection('posts');
        $this->assertTrue($finalSyncResult->isSuccess());

        // 7. Verify data integrity
        $this->assertDatabaseHas('firebase_sync_users', [
            'firebase_id' => $userId,
            'name' => 'Workflow User',
            'status' => 'active',
        ]);

        $this->assertDatabaseCount('firebase_sync_posts', 10);
    }

    /**
     * Test performance with large datasets.
     */
    public function test_performance_with_large_datasets()
    {
        $startTime = microtime(true);

        // Create 1000 users in batches
        $userData = BatchTestHelper::createTestData(1000, [
            'name' => 'User {i}',
            'email' => 'user{i}@example.com',
            'balance' => rand(100, 1000),
        ]);

        $batchResult = BatchManager::bulkInsert('users', $userData, [
            'chunk_size' => 100,
        ]);

        $this->assertTrue($batchResult->isSuccess());
        $this->assertEquals(1000, $batchResult->getOperationCount());

        $batchTime = microtime(true) - $startTime;
        $this->assertLessThan(30, $batchTime); // Should complete within 30 seconds

        // Test sync performance
        $syncStartTime = microtime(true);
        
        $syncManager = app(SyncManager::class);
        $syncResult = $syncManager->syncCollection('users', [
            'batch_size' => 100,
        ]);

        $syncTime = microtime(true) - $syncStartTime;
        $this->assertTrue($syncResult->isSuccess());
        $this->assertLessThan(60, $syncTime); // Sync should complete within 60 seconds

        // Verify all data was synced
        $this->assertDatabaseCount('firebase_sync_users', 1000);
    }

    /**
     * Test error handling and recovery.
     */
    public function test_error_handling_and_recovery()
    {
        // Test transaction rollback on error
        try {
            TransactionManager::execute(function ($transaction) {
                $userRef = $this->firestore->collection('users')->newDocument();
                $transaction->set($userRef, [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]);

                // Simulate error
                throw new \Exception('Simulated error');
            });
            
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Simulated error', $e->getMessage());
        }

        // Verify no data was created due to rollback
        $users = TestUser::all();
        $this->assertCount(0, $users);

        // Test batch operation error handling
        $invalidData = [
            ['name' => 'Valid User', 'email' => 'valid@example.com'],
            ['name' => '', 'email' => 'invalid'], // Invalid data
        ];

        try {
            BatchManager::bulkInsert('users', $invalidData, [
                'validate_operations' => true,
            ]);
        } catch (\Exception $e) {
            $this->assertStringContains('validation', strtolower($e->getMessage()));
        }

        // Test sync error recovery
        $syncManager = app(SyncManager::class);
        
        // Create valid data first
        TestUser::create(['name' => 'Valid User', 'email' => 'valid@example.com']);
        
        $syncResult = $syncManager->syncCollection('users', [
            'retry_on_failure' => true,
            'max_retries' => 3,
        ]);

        $this->assertTrue($syncResult->isSuccess());
    }

    /**
     * Helper method to create test models.
     */
    protected function createTestModels(): void
    {
        // Test models are created dynamically for testing
        if (!class_exists('TestUser')) {
            eval('
                class TestUser extends JTD\FirebaseModels\Firestore\FirestoreModel
                {
                    protected $collection = "users";
                    protected $fillable = ["name", "email", "balance", "status", "version"];
                    protected $syncEnabled = true;
                    
                    public function posts()
                    {
                        return $this->hasMany(TestPost::class, "user_id");
                    }
                }
                
                class TestPost extends JTD\FirebaseModels\Firestore\FirestoreModel
                {
                    protected $collection = "posts";
                    protected $fillable = ["title", "content", "user_id", "category_id", "status", "views", "likes"];
                    protected $syncEnabled = true;
                    
                    public function user()
                    {
                        return $this->belongsTo(TestUser::class, "user_id");
                    }
                    
                    public function category()
                    {
                        return $this->belongsTo(TestCategory::class, "category_id");
                    }
                }
                
                class TestCategory extends JTD\FirebaseModels\Firestore\FirestoreModel
                {
                    protected $collection = "categories";
                    protected $fillable = ["name", "description"];
                    protected $syncEnabled = true;
                    
                    public function posts()
                    {
                        return $this->hasMany(TestPost::class, "category_id");
                    }
                }
            ');
        }
    }

    /**
     * Helper method to clean up test data.
     */
    private function cleanupTestData(): void
    {
        // Clean up Firestore collections
        $collections = ['users', 'posts', 'categories', 'orders'];
        
        foreach ($collections as $collection) {
            try {
                $documents = $this->firestore->collection($collection)->documents();
                $documentIds = [];
                
                foreach ($documents as $document) {
                    $documentIds[] = $document->id();
                }
                
                if (!empty($documentIds)) {
                    BatchManager::bulkDelete($collection, $documentIds, [
                        'log_operations' => false,
                    ]);
                }
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
    }
}
