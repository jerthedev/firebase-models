<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\FirebaseModels\Firestore\Transactions\TransactionManager;
use JTD\FirebaseModels\Sync\SyncManager;
use JTD\FirebaseModels\Sync\ConflictResolvers\LastWriteWinsResolver;
use JTD\FirebaseModels\Sync\ConflictResolvers\VersionBasedResolver;

/**
 * Integration tests for sync mode with transaction support.
 */
class SyncTransactionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected SyncManager $syncManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->syncManager = app(SyncManager::class);
        
        // Configure sync mode
        config([
            'firebase-models.sync.enabled' => true,
            'firebase-models.sync.mode' => 'two_way',
            'firebase-models.conflict_resolution.policy' => 'last_write_wins',
        ]);
    }

    /**
     * Test transaction creation with immediate sync.
     */
    public function test_transaction_with_immediate_sync()
    {
        // Create data in transaction
        $result = TransactionManager::execute(function ($transaction) {
            $userRef = $this->firestore->collection('users')->newDocument();
            $transaction->set($userRef, [
                'name' => 'Transaction User',
                'email' => 'transaction@example.com',
                'balance' => 1500,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $profileRef = $this->firestore->collection('profiles')->newDocument();
            $transaction->set($profileRef, [
                'user_id' => $userRef->id(),
                'bio' => 'Test bio',
                'preferences' => ['theme' => 'dark'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'user_id' => $userRef->id(),
                'profile_id' => $profileRef->id(),
            ];
        });

        $this->assertIsArray($result);

        // Immediate sync after transaction
        $syncResult = $this->syncManager->syncCollection('users');
        $this->assertTrue($syncResult->isSuccess());
        $this->assertEquals(1, $syncResult->getSyncedCount());

        // Verify data in local database
        $this->assertDatabaseHas('firebase_sync_users', [
            'firebase_id' => $result['user_id'],
            'name' => 'Transaction User',
            'email' => 'transaction@example.com',
            'balance' => 1500,
        ]);

        // Sync profiles
        $profileSyncResult = $this->syncManager->syncCollection('profiles');
        $this->assertTrue($profileSyncResult->isSuccess());

        $this->assertDatabaseHas('firebase_sync_profiles', [
            'firebase_id' => $result['profile_id'],
            'user_id' => $result['user_id'],
            'bio' => 'Test bio',
        ]);
    }

    /**
     * Test conflict resolution during sync after transaction.
     */
    public function test_conflict_resolution_after_transaction()
    {
        // Create initial user
        $userId = 'test-user-123';
        $initialData = [
            'name' => 'Initial User',
            'email' => 'initial@example.com',
            'balance' => 1000,
            'version' => 1,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ];

        // Create in Firestore
        $this->firestore->collection('users')->document($userId)->set($initialData);

        // Initial sync
        $syncResult = $this->syncManager->syncCollection('users');
        $this->assertTrue($syncResult->isSuccess());

        // Simulate local modification
        DB::table('firebase_sync_users')
            ->where('firebase_id', $userId)
            ->update([
                'balance' => 1200,
                'updated_at' => now()->subMinutes(5),
            ]);

        // Simulate cloud modification via transaction
        TransactionManager::execute(function ($transaction) use ($userId) {
            $userRef = $this->firestore->collection('users')->document($userId);
            $transaction->update($userRef, [
                'balance' => 800,
                'version' => 2,
                'updated_at' => now(), // More recent
            ]);
        });

        // Sync with conflict resolution
        $conflictSyncResult = $this->syncManager->syncCollection('users', [
            'conflict_resolution' => 'last_write_wins',
        ]);

        $this->assertTrue($conflictSyncResult->isSuccess());

        // Cloud should win due to more recent timestamp
        $this->assertDatabaseHas('firebase_sync_users', [
            'firebase_id' => $userId,
            'balance' => 800, // Cloud value
            'version' => 2,
        ]);
    }

    /**
     * Test batch transaction with sync.
     */
    public function test_batch_transaction_with_sync()
    {
        // Create multiple users in a single transaction
        $result = TransactionManager::builder()
            ->create('users', [
                'name' => 'Batch User 1',
                'email' => 'batch1@example.com',
                'balance' => 500,
                'department' => 'Engineering',
            ])
            ->create('users', [
                'name' => 'Batch User 2',
                'email' => 'batch2@example.com',
                'balance' => 750,
                'department' => 'Marketing',
            ])
            ->create('users', [
                'name' => 'Batch User 3',
                'email' => 'batch3@example.com',
                'balance' => 1000,
                'department' => 'Sales',
            ])
            ->executeWithResult();

        $this->assertTrue($result->isSuccess());
        $userIds = $result->getData();
        $this->assertCount(3, $userIds);

        // Sync all users
        $syncResult = $this->syncManager->syncCollection('users');
        $this->assertTrue($syncResult->isSuccess());
        $this->assertEquals(3, $syncResult->getSyncedCount());

        // Verify all users are synced
        foreach ($userIds as $index => $userId) {
            $this->assertDatabaseHas('firebase_sync_users', [
                'firebase_id' => $userId,
                'name' => 'Batch User ' . ($index + 1),
            ]);
        }

        // Test department-based filtering in sync
        $engineeringSyncResult = $this->syncManager->syncCollection('users', [
            'filters' => [
                ['field' => 'department', 'operator' => '==', 'value' => 'Engineering']
            ]
        ]);

        $this->assertTrue($engineeringSyncResult->isSuccess());
        $this->assertEquals(1, $engineeringSyncResult->getSyncedCount());
    }

    /**
     * Test transaction rollback with sync consistency.
     */
    public function test_transaction_rollback_sync_consistency()
    {
        // Initial sync state
        $initialSyncResult = $this->syncManager->syncCollection('users');
        $initialCount = $initialSyncResult->getSyncedCount();

        // Attempt transaction that will fail
        try {
            TransactionManager::execute(function ($transaction) {
                // Create first user (should succeed)
                $userRef1 = $this->firestore->collection('users')->newDocument();
                $transaction->set($userRef1, [
                    'name' => 'User 1',
                    'email' => 'user1@example.com',
                    'balance' => 500,
                ]);

                // Create second user (should succeed)
                $userRef2 = $this->firestore->collection('users')->newDocument();
                $transaction->set($userRef2, [
                    'name' => 'User 2',
                    'email' => 'user2@example.com',
                    'balance' => 750,
                ]);

                // Simulate error that causes rollback
                throw new \Exception('Simulated transaction failure');
            });

            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Simulated transaction failure', $e->getMessage());
        }

        // Sync after failed transaction
        $postFailureSyncResult = $this->syncManager->syncCollection('users');
        $this->assertTrue($postFailureSyncResult->isSuccess());

        // Count should remain the same (no new users due to rollback)
        $this->assertEquals($initialCount, $postFailureSyncResult->getSyncedCount());

        // Verify no partial data was created
        $this->assertDatabaseMissing('firebase_sync_users', [
            'name' => 'User 1',
        ]);
        $this->assertDatabaseMissing('firebase_sync_users', [
            'name' => 'User 2',
        ]);
    }

    /**
     * Test version-based conflict resolution with transactions.
     */
    public function test_version_based_conflict_resolution()
    {
        $userId = 'version-test-user';
        
        // Create user with version
        $this->firestore->collection('users')->document($userId)->set([
            'name' => 'Version User',
            'email' => 'version@example.com',
            'balance' => 1000,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Initial sync
        $this->syncManager->syncCollection('users');

        // Simulate concurrent updates with version checking
        $transaction1Success = false;
        $transaction2Success = false;

        try {
            // Transaction 1: Update with version 1
            TransactionManager::execute(function ($transaction) use ($userId) {
                $userRef = $this->firestore->collection('users')->document($userId);
                $userSnapshot = $transaction->snapshot($userRef);
                $userData = $userSnapshot->data();

                if ($userData['version'] === 1) {
                    $transaction->update($userRef, [
                        'balance' => $userData['balance'] + 100,
                        'version' => 2,
                        'updated_at' => now(),
                    ]);
                } else {
                    throw new \Exception('Version conflict in transaction 1');
                }
            });
            $transaction1Success = true;
        } catch (\Exception $e) {
            // Transaction 1 failed due to version conflict
        }

        try {
            // Transaction 2: Also try to update with version 1 (should fail)
            TransactionManager::execute(function ($transaction) use ($userId) {
                $userRef = $this->firestore->collection('users')->document($userId);
                $userSnapshot = $transaction->snapshot($userRef);
                $userData = $userSnapshot->data();

                if ($userData['version'] === 1) {
                    $transaction->update($userRef, [
                        'balance' => $userData['balance'] + 200,
                        'version' => 2,
                        'updated_at' => now(),
                    ]);
                } else {
                    throw new \Exception('Version conflict in transaction 2');
                }
            });
            $transaction2Success = true;
        } catch (\Exception $e) {
            // Transaction 2 failed due to version conflict
        }

        // Only one transaction should succeed
        $this->assertTrue($transaction1Success XOR $transaction2Success);

        // Sync and verify final state
        $syncResult = $this->syncManager->syncCollection('users');
        $this->assertTrue($syncResult->isSuccess());

        // Check final balance (should be either 1100 or 1200, not 1300)
        $finalUser = DB::table('firebase_sync_users')
            ->where('firebase_id', $userId)
            ->first();

        $this->assertNotNull($finalUser);
        $this->assertTrue(in_array($finalUser->balance, [1100, 1200]));
        $this->assertEquals(2, $finalUser->version);
    }

    /**
     * Test sync performance with large transaction batches.
     */
    public function test_sync_performance_with_large_transactions()
    {
        $startTime = microtime(true);

        // Create 500 users in a single transaction (testing Firestore limits)
        $userCount = 500;
        $builder = TransactionManager::builder();

        for ($i = 1; $i <= $userCount; $i++) {
            $builder->create('users', [
                'name' => "Performance User {$i}",
                'email' => "perf{$i}@example.com",
                'balance' => rand(100, 1000),
                'department' => ['Engineering', 'Marketing', 'Sales'][($i - 1) % 3],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $transactionResult = $builder->executeWithResult();
        $transactionTime = microtime(true) - $startTime;

        $this->assertTrue($transactionResult->isSuccess());
        $this->assertEquals($userCount, $transactionResult->getOperationCount());
        $this->assertLessThan(60, $transactionTime); // Should complete within 60 seconds

        // Test sync performance
        $syncStartTime = microtime(true);
        $syncResult = $this->syncManager->syncCollection('users', [
            'batch_size' => 100,
        ]);
        $syncTime = microtime(true) - $syncStartTime;

        $this->assertTrue($syncResult->isSuccess());
        $this->assertEquals($userCount, $syncResult->getSyncedCount());
        $this->assertLessThan(120, $syncTime); // Sync should complete within 2 minutes

        // Verify data integrity
        $this->assertDatabaseCount('firebase_sync_users', $userCount);

        // Test partial sync with filters
        $engineeringSyncResult = $this->syncManager->syncCollection('users', [
            'filters' => [
                ['field' => 'department', 'operator' => '==', 'value' => 'Engineering']
            ],
            'since' => now()->subMinutes(5),
        ]);

        $this->assertTrue($engineeringSyncResult->isSuccess());
        $expectedEngineeringCount = ceil($userCount / 3);
        $this->assertEquals($expectedEngineeringCount, $engineeringSyncResult->getSyncedCount());
    }

    /**
     * Test error recovery in sync after transaction failures.
     */
    public function test_error_recovery_sync_after_transaction_failures()
    {
        // Create some initial data
        $successfulResult = TransactionManager::execute(function ($transaction) {
            $userRef = $this->firestore->collection('users')->newDocument();
            $transaction->set($userRef, [
                'name' => 'Successful User',
                'email' => 'success@example.com',
                'balance' => 1000,
            ]);
            return $userRef->id();
        });

        // Attempt failing transaction
        try {
            TransactionManager::execute(function ($transaction) {
                $userRef = $this->firestore->collection('users')->newDocument();
                $transaction->set($userRef, [
                    'name' => 'Failed User',
                    'email' => 'failed@example.com',
                    'balance' => 500,
                ]);

                // Simulate failure
                throw new \Exception('Transaction failed');
            });
        } catch (\Exception $e) {
            // Expected failure
        }

        // Sync should handle mixed success/failure scenarios
        $syncResult = $this->syncManager->syncCollection('users', [
            'error_handling' => 'continue_on_error',
            'retry_failed' => true,
        ]);

        $this->assertTrue($syncResult->isSuccess());
        $this->assertEquals(1, $syncResult->getSyncedCount()); // Only successful user

        // Verify only successful data was synced
        $this->assertDatabaseHas('firebase_sync_users', [
            'firebase_id' => $successfulResult,
            'name' => 'Successful User',
        ]);

        $this->assertDatabaseMissing('firebase_sync_users', [
            'name' => 'Failed User',
        ]);
    }
}
