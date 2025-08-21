<?php

namespace JTD\FirebaseModels\Firestore\Batch;

use Google\Cloud\Firestore\WriteBatch;
use Illuminate\Support\Collection;
use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Firestore\Batch\BatchResult;
use JTD\FirebaseModels\Firestore\Batch\Exceptions\BatchException;

/**
 * Fluent batch operation builder for complex batch operations.
 */
class BatchOperation
{
    protected array $operations = [];
    protected array $options;
    protected int $operationCount = 0;

    /**
     * Create a new batch operation.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Add a create operation.
     */
    public function create(string $collection, array $data, ?string $id = null): static
    {
        $this->validateOperationLimit();

        $this->operations[] = [
            'type' => 'create',
            'collection' => $collection,
            'data' => $data,
            'id' => $id,
        ];

        $this->operationCount++;
        return $this;
    }

    /**
     * Add an update operation.
     */
    public function update(string $collection, string $id, array $data): static
    {
        $this->validateOperationLimit();

        $this->operations[] = [
            'type' => 'update',
            'collection' => $collection,
            'id' => $id,
            'data' => $data,
        ];

        $this->operationCount++;
        return $this;
    }

    /**
     * Add a delete operation.
     */
    public function delete(string $collection, string $id): static
    {
        $this->validateOperationLimit();

        $this->operations[] = [
            'type' => 'delete',
            'collection' => $collection,
            'id' => $id,
        ];

        $this->operationCount++;
        return $this;
    }

    /**
     * Add a set operation (upsert).
     */
    public function set(string $collection, string $id, array $data, array $options = []): static
    {
        $this->validateOperationLimit();

        $this->operations[] = [
            'type' => 'set',
            'collection' => $collection,
            'id' => $id,
            'data' => $data,
            'options' => $options,
        ];

        $this->operationCount++;
        return $this;
    }

    /**
     * Add multiple create operations.
     */
    public function createMany(string $collection, array $documents): static
    {
        foreach ($documents as $document) {
            $this->create($collection, $document);
        }
        return $this;
    }

    /**
     * Add multiple update operations.
     */
    public function updateMany(string $collection, array $updates): static
    {
        foreach ($updates as $id => $data) {
            $this->update($collection, $id, $data);
        }
        return $this;
    }

    /**
     * Add multiple delete operations.
     */
    public function deleteMany(string $collection, array $ids): static
    {
        foreach ($ids as $id) {
            $this->delete($collection, $id);
        }
        return $this;
    }

    /**
     * Execute the batch operation.
     */
    public function execute(): BatchResult
    {
        if (empty($this->operations)) {
            return BatchResult::success(['message' => 'No operations to execute']);
        }

        $startTime = microtime(true);

        try {
            if ($this->operationCount <= ($this->options['chunk_size'] ?? 100)) {
                // Single batch
                return $this->executeSingleBatch();
            } else {
                // Multiple batches
                return $this->executeChunkedBatches();
            }
        } catch (\Exception $e) {
            return BatchResult::failure(
                'Batch execution failed: ' . $e->getMessage(),
                $e,
                microtime(true) - $startTime
            );
        }
    }

    /**
     * Execute as a single batch.
     */
    protected function executeSingleBatch(): BatchResult
    {
        $startTime = microtime(true);
        $batch = FirestoreDB::batch();
        $results = [];

        foreach ($this->operations as $operation) {
            $result = $this->executeOperation($batch, $operation);
            if ($result) {
                $results[] = $result;
            }
        }

        $batch->commit();

        return BatchResult::success([
            'operation_count' => $this->operationCount,
            'results' => $results,
            'batch_type' => 'single'
        ])->setDuration(microtime(true) - $startTime);
    }

    /**
     * Execute as multiple chunked batches.
     */
    protected function executeChunkedBatches(): BatchResult
    {
        $startTime = microtime(true);
        $chunkSize = $this->options['chunk_size'] ?? 100;
        $chunks = array_chunk($this->operations, $chunkSize);
        $allResults = [];
        $batchCount = 0;

        foreach ($chunks as $chunk) {
            $batch = FirestoreDB::batch();
            $chunkResults = [];

            foreach ($chunk as $operation) {
                $result = $this->executeOperation($batch, $operation);
                if ($result) {
                    $chunkResults[] = $result;
                }
            }

            $batch->commit();
            $allResults = array_merge($allResults, $chunkResults);
            $batchCount++;
        }

        return BatchResult::success([
            'operation_count' => $this->operationCount,
            'results' => $allResults,
            'batch_type' => 'chunked',
            'batch_count' => $batchCount,
            'chunk_size' => $chunkSize
        ])->setDuration(microtime(true) - $startTime);
    }

    /**
     * Execute a single operation within a batch.
     */
    protected function executeOperation(WriteBatch $batch, array $operation): ?string
    {
        switch ($operation['type']) {
            case 'create':
                return $this->executeCreate($batch, $operation);
                
            case 'update':
                $this->executeUpdate($batch, $operation);
                return $operation['id'];
                
            case 'delete':
                $this->executeDelete($batch, $operation);
                return $operation['id'];
                
            case 'set':
                $this->executeSet($batch, $operation);
                return $operation['id'];
                
            default:
                throw new BatchException("Unknown operation type: {$operation['type']}");
        }
    }

    /**
     * Execute a create operation.
     */
    protected function executeCreate(WriteBatch $batch, array $operation): string
    {
        $collection = FirestoreDB::collection($operation['collection']);
        
        if ($operation['id']) {
            $docRef = $collection->document($operation['id']);
            $batch->set($docRef, $operation['data']);
            return $operation['id'];
        } else {
            $docRef = $collection->newDocument();
            $batch->set($docRef, $operation['data']);
            return $docRef->id();
        }
    }

    /**
     * Execute an update operation.
     */
    protected function executeUpdate(WriteBatch $batch, array $operation): void
    {
        $docRef = FirestoreDB::collection($operation['collection'])->document($operation['id']);
        $batch->update($docRef, $operation['data']);
    }

    /**
     * Execute a delete operation.
     */
    protected function executeDelete(WriteBatch $batch, array $operation): void
    {
        $docRef = FirestoreDB::collection($operation['collection'])->document($operation['id']);
        $batch->delete($docRef);
    }

    /**
     * Execute a set operation.
     */
    protected function executeSet(WriteBatch $batch, array $operation): void
    {
        $docRef = FirestoreDB::collection($operation['collection'])->document($operation['id']);
        $batch->set($docRef, $operation['data'], $operation['options'] ?? []);
    }

    /**
     * Validate operation limit.
     */
    protected function validateOperationLimit(): void
    {
        $maxOperations = $this->options['max_operations'] ?? 500;
        
        if ($this->operationCount >= $maxOperations) {
            throw new BatchException("Maximum operations limit ({$maxOperations}) exceeded");
        }
    }

    /**
     * Get the current operation count.
     */
    public function getOperationCount(): int
    {
        return $this->operationCount;
    }

    /**
     * Get all operations.
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Clear all operations.
     */
    public function clear(): static
    {
        $this->operations = [];
        $this->operationCount = 0;
        return $this;
    }

    /**
     * Check if there are any operations.
     */
    public function hasOperations(): bool
    {
        return !empty($this->operations);
    }

    /**
     * Get a summary of the batch operation.
     */
    public function getSummary(): array
    {
        $operationTypes = array_count_values(array_column($this->operations, 'type'));
        
        return [
            'total_operations' => $this->operationCount,
            'operation_types' => $operationTypes,
            'estimated_batches' => ceil($this->operationCount / ($this->options['chunk_size'] ?? 100)),
            'options' => $this->options,
        ];
    }
}
