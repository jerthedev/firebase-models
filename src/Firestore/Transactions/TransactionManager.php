<?php

namespace JTD\FirebaseModels\Firestore\Transactions;

use Google\Cloud\Firestore\Transaction;
use Illuminate\Support\Facades\Log;
use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Firestore\Transactions\TransactionResult;
use JTD\FirebaseModels\Firestore\Transactions\Exceptions\TransactionException;
use JTD\FirebaseModels\Firestore\Transactions\Exceptions\TransactionRetryException;
use JTD\FirebaseModels\Firestore\Transactions\TransactionBuilder;

/**
 * Enhanced transaction manager with retry logic and error handling.
 */
class TransactionManager
{
    /**
     * Default transaction options.
     */
    protected static array $defaultOptions = [
        'max_attempts' => 3,
        'retry_delay' => 100, // milliseconds
        'timeout' => 30, // seconds
        'log_attempts' => true,
    ];

    /**
     * Execute a transaction with enhanced error handling.
     */
    public static function execute(callable $callback, array $options = []): mixed
    {
        $options = array_merge(static::$defaultOptions, $options);
        $startTime = microtime(true);

        try {
            $result = FirestoreDB::runTransaction(function (Transaction $transaction) use ($callback) {
                return $callback($transaction);
            }, $options);

            if ($options['log_attempts']) {
                Log::info('Transaction completed successfully', [
                    'duration' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                    'options' => $options
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            if ($options['log_attempts']) {
                Log::error('Transaction failed', [
                    'error' => $e->getMessage(),
                    'duration' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                    'options' => $options
                ]);
            }

            throw new TransactionException(
                'Transaction failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Execute a transaction with custom retry logic.
     */
    public static function executeWithRetry(callable $callback, int $maxAttempts = 3, array $options = []): mixed
    {
        $options = array_merge(static::$defaultOptions, $options, ['max_attempts' => $maxAttempts]);
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $maxAttempts) {
            try {
                $startTime = microtime(true);

                $result = static::execute($callback, array_merge($options, ['log_attempts' => false]));

                if ($options['log_attempts'] && $attempt > 1) {
                    Log::info('Transaction succeeded after retry', [
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'duration' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
                    ]);
                }

                return $result;

            } catch (TransactionException $e) {
                $lastException = $e;

                if ($attempt === $maxAttempts) {
                    break;
                }

                if ($options['log_attempts']) {
                    Log::warning('Transaction attempt failed, retrying', [
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'error' => $e->getMessage(),
                        'next_retry_in' => $options['retry_delay'] . 'ms'
                    ]);
                }

                // Wait before retry
                if ($options['retry_delay'] > 0) {
                    usleep($options['retry_delay'] * 1000);
                }

                $attempt++;
            }
        }

        throw new TransactionRetryException(
            "Transaction failed after {$maxAttempts} attempts. Last error: " . $lastException->getMessage(),
            $lastException->getCode(),
            $lastException
        );
    }

    /**
     * Execute a transaction and return a detailed result.
     */
    public static function executeWithResult(callable $callback, array $options = []): TransactionResult
    {
        $startTime = microtime(true);
        $result = new TransactionResult();

        try {
            $data = static::execute($callback, $options);
            
            $result->setSuccess(true)
                   ->setData($data)
                   ->setDuration(microtime(true) - $startTime)
                   ->setAttempts(1);

        } catch (TransactionRetryException $e) {
            $result->setSuccess(false)
                   ->setError($e->getMessage())
                   ->setException($e)
                   ->setDuration(microtime(true) - $startTime)
                   ->setAttempts($options['max_attempts'] ?? static::$defaultOptions['max_attempts']);

        } catch (TransactionException $e) {
            $result->setSuccess(false)
                   ->setError($e->getMessage())
                   ->setException($e)
                   ->setDuration(microtime(true) - $startTime)
                   ->setAttempts(1);
        }

        return $result;
    }

    /**
     * Execute multiple transactions in sequence.
     */
    public static function executeSequence(array $transactions, array $options = []): array
    {
        $results = [];
        $options = array_merge(static::$defaultOptions, $options);

        foreach ($transactions as $key => $transaction) {
            try {
                $results[$key] = static::execute($transaction, $options);
            } catch (TransactionException $e) {
                if ($options['stop_on_failure'] ?? true) {
                    throw $e;
                }
                $results[$key] = $e;
            }
        }

        return $results;
    }

    /**
     * Execute a transaction with a timeout.
     */
    public static function executeWithTimeout(callable $callback, int $timeoutSeconds, array $options = []): mixed
    {
        $options = array_merge($options, ['timeout' => $timeoutSeconds]);
        
        // Set up timeout handling
        $startTime = time();
        
        return static::execute(function (Transaction $transaction) use ($callback, $timeoutSeconds, $startTime) {
            // Check timeout before executing
            if (time() - $startTime >= $timeoutSeconds) {
                throw new TransactionException('Transaction timeout exceeded');
            }
            
            return $callback($transaction);
        }, $options);
    }

    /**
     * Create a transaction builder for complex operations.
     */
    public static function builder(): TransactionBuilder
    {
        return new TransactionBuilder();
    }

    /**
     * Set default options for all transactions.
     */
    public static function setDefaultOptions(array $options): void
    {
        static::$defaultOptions = array_merge(static::$defaultOptions, $options);
    }

    /**
     * Get current default options.
     */
    public static function getDefaultOptions(): array
    {
        return static::$defaultOptions;
    }

    /**
     * Check if a transaction is currently active.
     */
    public static function isActive(): bool
    {
        // Firestore doesn't provide a direct way to check this
        // This would need to be tracked manually if needed
        return false;
    }

    /**
     * Get transaction statistics.
     */
    public static function getStats(): array
    {
        // This would require implementing transaction tracking
        // For now, return basic info
        return [
            'default_options' => static::$defaultOptions,
            'active_transactions' => 0, // Would need tracking
        ];
    }
}
