<?php

namespace JTD\FirebaseModels\Firestore\Transactions;

use Google\Cloud\Firestore\Transaction;
use JTD\FirebaseModels\Facades\FirestoreDB;

/**
 * Builder for complex transaction operations.
 */
class TransactionBuilder
{
    protected array $operations = [];

    protected array $options = [];

    protected array $conditions = [];

    /**
     * Add a create operation to the transaction.
     */
    public function create(string $collection, array $data, ?string $id = null): static
    {
        $this->operations[] = [
            'type' => 'create',
            'collection' => $collection,
            'data' => $data,
            'id' => $id,
        ];

        return $this;
    }

    /**
     * Add an update operation to the transaction.
     */
    public function update(string $collection, string $id, array $data): static
    {
        $this->operations[] = [
            'type' => 'update',
            'collection' => $collection,
            'id' => $id,
            'data' => $data,
        ];

        return $this;
    }

    /**
     * Add a delete operation to the transaction.
     */
    public function delete(string $collection, string $id): static
    {
        $this->operations[] = [
            'type' => 'delete',
            'collection' => $collection,
            'id' => $id,
        ];

        return $this;
    }

    /**
     * Add a conditional operation.
     */
    public function when(string $collection, string $id, array $conditions): static
    {
        $this->conditions[] = [
            'collection' => $collection,
            'id' => $id,
            'conditions' => $conditions,
        ];

        return $this;
    }

    /**
     * Set transaction options.
     */
    public function withOptions(array $options): static
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Set retry options.
     */
    public function withRetry(int $maxAttempts = 3, int $retryDelay = 100): static
    {
        $this->options['max_attempts'] = $maxAttempts;
        $this->options['retry_delay'] = $retryDelay;

        return $this;
    }

    /**
     * Set timeout.
     */
    public function withTimeout(int $seconds): static
    {
        $this->options['timeout'] = $seconds;

        return $this;
    }

    /**
     * Enable or disable logging.
     */
    public function withLogging(bool $enabled = true): static
    {
        $this->options['log_attempts'] = $enabled;

        return $this;
    }

    /**
     * Execute the transaction.
     */
    public function execute(): mixed
    {
        return TransactionManager::execute(function (Transaction $transaction) {
            return $this->executeOperations($transaction);
        }, $this->options);
    }

    /**
     * Execute with retry logic.
     */
    public function executeWithRetry(?int $maxAttempts = null): mixed
    {
        $attempts = $maxAttempts ?? $this->options['max_attempts'] ?? 3;

        return TransactionManager::executeWithRetry(function (Transaction $transaction) {
            return $this->executeOperations($transaction);
        }, $attempts, $this->options);
    }

    /**
     * Execute and return detailed result.
     */
    public function executeWithResult(): TransactionResult
    {
        return TransactionManager::executeWithResult(function (Transaction $transaction) {
            return $this->executeOperations($transaction);
        }, $this->options);
    }

    /**
     * Execute all operations within the transaction.
     */
    protected function executeOperations(Transaction $transaction): array
    {
        $results = [];

        // First, check all conditions
        foreach ($this->conditions as $condition) {
            $this->checkCondition($transaction, $condition);
        }

        // Then execute all operations
        foreach ($this->operations as $index => $operation) {
            $results[$index] = $this->executeOperation($transaction, $operation);
        }

        return $results;
    }

    /**
     * Check a condition.
     */
    protected function checkCondition(Transaction $transaction, array $condition): void
    {
        $docRef = FirestoreDB::collection($condition['collection'])->document($condition['id']);
        $snapshot = $transaction->snapshot($docRef);

        if (!$snapshot->exists()) {
            throw new \Exception("Document {$condition['collection']}/{$condition['id']} does not exist");
        }

        $data = $snapshot->data();

        foreach ($condition['conditions'] as $field => $expectedValue) {
            $actualValue = $data[$field] ?? null;

            if ($actualValue !== $expectedValue) {
                throw new \Exception(
                    "Condition failed: {$field} expected '{$expectedValue}', got '{$actualValue}'"
                );
            }
        }
    }

    /**
     * Execute a single operation.
     */
    protected function executeOperation(Transaction $transaction, array $operation): mixed
    {
        switch ($operation['type']) {
            case 'create':
                return $this->executeCreate($transaction, $operation);

            case 'update':
                return $this->executeUpdate($transaction, $operation);

            case 'delete':
                return $this->executeDelete($transaction, $operation);

            default:
                throw new \InvalidArgumentException("Unknown operation type: {$operation['type']}");
        }
    }

    /**
     * Execute a create operation.
     */
    protected function executeCreate(Transaction $transaction, array $operation): string
    {
        $collection = FirestoreDB::collection($operation['collection']);

        if ($operation['id']) {
            $docRef = $collection->document($operation['id']);
            $transaction->set($docRef, $operation['data']);

            return $operation['id'];
        } else {
            $docRef = $collection->newDocument();
            $transaction->set($docRef, $operation['data']);

            return $docRef->id();
        }
    }

    /**
     * Execute an update operation.
     */
    protected function executeUpdate(Transaction $transaction, array $operation): void
    {
        $docRef = FirestoreDB::collection($operation['collection'])->document($operation['id']);
        $transaction->update($docRef, $operation['data']);
    }

    /**
     * Execute a delete operation.
     */
    protected function executeDelete(Transaction $transaction, array $operation): void
    {
        $docRef = FirestoreDB::collection($operation['collection'])->document($operation['id']);
        $transaction->delete($docRef);
    }

    /**
     * Get the number of operations.
     */
    public function getOperationCount(): int
    {
        return count($this->operations);
    }

    /**
     * Get the number of conditions.
     */
    public function getConditionCount(): int
    {
        return count($this->conditions);
    }

    /**
     * Clear all operations and conditions.
     */
    public function clear(): static
    {
        $this->operations = [];
        $this->conditions = [];

        return $this;
    }

    /**
     * Get a summary of the transaction.
     */
    public function getSummary(): array
    {
        return [
            'operations' => count($this->operations),
            'conditions' => count($this->conditions),
            'options' => $this->options,
        ];
    }
}
