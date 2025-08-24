<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Firestore\Batch\BatchManager;
use JTD\FirebaseModels\Firestore\Batch\BatchOperation;
use JTD\FirebaseModels\Firestore\Batch\BatchResult;
use JTD\FirebaseModels\Tests\Models\TestPost;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('advanced')]
#[Group('batch-operations')]
class BatchOperationsTest extends UnitTestSuite
{
    #[Test]
    public function it_creates_batch_manager_instances()
    {
        $manager = new BatchManager();
        expect($manager)->toBeInstanceOf(BatchManager::class);

        $operation = BatchManager::create();
        expect($operation)->toBeInstanceOf(BatchOperation::class);
    }

    #[Test]
    public function it_handles_bulk_insert_operations()
    {
        $documents = [
            ['title' => 'Post 1', 'content' => 'Content 1', 'published' => true],
            ['title' => 'Post 2', 'content' => 'Content 2', 'published' => false],
            ['title' => 'Post 3', 'content' => 'Content 3', 'published' => true],
        ];

        $this->mockFirestoreBulkInsert('posts', $documents);

        $result = BatchManager::bulkInsert('posts', $documents);

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->getOperationCount())->toBe(3);
        expect($result->getInsertedCount())->toBe(3);
        expect($result->getUpdatedCount())->toBe(0);
        expect($result->getDeletedCount())->toBe(0);
    }

    #[Test]
    public function it_handles_bulk_update_operations()
    {
        $updates = [
            'post-1' => ['views' => 100, 'updated_at' => now()],
            'post-2' => ['views' => 200, 'updated_at' => now()],
            'post-3' => ['views' => 150, 'updated_at' => now()],
        ];

        $this->mockFirestoreBulkUpdate('posts', $updates);

        $result = BatchManager::bulkUpdate('posts', $updates);

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->getOperationCount())->toBe(3);
        expect($result->getUpdatedCount())->toBe(3);
        expect($result->getInsertedCount())->toBe(0);
        expect($result->getDeletedCount())->toBe(0);
    }

    #[Test]
    public function it_handles_bulk_delete_operations()
    {
        $documentIds = ['post-1', 'post-2', 'post-3'];

        $this->mockFirestoreBulkDelete('posts', $documentIds);

        $result = BatchManager::bulkDelete('posts', $documentIds);

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->getOperationCount())->toBe(3);
        expect($result->getDeletedCount())->toBe(3);
        expect($result->getInsertedCount())->toBe(0);
        expect($result->getUpdatedCount())->toBe(0);
    }

    #[Test]
    public function it_handles_mixed_batch_operations()
    {
        $this->mockFirestoreMixedBatch();

        $batch = BatchManager::create()
            ->insert('posts', 'new-post', [
                'title' => 'New Post',
                'content' => 'New content',
            ])
            ->update('posts', 'existing-post', [
                'views' => FirestoreDB::increment(1),
                'updated_at' => now(),
            ])
            ->delete('posts', 'old-post')
            ->upsert('users', 'user-123', [
                'last_active' => now(),
                'login_count' => FirestoreDB::increment(1),
            ]);

        $result = $batch->commit();

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->getOperationCount())->toBe(4);
        expect($result->getInsertedCount())->toBe(1);
        expect($result->getUpdatedCount())->toBe(2); // update + upsert
        expect($result->getDeletedCount())->toBe(1);
    }

    #[Test]
    public function it_handles_batch_size_limits()
    {
        // Firestore has a 500 operation limit per batch
        $largeDataset = [];
        for ($i = 0; $i < 600; $i++) {
            $largeDataset[] = [
                'title' => "Post {$i}",
                'content' => "Content for post {$i}",
                'index' => $i,
            ];
        }

        $this->mockFirestoreLargeBatch($largeDataset);

        $result = BatchManager::bulkInsert('posts', $largeDataset, [
            'chunk_size' => 500,
            'auto_chunk' => true,
        ]);

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->getOperationCount())->toBe(600);
        expect($result->getBatchCount())->toBe(2); // Should be split into 2 batches
        expect($result->getInsertedCount())->toBe(600);
    }

    #[Test]
    public function it_handles_batch_error_handling()
    {
        $this->mockFirestoreBatchWithError();

        $batch = BatchManager::create()
            ->insert('posts', 'valid-post', ['title' => 'Valid Post'])
            ->insert('posts', 'invalid-post', []); // Missing required fields

        $result = $batch->commit(['continue_on_error' => true]);

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->isSuccess())->toBeFalse();
        expect($result->hasErrors())->toBeTrue();
        expect($result->getErrors())->toHaveCount(1);
        expect($result->getSuccessfulOperations())->toBe(1);
        expect($result->getFailedOperations())->toBe(1);
    }

    #[Test]
    public function it_handles_batch_performance_monitoring()
    {
        $documents = [];
        for ($i = 0; $i < 100; $i++) {
            $documents[] = [
                'title' => "Performance Test {$i}",
                'content' => str_repeat('x', 100), // 100 chars
                'index' => $i,
            ];
        }

        $this->mockFirestorePerformanceBatch($documents);

        $startTime = microtime(true);
        $result = BatchManager::bulkInsert('posts', $documents, [
            'monitor_performance' => true,
        ]);
        $totalTime = (microtime(true) - $startTime) * 1000;

        expect($result->isSuccess())->toBeTrue();
        expect($result->getDurationMs())->toBeGreaterThan(0);
        expect($result->getDurationMs())->toBeLessThan($totalTime + 100); // Should be close
        expect($result->getOperationsPerSecond())->toBeGreaterThan(0);
    }

    #[Test]
    public function it_handles_batch_with_field_transforms()
    {
        $this->mockFirestoreBatchWithTransforms();

        $batch = BatchManager::create()
            ->insert('posts', 'post-1', [
                'title' => 'Post with Transforms',
                'created_at' => FirestoreDB::serverTimestamp(),
                'views' => 0,
                'tags' => [],
            ])
            ->update('posts', 'post-2', [
                'views' => FirestoreDB::increment(5),
                'updated_at' => FirestoreDB::serverTimestamp(),
                'tags' => FirestoreDB::arrayUnion(['php', 'laravel']),
            ])
            ->update('posts', 'post-3', [
                'tags' => FirestoreDB::arrayRemove(['deprecated']),
                'old_field' => FirestoreDB::delete(),
            ]);

        $result = $batch->commit();

        expect($result->isSuccess())->toBeTrue();
        expect($result->getOperationCount())->toBe(3);
    }

    #[Test]
    public function it_handles_conditional_batch_operations()
    {
        $this->mockFirestoreConditionalBatch();

        $batch = BatchManager::create()
            ->insertIf('posts', 'conditional-post', [
                'title' => 'Conditional Post',
            ], function ($docRef) {
                // Only insert if document doesn't exist
                return !$docRef->snapshot()->exists();
            })
            ->updateIf('users', 'user-123', [
                'last_login' => now(),
            ], function ($docRef) {
                // Only update if user is active
                $data = $docRef->snapshot()->data();

                return $data['active'] ?? false;
            });

        $result = $batch->commit();

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->isSuccess())->toBeTrue();
    }

    #[Test]
    public function it_handles_batch_rollback_simulation()
    {
        $this->mockFirestoreBatchRollback();

        $batch = BatchManager::create(['simulate_rollback' => true])
            ->insert('posts', 'rollback-test-1', ['title' => 'Test 1'])
            ->insert('posts', 'rollback-test-2', ['title' => 'Test 2'])
            ->insert('posts', 'rollback-test-3', ['title' => 'Test 3']);

        // Simulate a failure in the middle
        $result = $batch->commitWithRollback();

        expect($result)->toBeInstanceOf(BatchResult::class);
        // In a real implementation, this would test rollback behavior
    }

    #[Test]
    public function it_handles_batch_progress_tracking()
    {
        $documents = [];
        for ($i = 0; $i < 50; $i++) {
            $documents[] = ['title' => "Progress Test {$i}", 'index' => $i];
        }

        $this->mockFirestoreBatchProgress($documents);

        $progressUpdates = [];
        $result = BatchManager::bulkInsert('posts', $documents, [
            'progress_callback' => function ($completed, $total, $currentBatch) use (&$progressUpdates) {
                $progressUpdates[] = [
                    'completed' => $completed,
                    'total' => $total,
                    'batch' => $currentBatch,
                    'percentage' => round(($completed / $total) * 100, 2),
                ];
            },
            'chunk_size' => 10,
        ]);

        expect($result->isSuccess())->toBeTrue();
        expect($progressUpdates)->toHaveCount(5); // 50 items / 10 per batch = 5 batches
        expect($progressUpdates[4]['completed'])->toBe(50);
        expect($progressUpdates[4]['percentage'])->toBe(100.0);
    }

    #[Test]
    public function it_handles_batch_validation()
    {
        $this->mockFirestoreBatchValidation();

        $invalidDocuments = [
            ['title' => ''], // Empty title
            ['content' => 'No title'], // Missing title
            ['title' => 'Valid', 'content' => 'Valid content'], // Valid
        ];

        $result = BatchManager::bulkInsert('posts', $invalidDocuments, [
            'validate' => true,
            'validation_rules' => [
                'title' => 'required|min:1',
                'content' => 'string',
            ],
        ]);

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->hasValidationErrors())->toBeTrue();
        expect($result->getValidationErrors())->toHaveCount(2);
        expect($result->getValidDocuments())->toHaveCount(1);
    }

    #[Test]
    public function it_handles_batch_statistics()
    {
        $this->mockFirestoreBatchStatistics();

        $documents = array_fill(0, 25, ['title' => 'Stats Test', 'content' => 'Content']);

        $result = BatchManager::bulkInsert('posts', $documents, [
            'collect_statistics' => true,
        ]);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getStatistics())->toBeArray();
        expect($result->getStatistics())->toHaveKey('total_operations');
        expect($result->getStatistics())->toHaveKey('average_operation_time_ms');
        expect($result->getStatistics())->toHaveKey('throughput_ops_per_second');
        expect($result->getStatistics())->toHaveKey('memory_usage_mb');
    }

    #[Test]
    public function it_handles_model_level_batch_operations()
    {
        $this->mockFirestoreModelBatch();

        $posts = [
            new TestPost(['title' => 'Batch Post 1', 'content' => 'Content 1']),
            new TestPost(['title' => 'Batch Post 2', 'content' => 'Content 2']),
            new TestPost(['title' => 'Batch Post 3', 'content' => 'Content 3']),
        ];

        $result = TestPost::batchInsert($posts);

        expect($result)->toBeInstanceOf(BatchResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->getInsertedCount())->toBe(3);

        // Verify models have IDs assigned
        foreach ($posts as $post) {
            expect($post->getKey())->not->toBeNull();
            expect($post->exists)->toBeTrue();
        }
    }

    #[Test]
    public function it_handles_batch_memory_optimization()
    {
        $this->enableMemoryMonitoring();

        // Create large dataset
        $largeDataset = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeDataset[] = [
                'title' => "Memory Test {$i}",
                'content' => str_repeat('x', 500), // 500 chars per document
                'index' => $i,
                'metadata' => array_fill(0, 10, "meta_{$i}"),
            ];
        }

        $this->mockFirestoreLargeMemoryBatch($largeDataset);

        $initialMemory = memory_get_usage(true);

        $result = BatchManager::bulkInsert('posts', $largeDataset, [
            'memory_efficient' => true,
            'chunk_size' => 100,
            'clear_processed_chunks' => true,
        ]);

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // MB

        expect($result->isSuccess())->toBeTrue();
        expect($result->getOperationCount())->toBe(1000);
        expect($memoryIncrease)->toBeLessThan(50); // Should use less than 50MB additional
    }
}
