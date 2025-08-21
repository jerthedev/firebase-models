<?php

namespace Tests\Integration;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\FirebaseModels\Firestore\Batch\BatchManager;
use JTD\FirebaseModels\Firestore\Transactions\TransactionManager;
use JTD\FirebaseModels\Sync\SyncManager;
use JTD\FirebaseModels\Testing\BatchTestHelper;

/**
 * Performance and stress tests for Sprint 3 features.
 */
class Sprint3PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->performanceMetrics = [];
    }

    protected function tearDown(): void
    {
        // Log performance metrics
        if (!empty($this->performanceMetrics)) {
            $this->logPerformanceResults();
        }
        parent::tearDown();
    }

    /**
     * Test batch operation performance with large datasets.
     */
    public function test_batch_operation_performance()
    {
        $testSizes = [100, 500, 1000];
        
        foreach ($testSizes as $size) {
            $this->runBatchPerformanceTest($size);
        }

        // Verify performance scales reasonably
        $this->assertPerformanceScaling('batch_insert');
    }

    /**
     * Test transaction performance under load.
     */
    public function test_transaction_performance_under_load()
    {
        $concurrentTransactions = 10;
        $results = [];

        for ($i = 0; $i < $concurrentTransactions; $i++) {
            $startTime = microtime(true);
            
            $result = TransactionManager::executeWithResult(function ($transaction) use ($i) {
                // Create user
                $userRef = $this->firestore->collection('users')->newDocument();
                $transaction->set($userRef, [
                    'name' => "Concurrent User {$i}",
                    'email' => "user{$i}@example.com",
                    'balance' => 1000,
                    'created_at' => now(),
                ]);

                // Create profile
                $profileRef = $this->firestore->collection('profiles')->newDocument();
                $transaction->set($profileRef, [
                    'user_id' => $userRef->id(),
                    'bio' => "Bio for user {$i}",
                    'preferences' => ['theme' => 'dark'],
                    'created_at' => now(),
                ]);

                return ['user_id' => $userRef->id(), 'profile_id' => $profileRef->id()];
            });

            $duration = microtime(true) - $startTime;
            
            $results[] = [
                'success' => $result->isSuccess(),
                'duration' => $duration,
                'attempts' => $result->getAttempts(),
            ];
        }

        // Analyze results
        $successCount = collect($results)->where('success', true)->count();
        $avgDuration = collect($results)->avg('duration');
        $maxDuration = collect($results)->max('duration');

        $this->assertGreaterThanOrEqual(8, $successCount); // At least 80% success rate
        $this->assertLessThan(10, $avgDuration); // Average under 10 seconds
        $this->assertLessThan(30, $maxDuration); // Max under 30 seconds

        $this->performanceMetrics['concurrent_transactions'] = [
            'count' => $concurrentTransactions,
            'success_rate' => $successCount / $concurrentTransactions,
            'avg_duration' => $avgDuration,
            'max_duration' => $maxDuration,
        ];
    }

    /**
     * Test sync performance with large datasets.
     */
    public function test_sync_performance()
    {
        $syncManager = app(SyncManager::class);
        
        // Create large dataset
        $userData = BatchTestHelper::createTestData(2000, [
            'name' => 'Sync User {i}',
            'email' => 'sync{i}@example.com',
            'department' => ['Engineering', 'Marketing', 'Sales']['{i}' % 3],
            'balance' => rand(100, 1000),
        ]);

        // Batch insert
        $insertStart = microtime(true);
        $batchResult = BatchManager::bulkInsert('users', $userData, [
            'chunk_size' => 100,
        ]);
        $insertDuration = microtime(true) - $insertStart;

        $this->assertTrue($batchResult->isSuccess());
        $this->assertEquals(2000, $batchResult->getOperationCount());

        // Initial sync
        $syncStart = microtime(true);
        $syncResult = $syncManager->syncCollection('users', [
            'batch_size' => 200,
        ]);
        $syncDuration = microtime(true) - $syncStart;

        $this->assertTrue($syncResult->isSuccess());
        $this->assertEquals(2000, $syncResult->getSyncedCount());

        // Incremental sync (should be faster)
        $incrementalStart = microtime(true);
        $incrementalResult = $syncManager->syncCollection('users', [
            'since' => now()->subMinutes(1),
        ]);
        $incrementalDuration = microtime(true) - $incrementalStart;

        $this->assertTrue($incrementalResult->isSuccess());

        $this->performanceMetrics['sync_performance'] = [
            'dataset_size' => 2000,
            'insert_duration' => $insertDuration,
            'initial_sync_duration' => $syncDuration,
            'incremental_sync_duration' => $incrementalDuration,
            'insert_rate' => 2000 / $insertDuration,
            'sync_rate' => 2000 / $syncDuration,
        ];

        // Performance assertions
        $this->assertLessThan(120, $insertDuration); // Insert under 2 minutes
        $this->assertLessThan(180, $syncDuration); // Sync under 3 minutes
        $this->assertLessThan(30, $incrementalDuration); // Incremental under 30 seconds
    }

    /**
     * Test relationship loading performance.
     */
    public function test_relationship_loading_performance()
    {
        // Create test data with relationships
        $userCount = 100;
        $postsPerUser = 10;

        // Create users
        $userData = BatchTestHelper::createTestData($userCount, [
            'name' => 'User {i}',
            'email' => 'user{i}@example.com',
        ]);

        $userResult = BatchManager::bulkInsert('users', $userData);
        $userIds = $userResult->getData()['document_ids'];

        // Create posts
        $postData = [];
        foreach ($userIds as $userId) {
            for ($i = 1; $i <= $postsPerUser; $i++) {
                $postData[] = [
                    'title' => "Post {$i}",
                    'content' => 'Post content',
                    'user_id' => $userId,
                    'status' => 'published',
                ];
            }
        }

        BatchManager::bulkInsert('posts', $postData);

        // Test N+1 query problem
        $nPlusOneStart = microtime(true);
        $usersWithoutEager = TestUser::limit(50)->get();
        foreach ($usersWithoutEager as $user) {
            $postCount = $user->posts->count(); // Individual queries
        }
        $nPlusOneDuration = microtime(true) - $nPlusOneStart;

        // Test eager loading
        $eagerStart = microtime(true);
        $usersWithEager = TestUser::with('posts')->limit(50)->get();
        foreach ($usersWithEager as $user) {
            $postCount = $user->posts->count(); // Preloaded data
        }
        $eagerDuration = microtime(true) - $eagerStart;

        // Eager loading should be significantly faster
        $this->assertLessThan($nPlusOneDuration * 0.5, $eagerDuration);

        $this->performanceMetrics['relationship_loading'] = [
            'dataset_size' => $userCount,
            'posts_per_user' => $postsPerUser,
            'n_plus_one_duration' => $nPlusOneDuration,
            'eager_loading_duration' => $eagerDuration,
            'performance_improvement' => $nPlusOneDuration / $eagerDuration,
        ];
    }

    /**
     * Test memory usage under load.
     */
    public function test_memory_usage_under_load()
    {
        $initialMemory = memory_get_usage(true);

        // Large batch operation
        $userData = BatchTestHelper::createTestData(5000, [
            'name' => 'Memory Test User {i}',
            'email' => 'memory{i}@example.com',
            'data' => str_repeat('x', 1000), // 1KB per user
        ]);

        $batchResult = BatchManager::bulkInsert('users', $userData, [
            'chunk_size' => 500,
        ]);

        $peakMemory = memory_get_peak_usage(true);
        $memoryUsed = $peakMemory - $initialMemory;

        $this->assertTrue($batchResult->isSuccess());
        $this->assertLessThan(100 * 1024 * 1024, $memoryUsed); // Under 100MB

        $this->performanceMetrics['memory_usage'] = [
            'dataset_size' => 5000,
            'initial_memory_mb' => $initialMemory / 1024 / 1024,
            'peak_memory_mb' => $peakMemory / 1024 / 1024,
            'memory_used_mb' => $memoryUsed / 1024 / 1024,
            'memory_per_record_kb' => ($memoryUsed / 5000) / 1024,
        ];
    }

    /**
     * Test error recovery performance.
     */
    public function test_error_recovery_performance()
    {
        $errorRecoveryStart = microtime(true);
        $successCount = 0;
        $errorCount = 0;

        // Simulate operations with random failures
        for ($i = 0; $i < 100; $i++) {
            try {
                $result = TransactionManager::executeWithRetry(function ($transaction) use ($i) {
                    // Simulate 20% failure rate
                    if (rand(1, 100) <= 20) {
                        throw new \Exception("Simulated failure for operation {$i}");
                    }

                    $userRef = $this->firestore->collection('users')->newDocument();
                    $transaction->set($userRef, [
                        'name' => "Recovery Test User {$i}",
                        'email' => "recovery{$i}@example.com",
                        'created_at' => now(),
                    ]);

                    return $userRef->id();
                }, 3); // 3 retry attempts

                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
            }
        }

        $errorRecoveryDuration = microtime(true) - $errorRecoveryStart;

        // Should have reasonable success rate with retries
        $this->assertGreaterThan(70, $successCount); // At least 70% success
        $this->assertLessThan(60, $errorRecoveryDuration); // Under 1 minute

        $this->performanceMetrics['error_recovery'] = [
            'total_operations' => 100,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'success_rate' => $successCount / 100,
            'duration' => $errorRecoveryDuration,
        ];
    }

    /**
     * Run batch performance test for a specific size.
     */
    private function runBatchPerformanceTest(int $size): void
    {
        $testData = BatchTestHelper::createTestData($size, [
            'name' => 'Perf User {i}',
            'email' => 'perf{i}@example.com',
            'data' => str_repeat('x', 100), // 100 bytes per record
        ]);

        $startTime = microtime(true);
        $result = BatchManager::bulkInsert('users', $testData, [
            'chunk_size' => min(100, $size),
        ]);
        $duration = microtime(true) - $startTime;

        $this->assertTrue($result->isSuccess());
        $this->assertEquals($size, $result->getOperationCount());

        $this->performanceMetrics['batch_insert'][$size] = [
            'size' => $size,
            'duration' => $duration,
            'rate' => $size / $duration,
            'batch_type' => $result->getBatchType(),
        ];

        // Performance assertions based on size
        $maxDuration = $size <= 100 ? 10 : ($size <= 500 ? 30 : 60);
        $this->assertLessThan($maxDuration, $duration);
    }

    /**
     * Assert that performance scales reasonably.
     */
    private function assertPerformanceScaling(string $operation): void
    {
        $metrics = $this->performanceMetrics[$operation] ?? [];
        
        if (count($metrics) < 2) {
            return; // Need at least 2 data points
        }

        $sizes = array_keys($metrics);
        sort($sizes);

        // Check that rate doesn't degrade too much as size increases
        $firstRate = $metrics[$sizes[0]]['rate'];
        $lastRate = $metrics[$sizes[count($sizes) - 1]]['rate'];

        // Rate should not degrade by more than 50%
        $this->assertGreaterThan($firstRate * 0.5, $lastRate);
    }

    /**
     * Log performance results for analysis.
     */
    private function logPerformanceResults(): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'test_class' => static::class,
            'metrics' => $this->performanceMetrics,
        ];

        // Log to file for analysis
        file_put_contents(
            storage_path('logs/sprint3-performance.json'),
            json_encode($logData, JSON_PRETTY_PRINT) . "\n",
            FILE_APPEND | LOCK_EX
        );

        // Output summary to console
        echo "\n=== Sprint 3 Performance Test Results ===\n";
        foreach ($this->performanceMetrics as $test => $metrics) {
            echo "{$test}: " . json_encode($metrics, JSON_PRETTY_PRINT) . "\n";
        }
        echo "==========================================\n";
    }

    /**
     * Helper method to create test models.
     */
    private function createTestModels(): void
    {
        if (!class_exists('TestUser')) {
            eval('
                class TestUser extends JTD\FirebaseModels\Firestore\FirestoreModel
                {
                    protected $collection = "users";
                    protected $fillable = ["name", "email", "bio", "department", "balance", "data"];
                    
                    public function posts()
                    {
                        return $this->hasMany(TestPost::class, "user_id");
                    }
                }
                
                class TestPost extends JTD\FirebaseModels\Firestore\FirestoreModel
                {
                    protected $collection = "posts";
                    protected $fillable = ["title", "content", "user_id", "status"];
                    
                    public function user()
                    {
                        return $this->belongsTo(TestUser::class, "user_id");
                    }
                }
            ');
        }
    }
}
