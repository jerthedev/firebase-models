<?php

namespace JTD\FirebaseModels\Tests\Helpers;

use Mockery;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\Query;
use Google\Cloud\Firestore\Timestamp;
use Illuminate\Support\Collection;

/**
 * FirestoreMock provides comprehensive mocking capabilities for Firestore operations
 * during testing, allowing tests to run without requiring actual Firebase connections.
 */
class FirestoreMock
{
    protected static ?self $instance = null;
    protected array $documents = [];
    protected array $operations = [];
    protected array $queryMocks = [];
    protected ?FirestoreClient $mockClient = null;

    public static function initialize(): void
    {
        static::$instance = new static();
        static::$instance->setupMocks();
    }

    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::initialize();
        }

        return static::$instance;
    }

    protected function setupMocks(): void
    {
        // Mock the FirestoreClient
        $this->mockClient = Mockery::mock(FirestoreClient::class);

        // Bind the mock client to the container
        app()->instance(FirestoreClient::class, $this->mockClient);

        // Also mock the Kreait Firestore contract to return our mock client
        $mockFirestore = Mockery::mock(\Kreait\Firebase\Contract\Firestore::class);
        $mockFirestore->shouldReceive('database')->andReturn($this->mockClient);
        app()->instance(\Kreait\Firebase\Contract\Firestore::class, $mockFirestore);

        // Set up default mock behaviors
        $this->setupDefaultMockBehaviors();

        // Set up batch and transaction support
        $this->setupBatchAndTransactionMocks();
    }

    protected function setupDefaultMockBehaviors(): void
    {
        // Mock collection() method
        $this->mockClient->shouldReceive('collection')
            ->andReturnUsing(function ($collectionName) {
                return $this->createMockCollection($collectionName);
            });

        // Mock document() method
        $this->mockClient->shouldReceive('document')
            ->andReturnUsing(function ($documentPath) {
                [$collection, $id] = explode('/', $documentPath, 2);
                return $this->createMockDocument($collection, $id);
            });
    }

    protected function createMockCollection(string $collectionName): CollectionReference
    {
        $mockCollection = Mockery::mock(CollectionReference::class);

        // Mock add() method for creating documents
        $mockCollection->shouldReceive('add')
            ->andReturnUsing(function ($data) use ($collectionName) {
                $id = $this->generateDocumentId();
                $this->storeDocument($collectionName, $id, $data);
                $this->recordOperation('create', $collectionName, $id);
                return $this->createMockDocumentReference($collectionName, $id);
            });

        // Mock document() method for getting document references
        $mockCollection->shouldReceive('document')
            ->andReturnUsing(function ($id = null) use ($collectionName) {
                $id = $id ?: $this->generateDocumentId();
                return $this->createMockDocumentReference($collectionName, $id);
            });

        // Mock where() method for queries
        $mockCollection->shouldReceive('where')
            ->andReturnUsing(function ($field, $operator, $value) use ($collectionName) {
                return $this->createMockQuery($collectionName, [
                    ['field' => $field, 'operator' => $operator, 'value' => $value]
                ]);
            });

        // Mock orderBy() method
        $mockCollection->shouldReceive('orderBy')
            ->andReturnUsing(function ($field, $direction = 'ASC') use ($collectionName) {
                return $this->createMockQuery($collectionName, [], [
                    ['field' => $field, 'direction' => $direction]
                ]);
            });

        // Mock limit() method
        $mockCollection->shouldReceive('limit')
            ->andReturnUsing(function ($limit) use ($collectionName) {
                return $this->createMockQuery($collectionName, [], [], $limit);
            });

        // Mock offset() method
        $mockCollection->shouldReceive('offset')
            ->andReturnUsing(function ($offset) use ($collectionName) {
                return $this->createMockQuery($collectionName, [], [], null, $offset);
            });

        // Mock documents() method for getting all documents
        $mockCollection->shouldReceive('documents')
            ->andReturnUsing(function () use ($collectionName) {
                return $this->getCollectionDocuments($collectionName);
            });

        return $mockCollection;
    }

    protected function createMockDocumentReference(string $collection, string $id): DocumentReference
    {
        $mockDocRef = Mockery::mock(DocumentReference::class);

        // Mock id() method
        $mockDocRef->shouldReceive('id')->andReturn($id);

        // Mock path() method
        $mockDocRef->shouldReceive('path')->andReturn("{$collection}/{$id}");

        // Mock set() method
        $mockDocRef->shouldReceive('set')
            ->andReturnUsing(function ($data, $options = []) use ($collection, $id) {
                $this->storeDocument($collection, $id, $data);
                $this->recordOperation('set', $collection, $id);
                return true;
            });

        // Mock update() method
        $mockDocRef->shouldReceive('update')
            ->andReturnUsing(function ($data) use ($collection, $id) {
                $this->updateDocument($collection, $id, $data);
                $this->recordOperation('update', $collection, $id);
                return true;
            });

        // Mock delete() method
        $mockDocRef->shouldReceive('delete')
            ->andReturnUsing(function () use ($collection, $id) {
                $this->deleteDocument($collection, $id);
                $this->recordOperation('delete', $collection, $id);
                return true;
            });

        // Mock snapshot() method
        $mockDocRef->shouldReceive('snapshot')
            ->andReturnUsing(function () use ($collection, $id) {
                return $this->createMockDocumentSnapshot($collection, $id);
            });

        return $mockDocRef;
    }

    protected function createMockDocumentSnapshot(string $collection, string $id): DocumentSnapshot
    {
        $mockSnapshot = Mockery::mock(DocumentSnapshot::class);
        $data = $this->getDocument($collection, $id);

        // Mock exists() method
        $mockSnapshot->shouldReceive('exists')->andReturn($data !== null);

        // Mock id() method
        $mockSnapshot->shouldReceive('id')->andReturn($id);

        // Mock data() method
        $mockSnapshot->shouldReceive('data')->andReturn($data);

        // Mock get() method for specific fields
        $mockSnapshot->shouldReceive('get')
            ->andReturnUsing(function ($field) use ($data) {
                return $data[$field] ?? null;
            });

        // Mock reference() method
        $mockSnapshot->shouldReceive('reference')
            ->andReturn($this->createMockDocumentReference($collection, $id));

        return $mockSnapshot;
    }

    protected function createMockQuery(string $collection, array $wheres = [], array $orders = [], ?int $limit = null, ?int $offset = null): Query
    {
        $mockQuery = Mockery::mock(Query::class);

        // Mock where() method for chaining
        $mockQuery->shouldReceive('where')
            ->andReturnUsing(function ($field, $operator, $value) use ($collection, $wheres, $orders, $limit, $offset) {
                $newWheres = array_merge($wheres, [
                    ['field' => $field, 'operator' => $operator, 'value' => $value]
                ]);
                return $this->createMockQuery($collection, $newWheres, $orders, $limit, $offset);
            });

        // Mock orderBy() method for chaining
        $mockQuery->shouldReceive('orderBy')
            ->andReturnUsing(function ($field, $direction = 'ASC') use ($collection, $wheres, $orders, $limit, $offset) {
                $newOrders = array_merge($orders, [
                    ['field' => $field, 'direction' => $direction]
                ]);
                return $this->createMockQuery($collection, $wheres, $newOrders, $limit, $offset);
            });

        // Mock limit() method for chaining
        $mockQuery->shouldReceive('limit')
            ->andReturnUsing(function ($newLimit) use ($collection, $wheres, $orders, $offset) {
                return $this->createMockQuery($collection, $wheres, $orders, $newLimit, $offset);
            });

        // Mock offset() method for chaining
        $mockQuery->shouldReceive('offset')
            ->andReturnUsing(function ($newOffset) use ($collection, $wheres, $orders, $limit) {
                return $this->createMockQuery($collection, $wheres, $orders, $limit, $newOffset);
            });

        // Mock documents() method for executing query
        $mockQuery->shouldReceive('documents')
            ->andReturnUsing(function () use ($collection, $wheres, $orders, $limit, $offset) {
                $this->recordQuery($collection, $wheres, $orders, $limit, $offset);
                return $this->executeQuery($collection, $wheres, $orders, $limit, $offset);
            });

        return $mockQuery;
    }

    protected function executeQuery(string $collection, array $wheres = [], array $orders = [], ?int $limit = null, ?int $offset = null): array
    {
        $documents = $this->getCollectionDocuments($collection);
        
        // Apply where filters
        foreach ($wheres as $where) {
            $documents = array_filter($documents, function ($doc) use ($where) {
                $data = $doc->data();
                $fieldValue = $data[$where['field']] ?? null;
                
                return match ($where['operator']) {
                    '=', '==' => $fieldValue == $where['value'],
                    '!=' => $fieldValue != $where['value'],
                    '>' => $fieldValue > $where['value'],
                    '>=' => $fieldValue >= $where['value'],
                    '<' => $fieldValue < $where['value'],
                    '<=' => $fieldValue <= $where['value'],
                    'in' => in_array($fieldValue, $where['value']),
                    'not-in' => !in_array($fieldValue, $where['value']),
                    'array-contains' => is_array($fieldValue) && in_array($where['value'], $fieldValue),
                    default => false,
                };
            });
        }

        // Apply ordering
        foreach ($orders as $order) {
            usort($documents, function ($a, $b) use ($order) {
                $aValue = $a->data()[$order['field']] ?? null;
                $bValue = $b->data()[$order['field']] ?? null;
                
                $result = $aValue <=> $bValue;
                
                return $order['direction'] === 'DESC' ? -$result : $result;
            });
        }

        // Apply offset
        if ($offset !== null) {
            $documents = array_slice($documents, $offset);
        }

        // Apply limit
        if ($limit !== null) {
            $documents = array_slice($documents, 0, $limit);
        }

        return array_values($documents);
    }

    protected function getCollectionDocuments(string $collection): array
    {
        $documents = [];
        
        foreach ($this->documents[$collection] ?? [] as $id => $data) {
            $documents[] = $this->createMockDocumentSnapshot($collection, $id);
        }
        
        return $documents;
    }

    public function storeDocument(string $collection, string $id, array $data): void
    {
        if (!isset($this->documents[$collection])) {
            $this->documents[$collection] = [];
        }
        
        $this->documents[$collection][$id] = $data;
    }

    public function updateDocument(string $collection, string $id, array $data): void
    {
        if (isset($this->documents[$collection][$id])) {
            $this->documents[$collection][$id] = array_merge(
                $this->documents[$collection][$id],
                $data
            );
        }
    }

    public function deleteDocument(string $collection, string $id): void
    {
        unset($this->documents[$collection][$id]);
    }

    public function getDocument(string $collection, string $id): ?array
    {
        return $this->documents[$collection][$id] ?? null;
    }

    protected function recordOperation(string $operation, string $collection, string $id): void
    {
        $this->operations[] = [
            'operation' => $operation,
            'collection' => $collection,
            'id' => $id,
            'timestamp' => microtime(true),
        ];
    }

    protected function recordQuery(string $collection, array $wheres, array $orders, ?int $limit, ?int $offset): void
    {
        $this->queryMocks[] = [
            'collection' => $collection,
            'wheres' => $wheres,
            'orders' => $orders,
            'limit' => $limit,
            'offset' => $offset,
            'timestamp' => microtime(true),
        ];
    }

    protected function generateDocumentId(): string
    {
        return 'mock_' . uniqid() . '_' . random_int(1000, 9999);
    }

    // Public API methods for testing

    public static function createDocument(string $collection, string $id, array $data = []): array
    {
        $instance = static::getInstance();
        $instance->storeDocument($collection, $id, $data);
        return array_merge(['id' => $id], $data);
    }

    public static function mockQuery(string $collection, array $documents = []): void
    {
        $instance = static::getInstance();
        foreach ($documents as $doc) {
            $instance->storeDocument($collection, $doc['id'], $doc);
        }
    }

    public static function mockGet(string $collection, string $id, ?array $data = null): void
    {
        $instance = static::getInstance();
        if ($data !== null) {
            $instance->storeDocument($collection, $id, $data);
        }
    }

    public static function mockCreate(string $collection, ?string $id = null): void
    {
        // Mock is set up to handle creates automatically
    }

    public static function mockUpdate(string $collection, string $id): void
    {
        // Mock is set up to handle updates automatically
    }

    public static function mockDelete(string $collection, string $id): void
    {
        // Mock is set up to handle deletes automatically
    }

    public static function assertOperationCalled(string $operation, string $collection, ?string $id = null): void
    {
        $instance = static::getInstance();
        
        $found = false;
        foreach ($instance->operations as $op) {
            if ($op['operation'] === $operation && 
                $op['collection'] === $collection && 
                ($id === null || $op['id'] === $id)) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $message = "Expected Firestore operation '{$operation}' on collection '{$collection}'";
            if ($id) {
                $message .= " with ID '{$id}'";
            }
            $message .= " was not called.";
            
            throw new \PHPUnit\Framework\AssertionFailedError($message);
        }
    }

    public static function assertQueryExecuted(string $collection, array $expectedWheres = []): void
    {
        $instance = static::getInstance();
        
        $found = false;
        foreach ($instance->queryMocks as $query) {
            if ($query['collection'] === $collection) {
                if (empty($expectedWheres) || $query['wheres'] === $expectedWheres) {
                    $found = true;
                    break;
                }
            }
        }
        
        if (!$found) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected Firestore query on collection '{$collection}' was not executed."
            );
        }
    }

    public static function clear(): void
    {
        if (static::$instance !== null) {
            $instance = static::$instance;
            $instance->documents = [];
            $instance->operations = [];
            $instance->queryMocks = [];

            // Clear mock client and close Mockery
            if ($instance->mockClient !== null) {
                $instance->mockClient = null;
            }
        }

        // Reset static instance to force recreation
        static::$instance = null;

        // Clear Laravel container bindings
        if (app()->bound(\Google\Cloud\Firestore\FirestoreClient::class)) {
            app()->forgetInstance(\Google\Cloud\Firestore\FirestoreClient::class);
        }
        if (app()->bound(\Kreait\Firebase\Contract\Firestore::class)) {
            app()->forgetInstance(\Kreait\Firebase\Contract\Firestore::class);
        }
    }

    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getQueries(): array
    {
        return $this->queryMocks;
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }

    protected function setupBatchAndTransactionMocks(): void
    {
        // Mock batch() method
        $this->mockClient->shouldReceive('batch')
            ->andReturnUsing(function () {
                return $this->createMockBatch();
            });

        // Mock runTransaction() method
        $this->mockClient->shouldReceive('runTransaction')
            ->andReturnUsing(function ($callable) {
                $transaction = $this->createMockTransaction();
                return $callable($transaction);
            });
    }

    protected function createMockBatch()
    {
        $mockBatch = Mockery::mock(\Google\Cloud\Firestore\WriteBatch::class);
        $operations = [];

        $mockBatch->shouldReceive('create')
            ->andReturnUsing(function ($docRef, $data) use (&$operations) {
                $operations[] = ['type' => 'create', 'ref' => $docRef, 'data' => $data];
                return $mockBatch;
            });

        $mockBatch->shouldReceive('set')
            ->andReturnUsing(function ($docRef, $data, $options = []) use (&$operations) {
                $operations[] = ['type' => 'set', 'ref' => $docRef, 'data' => $data, 'options' => $options];
                return $mockBatch;
            });

        $mockBatch->shouldReceive('update')
            ->andReturnUsing(function ($docRef, $data) use (&$operations) {
                $operations[] = ['type' => 'update', 'ref' => $docRef, 'data' => $data];
                return $mockBatch;
            });

        $mockBatch->shouldReceive('delete')
            ->andReturnUsing(function ($docRef) use (&$operations) {
                $operations[] = ['type' => 'delete', 'ref' => $docRef];
                return $mockBatch;
            });

        $mockBatch->shouldReceive('commit')
            ->andReturnUsing(function () use (&$operations) {
                foreach ($operations as $op) {
                    $this->executeBatchOperation($op);
                }
                return true;
            });

        return $mockBatch;
    }

    protected function createMockTransaction()
    {
        $mockTransaction = Mockery::mock(\Google\Cloud\Firestore\Transaction::class);
        $operations = [];

        $mockTransaction->shouldReceive('snapshot')
            ->andReturnUsing(function ($docRef) {
                // Extract collection and ID from document reference
                $path = $docRef->path();
                [$collection, $id] = explode('/', $path, 2);
                return $this->createMockDocumentSnapshot($collection, $id);
            });

        $mockTransaction->shouldReceive('create')
            ->andReturnUsing(function ($docRef, $data) use (&$operations) {
                $operations[] = ['type' => 'create', 'ref' => $docRef, 'data' => $data];
                return $mockTransaction;
            });

        $mockTransaction->shouldReceive('set')
            ->andReturnUsing(function ($docRef, $data, $options = []) use (&$operations) {
                $operations[] = ['type' => 'set', 'ref' => $docRef, 'data' => $data, 'options' => $options];
                return $mockTransaction;
            });

        $mockTransaction->shouldReceive('update')
            ->andReturnUsing(function ($docRef, $data) use (&$operations) {
                $operations[] = ['type' => 'update', 'ref' => $docRef, 'data' => $data];
                return $mockTransaction;
            });

        $mockTransaction->shouldReceive('delete')
            ->andReturnUsing(function ($docRef) use (&$operations) {
                $operations[] = ['type' => 'delete', 'ref' => $docRef];
                return $mockTransaction;
            });

        // Auto-commit transaction operations when transaction completes
        register_shutdown_function(function () use (&$operations) {
            foreach ($operations as $op) {
                $this->executeBatchOperation($op);
            }
        });

        return $mockTransaction;
    }

    protected function executeBatchOperation(array $operation): void
    {
        $docRef = $operation['ref'];
        $path = $docRef->path();
        [$collection, $id] = explode('/', $path, 2);

        switch ($operation['type']) {
            case 'create':
            case 'set':
                $this->storeDocument($collection, $id, $operation['data']);
                $this->recordOperation($operation['type'], $collection, $id);
                break;
            case 'update':
                $this->updateDocument($collection, $id, $operation['data']);
                $this->recordOperation('update', $collection, $id);
                break;
            case 'delete':
                $this->deleteDocument($collection, $id);
                $this->recordOperation('delete', $collection, $id);
                break;
        }
    }

    // Helper methods for testing

    public function filterDocuments(array $documents, array $wheres): array
    {
        $filtered = $documents;

        foreach ($wheres as $where) {
            $filtered = array_filter($filtered, function ($doc) use ($where) {
                $fieldValue = $doc[$where['field']] ?? null;

                return match ($where['operator']) {
                    '=', '==' => $fieldValue == $where['value'],
                    '!=' => $fieldValue != $where['value'],
                    '>' => $fieldValue > $where['value'],
                    '>=' => $fieldValue >= $where['value'],
                    '<' => $fieldValue < $where['value'],
                    '<=' => $fieldValue <= $where['value'],
                    'in' => in_array($fieldValue, $where['value']),
                    'not-in' => !in_array($fieldValue, $where['value']),
                    'array-contains' => is_array($fieldValue) && in_array($where['value'], $fieldValue),
                    default => false,
                };
            });
        }

        return array_values($filtered);
    }

    public function orderDocuments(array $documents, array $orders): array
    {
        $ordered = $documents;

        foreach ($orders as $order) {
            usort($ordered, function ($a, $b) use ($order) {
                $aValue = $a[$order['field']] ?? null;
                $bValue = $b[$order['field']] ?? null;

                $result = $aValue <=> $bValue;

                return strtolower($order['direction']) === 'desc' ? -$result : $result;
            });
        }

        return $ordered;
    }

    public function limitDocuments(array $documents, int $limit): array
    {
        return array_slice($documents, 0, $limit);
    }

    public function offsetDocuments(array $documents, int $offset): array
    {
        return array_slice($documents, $offset);
    }
}
