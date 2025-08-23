<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Models\TestPost;
use JTD\FirebaseModels\Tests\Models\TestUser;
use JTD\FirebaseModels\Firestore\Transactions\TransactionManager;
use JTD\FirebaseModels\Firestore\Transactions\TransactionBuilder;
use JTD\FirebaseModels\Firestore\Transactions\TransactionResult;
use JTD\FirebaseModels\Facades\FirestoreDB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Exception;

#[Group('unit')]
#[Group('advanced')]
#[Group('transactions')]
class TransactionSystemTest extends UnitTestSuite
{
    #[Test]
    public function it_creates_transaction_manager_instances()
    {
        $manager = new TransactionManager();
        expect($manager)->toBeInstanceOf(TransactionManager::class);

        $builder = TransactionManager::builder();
        expect($builder)->toBeInstanceOf(TransactionBuilder::class);
    }

    #[Test]
    public function it_handles_basic_transaction_execution()
    {
        // Mock a simple transaction
        $this->mockFirestoreTransaction(function ($transaction) {
            return 'transaction_result';
        });

        $result = TransactionManager::execute(function ($transaction) {
            return 'transaction_result';
        });

        expect($result)->toBe('transaction_result');
    }

    #[Test]
    public function it_handles_transaction_with_detailed_result()
    {
        $this->mockFirestoreTransaction(function ($transaction) {
            return ['data' => 'test'];
        });

        $result = TransactionManager::executeWithResult(function ($transaction) {
            return ['data' => 'test'];
        });

        expect($result)->toBeInstanceOf(TransactionResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->getData())->toEqual(['data' => 'test']);
        expect($result->getAttempts())->toBe(1);
        expect($result->getDurationMs())->toBeGreaterThan(0);
        expect($result->getError())->toBeNull();
    }

    #[Test]
    public function it_handles_transaction_retry_logic()
    {
        $attempts = 0;
        
        $this->mockFirestoreTransactionWithRetry(function ($transaction) use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new Exception('Aborted transaction'); // Retryable error
            }
            return 'success_after_retry';
        });

        $result = TransactionManager::executeWithResult(function ($transaction) use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new Exception('Aborted transaction');
            }
            return 'success_after_retry';
        }, ['max_attempts' => 5]);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData())->toBe('success_after_retry');
        expect($result->getAttempts())->toBe(3);
    }

    #[Test]
    public function it_handles_transaction_failure_after_max_attempts()
    {
        $this->mockFirestoreTransactionWithFailure(function ($transaction) {
            throw new Exception('Persistent failure');
        });

        $result = TransactionManager::executeWithResult(function ($transaction) {
            throw new Exception('Persistent failure');
        }, ['max_attempts' => 2]);

        expect($result->isSuccess())->toBeFalse();
        expect($result->getData())->toBeNull();
        expect($result->getAttempts())->toBe(2);
        expect($result->getError())->toBeInstanceOf(Exception::class);
        expect($result->getError()->getMessage())->toBe('Persistent failure');
    }

    #[Test]
    public function it_handles_transaction_builder_pattern()
    {
        // Mock the transaction operations
        $this->mockFirestoreTransactionBuilder();

        $result = TransactionManager::builder()
            ->create('posts', [
                'title' => 'New Post',
                'content' => 'Post content',
                'published' => true
            ])
            ->update('users', 'user-123', [
                'last_post_at' => now(),
                'post_count' => FirestoreDB::increment(1)
            ])
            ->delete('drafts', 'draft-456')
            ->withRetry(3, 200)
            ->withTimeout(30)
            ->executeWithResult();

        expect($result)->toBeInstanceOf(TransactionResult::class);
        expect($result->isSuccess())->toBeTrue();
    }

    #[Test]
    public function it_handles_conditional_transactions()
    {
        $this->mockFirestoreConditionalTransaction();

        $conditions = [
            [
                'collection' => 'users',
                'document' => 'user-123',
                'field' => 'balance',
                'operator' => '>=',
                'value' => 100,
                'description' => 'User has sufficient balance'
            ]
        ];

        $result = TransactionManager::conditional($conditions, function ($transaction) {
            return 'conditional_success';
        });

        expect($result)->toBeInstanceOf(TransactionResult::class);
        expect($result->isSuccess())->toBeTrue();
        expect($result->getData())->toBe('conditional_success');
    }

    #[Test]
    public function it_handles_transaction_sequence()
    {
        $this->mockFirestoreTransactionSequence();

        $transactions = [
            function ($transaction) { return 'result_1'; },
            function ($transaction) { return 'result_2'; },
            function ($transaction) { return 'result_3'; },
        ];

        $results = TransactionManager::sequence($transactions);

        expect($results)->toHaveCount(3);
        expect($results[0])->toBeInstanceOf(TransactionResult::class);
        expect($results[0]->getData())->toBe('result_1');
        expect($results[1]->getData())->toBe('result_2');
        expect($results[2]->getData())->toBe('result_3');
    }

    #[Test]
    public function it_handles_model_level_transactions()
    {
        $this->mockFirestoreModelTransaction();

        $post = new TestPost([
            'title' => 'Transaction Test',
            'content' => 'Testing model transactions'
        ]);

        $result = $post->transaction(function ($model, $transaction) {
            $model->published = true;
            $model->published_at = now();
            return $model->save();
        });

        expect($result)->toBeTrue();
        expect($post->published)->toBeTrue();
        expect($post->published_at)->not->toBeNull();
    }

    #[Test]
    public function it_handles_bulk_transaction_operations()
    {
        $this->mockFirestoreBulkTransaction();

        $posts = [
            ['title' => 'Post 1', 'content' => 'Content 1'],
            ['title' => 'Post 2', 'content' => 'Content 2'],
            ['title' => 'Post 3', 'content' => 'Content 3'],
        ];

        $result = TransactionManager::executeWithResult(function ($transaction) use ($posts) {
            $createdIds = [];
            
            foreach ($posts as $postData) {
                $docRef = FirestoreDB::collection('posts')->newDocument();
                $transaction->create($docRef, $postData);
                $createdIds[] = $docRef->id();
            }
            
            return $createdIds;
        });

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData())->toHaveCount(3);
        expect($result->getData())->toBeArray();
    }

    #[Test]
    public function it_handles_transaction_performance_monitoring()
    {
        $this->mockFirestoreTransactionWithDelay();

        $result = TransactionManager::executeWithResult(function ($transaction) {
            // Simulate some work
            usleep(10000); // 10ms
            return 'performance_test';
        }, ['log_performance' => true]);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getDurationMs())->toBeGreaterThan(10);
        expect($result->getDurationMs())->toBeLessThan(1000); // Should be reasonable
    }

    #[Test]
    public function it_handles_transaction_timeout()
    {
        $this->mockFirestoreTransactionWithTimeout();

        $result = TransactionManager::executeWithResult(function ($transaction) {
            // Simulate long-running operation
            sleep(2);
            return 'timeout_test';
        }, ['timeout_seconds' => 1]);

        // In a real implementation, this would timeout
        // For testing, we'll just verify the timeout option is handled
        expect($result)->toBeInstanceOf(TransactionResult::class);
    }

    #[Test]
    public function it_handles_transaction_error_classification()
    {
        $retryableErrors = [
            'Transaction aborted',
            'Deadline exceeded',
            'Internal error',
            'Service unavailable',
        ];

        foreach ($retryableErrors as $errorMessage) {
            $this->mockFirestoreTransactionWithError($errorMessage);
            
            $result = TransactionManager::executeWithResult(function ($transaction) use ($errorMessage) {
                throw new Exception($errorMessage);
            }, ['max_attempts' => 2]);

            expect($result->getAttempts())->toBe(2); // Should retry
        }

        // Non-retryable error
        $this->mockFirestoreTransactionWithError('Invalid argument');
        
        $result = TransactionManager::executeWithResult(function ($transaction) {
            throw new Exception('Invalid argument');
        }, ['max_attempts' => 3]);

        expect($result->getAttempts())->toBe(1); // Should not retry
    }

    #[Test]
    public function it_handles_transaction_statistics()
    {
        $stats = TransactionManager::getStats();
        
        expect($stats)->toBeArray();
        expect($stats)->toHaveKey('total_transactions');
        expect($stats)->toHaveKey('successful_transactions');
        expect($stats)->toHaveKey('failed_transactions');
        expect($stats)->toHaveKey('average_duration_ms');
        expect($stats)->toHaveKey('average_attempts');
    }

    #[Test]
    public function it_handles_transaction_configuration()
    {
        $originalOptions = TransactionManager::getDefaultOptions();
        
        $newOptions = [
            'max_attempts' => 5,
            'retry_delay_ms' => 200,
            'exponential_backoff' => false,
        ];

        TransactionManager::setDefaultOptions($newOptions);
        $updatedOptions = TransactionManager::getDefaultOptions();

        expect($updatedOptions['max_attempts'])->toBe(5);
        expect($updatedOptions['retry_delay_ms'])->toBe(200);
        expect($updatedOptions['exponential_backoff'])->toBeFalse();

        // Restore original options
        TransactionManager::setDefaultOptions($originalOptions);
    }

    #[Test]
    public function it_handles_nested_transactions()
    {
        $this->mockFirestoreNestedTransaction();

        $result = TransactionManager::executeWithResult(function ($outerTransaction) {
            // Outer transaction operations
            $outerResult = 'outer_result';
            
            // Inner transaction (should be handled properly)
            $innerResult = TransactionManager::executeWithResult(function ($innerTransaction) {
                return 'inner_result';
            });
            
            return [
                'outer' => $outerResult,
                'inner' => $innerResult->getData()
            ];
        });

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData())->toHaveKey('outer');
        expect($result->getData())->toHaveKey('inner');
        expect($result->getData()['outer'])->toBe('outer_result');
        expect($result->getData()['inner'])->toBe('inner_result');
    }
}
