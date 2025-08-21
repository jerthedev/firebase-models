<?php

namespace JTD\FirebaseModels\Tests\Helpers;

// Load stub classes for missing Google Cloud Firestore classes
require_once __DIR__ . '/FirestoreStubs.php';

use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Firebase\Contract\Firestore;

/**
 * UltraLightFirestoreMock provides the most memory-efficient Firestore mocking
 * by avoiding Mockery and anonymous classes entirely.
 */
class UltraLightFirestoreMock extends AbstractFirestoreMock
{

    /**
     * Get the mock type identifier.
     */
    public function getMockType(): string
    {
        return 'ultra';
    }

    /**
     * Get the memory efficiency level (1-3, where 3 is most efficient).
     */
    public function getMemoryEfficiencyLevel(): int
    {
        return 3;
    }

    /**
     * Get the feature completeness level (1-3, where 3 is most complete).
     */
    public function getFeatureCompletenessLevel(): int
    {
        return 2;
    }

    protected function setupMocks(): void
    {
        // Create simple mock implementations without anonymous classes
        $mockFirestoreClient = new MockFirestoreClient($this);
        $mockFirestore = new MockFirestore($mockFirestoreClient);



        // Bind to Laravel container using string names to avoid stub class conflicts
        app()->instance('Google\Cloud\Firestore\FirestoreClient', $mockFirestoreClient);
        app()->instance('Kreait\Firebase\Contract\Firestore', $mockFirestore);

        // Also try binding with class constants
        app()->instance(\Google\Cloud\Firestore\FirestoreClient::class, $mockFirestoreClient);
        app()->instance(\Kreait\Firebase\Contract\Firestore::class, $mockFirestore);
    }

    public function storeDocument(string $collection, string $id, array $data): void
    {
        parent::storeDocument($collection, $id, $data);
        $this->recordOperation('store', $collection, $id);
    }

    public function getDocument(string $collection, string $id): ?array
    {
        $this->recordOperation('get', $collection, $id);
        return parent::getDocument($collection, $id);
    }

    public function deleteDocument(string $collection, string $id): void
    {
        parent::deleteDocument($collection, $id);
        $this->recordOperation('delete', $collection, $id);
    }

    public function getCollectionDocuments(string $collection): array
    {
        $documents = [];
        $collectionData = $this->documents[$collection] ?? [];
        
        foreach ($collectionData as $id => $data) {
            $documents[] = new MockDocumentSnapshot($id, $data, true);
        }
        
        return $documents;
    }

    public function executeQuery(string $collection, array $wheres = [], array $orders = [], ?int $limit = null, ?int $offset = null): array
    {
        $this->recordQuery($collection, $wheres, $orders, $limit, $offset);
        
        $documents = $this->getCollectionDocuments($collection);
        
        // Apply where filters
        foreach ($wheres as $where) {
            $documents = array_filter($documents, function($doc) use ($where) {
                $field = $where['field'];
                $operator = $where['operator'];
                $value = $where['value'];

                // Handle nested field access (e.g., 'metadata.active')
                $docValue = $this->getNestedValue($doc->data(), $field);

                return match($operator) {
                    '=', '==' => $docValue == $value,
                    '!=' => $docValue != $value,
                    '>' => $docValue > $value,
                    '>=' => $docValue >= $value,
                    '<' => $docValue < $value,
                    '<=' => $docValue <= $value,
                    'in' => in_array($docValue, (array)$value),
                    'not-in' => !in_array($docValue, (array)$value),
                    'array-contains' => in_array($value, (array)$docValue),
                    'like' => str_contains(strtolower($docValue), strtolower($value)),
                    default => false,
                };
            });
        }
        
        // Apply ordering
        foreach (array_reverse($orders) as $order) {
            $field = $order['field'];
            $direction = $order['direction'] ?? 'asc';
            
            usort($documents, function($a, $b) use ($field, $direction) {
                $aVal = $a->data()[$field] ?? null;
                $bVal = $b->data()[$field] ?? null;
                
                $result = $aVal <=> $bVal;
                return $direction === 'desc' ? -$result : $result;
            });
        }
        
        // Apply offset
        if ($offset !== null && $offset > 0) {
            $documents = array_slice($documents, $offset);
        }
        
        // Apply limit
        if ($limit !== null && $limit > 0) {
            $documents = array_slice($documents, 0, $limit);
        }
        
        return array_values($documents);
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

    public function generateDocumentId(): string
    {
        return 'mock_' . uniqid() . '_' . random_int(1000, 9999);
    }

    /**
     * Get nested value from array using dot notation.
     */
    private function getNestedValue(array $data, string $field): mixed
    {
        if (!str_contains($field, '.')) {
            return $data[$field] ?? null;
        }

        $keys = explode('.', $field);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    // Public API methods (same as other mocks)
    public static function createDocument(string $collection, string $id, array $data = []): array
    {
        $instance = static::getInstance();
        $instance->storeDocument($collection, $id, $data);
        return array_merge(['id' => $id], $data);
    }

    // mockQuery is inherited from AbstractFirestoreMock

    public static function mockGet(string $collection, string $id, ?array $data = null): void
    {
        $instance = static::getInstance();
        if ($data !== null) {
            $instance->storeDocument($collection, $id, $data);
        }
    }

    public static function mockCreate(string $collection, ?string $id = null): void
    {
        // Ultra-light mock doesn't need explicit create mocking
        // Documents are created when accessed
    }

    public static function mockUpdate(string $collection, string $id): void
    {
        // Ultra-light mock doesn't need explicit update mocking
        // Updates happen automatically when documents are modified
    }

    public static function mockDelete(string $collection, string $id): void
    {
        $instance = static::getInstance();
        $instance->deleteDocument($collection, $id);
    }

    public static function clear(): void
    {
        // Clear Laravel container bindings
        if (app()->bound(FirestoreClient::class)) {
            app()->forgetInstance(FirestoreClient::class);
        }
        if (app()->bound(Firestore::class)) {
            app()->forgetInstance(Firestore::class);
        }

        // Call parent clear method
        parent::clear();
    }

    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function getQueryMocks(): array
    {
        return $this->queryMocks;
    }

    public static function assertOperationCalled(string $operation, string $collection, ?string $id = null): void
    {
        $instance = static::getInstance();

        $found = false;
        foreach ($instance->operations as $op) {
            if ($op['operation'] === $operation && $op['collection'] === $collection) {
                if ($id === null || $op['id'] === $id) {
                    $found = true;
                    break;
                }
            }
        }

        if (!$found) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Expected Firestore operation '{$operation}' on collection '{$collection}'" .
                ($id ? " with ID '{$id}'" : '') . " was not called."
            );
        }
    }
}

/**
 * Simple mock classes to replace anonymous classes
 */
class MockFirestore implements \Kreait\Firebase\Contract\Firestore
{
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function database(): \Google\Cloud\Firestore\FirestoreClient
    {
        return $this->client;
    }
}

class MockFirestoreClient extends \Google\Cloud\Firestore\FirestoreClient
{
    private $mock;

    public function __construct($mock)
    {
        $this->mock = $mock;
        // Don't call parent constructor to avoid Firebase setup
    }

    public function collection(string $name)
    {
        return new MockCollectionReference($name, $this->mock);
    }

    public function document(string $path)
    {
        [$collection, $id] = explode('/', $path, 2);
        return new MockDocumentReference($collection, $id, $this->mock);
    }

    // Add additional methods that might be called by the FirestoreDatabase
    public function batch()
    {
        return new MockWriteBatch($this->mock);
    }

    public function runTransaction(callable $updateFunction, array $options = [])
    {
        // Simple transaction mock - just execute the function
        return $updateFunction(new MockTransaction($this->mock));
    }

    public function bulkWriter(array $options = [])
    {
        return new MockBulkWriter($this->mock);
    }
}

class MockCollectionReference extends \Google\Cloud\Firestore\CollectionReference
{
    private $name;
    private $mock;

    public function __construct(string $name, $mock)
    {
        $this->name = $name;
        $this->mock = $mock;
        // Don't call parent constructor to avoid Firebase setup
    }

    public function document(?string $documentId = null)
    {
        $id = $documentId ?? $this->mock->generateDocumentId();
        return new MockDocumentReference($this->name, $id, $this->mock);
    }

    public function documents(array $options = [])
    {
        return $this->mock->getCollectionDocuments($this->name);
    }

    public function add(array $data)
    {
        $id = $this->mock->generateDocumentId();
        $this->mock->storeDocument($this->name, $id, $data);
        return new MockDocumentReference($this->name, $id, $this->mock);
    }

    public function where(string $field, string $operator, $value)
    {
        // Return a mock query that can be chained
        return new MockQuery($this->name, $this->mock, [
            ['field' => $field, 'operator' => $operator, 'value' => $value]
        ]);
    }

    public function orderBy(string $field, string $direction = 'ASC')
    {
        return new MockQuery($this->name, $this->mock, [], [
            ['field' => $field, 'direction' => $direction]
        ]);
    }

    public function limit(int $limit)
    {
        return new MockQuery($this->name, $this->mock, [], [], $limit);
    }
}

class MockDocumentReference extends \Google\Cloud\Firestore\DocumentReference
{
    private $collection;
    private $id;
    private $mock;

    public function __construct(string $collection, string $id, $mock)
    {
        $this->collection = $collection;
        $this->id = $id;
        $this->mock = $mock;
        // Don't call parent constructor to avoid Firebase setup
    }

    public function id(): string
    {
        return $this->id;
    }

    public function set(array $data, array $options = [])
    {
        $this->mock->storeDocument($this->collection, $this->id, $data);
        return true;
    }

    public function update(array $fields, array $options = [])
    {
        $existing = $this->mock->getDocument($this->collection, $this->id) ?? [];
        $merged = array_merge($existing, $fields);
        $this->mock->storeDocument($this->collection, $this->id, $merged);
        return true;
    }

    public function delete(array $options = [])
    {
        $this->mock->deleteDocument($this->collection, $this->id);
        return true;
    }

    public function snapshot(array $options = [])
    {
        $data = $this->mock->getDocument($this->collection, $this->id);
        return new MockDocumentSnapshot($this->id, $data, $data !== null);
    }
}

class MockDocumentSnapshot
{
    private $id;
    private $data;
    private $exists;

    public function __construct(string $id, ?array $data, bool $exists)
    {
        $this->id = $id;
        $this->data = $data ?? [];
        $this->exists = $exists;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function exists(): bool
    {
        return $this->exists;
    }
}

class MockWriteBatch
{
    private $mock;
    private $operations = [];

    public function __construct($mock)
    {
        $this->mock = $mock;
    }

    public function set($documentReference, array $data, array $options = [])
    {
        $this->operations[] = ['set', $documentReference, $data, $options];
        return $this;
    }

    public function update($documentReference, array $data, $precondition = null)
    {
        $this->operations[] = ['update', $documentReference, $data, $precondition];
        return $this;
    }

    public function delete($documentReference, $precondition = null)
    {
        $this->operations[] = ['delete', $documentReference, $precondition];
        return $this;
    }

    public function commit()
    {
        foreach ($this->operations as $operation) {
            [$type, $docRef] = $operation;
            if ($type === 'set') {
                $docRef->set($operation[2], $operation[3]);
            } elseif ($type === 'update') {
                $docRef->update($operation[2]);
            } elseif ($type === 'delete') {
                $docRef->delete();
            }
        }
        return true;
    }
}

class MockTransaction
{
    private $mock;

    public function __construct($mock)
    {
        $this->mock = $mock;
    }

    public function snapshot($documentReference)
    {
        return $documentReference->snapshot();
    }

    public function set($documentReference, array $data, array $options = [])
    {
        return $documentReference->set($data, $options);
    }

    public function update($documentReference, array $data, $precondition = null)
    {
        return $documentReference->update($data);
    }

    public function delete($documentReference, $precondition = null)
    {
        return $documentReference->delete();
    }
}

class MockBulkWriter
{
    private $mock;

    public function __construct($mock)
    {
        $this->mock = $mock;
    }

    public function set($documentReference, array $data, array $options = [])
    {
        return $documentReference->set($data, $options);
    }

    public function update($documentReference, array $data, $precondition = null)
    {
        return $documentReference->update($data);
    }

    public function delete($documentReference, $precondition = null)
    {
        return $documentReference->delete();
    }

    public function flush()
    {
        return true;
    }

    public function close()
    {
        return true;
    }
}

class MockQuery
{
    private $collection;
    private $mock;
    private $wheres;
    private $orders;
    private $limitValue;

    public function __construct(string $collection, $mock, array $wheres = [], array $orders = [], ?int $limit = null)
    {
        $this->collection = $collection;
        $this->mock = $mock;
        $this->wheres = $wheres;
        $this->orders = $orders;
        $this->limitValue = $limit;
    }

    public function where(string $field, string $operator, $value)
    {
        $wheres = $this->wheres;
        $wheres[] = ['field' => $field, 'operator' => $operator, 'value' => $value];
        return new MockQuery($this->collection, $this->mock, $wheres, $this->orders, $this->limitValue);
    }

    public function orderBy(string $field, string $direction = 'ASC')
    {
        $orders = $this->orders;
        $orders[] = ['field' => $field, 'direction' => $direction];
        return new MockQuery($this->collection, $this->mock, $this->wheres, $orders, $this->limitValue);
    }

    public function limit(int $limit)
    {
        return new MockQuery($this->collection, $this->mock, $this->wheres, $this->orders, $limit);
    }

    public function documents(array $options = [])
    {
        // Apply the query filters and return matching documents
        $allDocs = $this->mock->getCollectionDocuments($this->collection);

        // Apply where clauses
        foreach ($this->wheres as $where) {
            $allDocs = array_filter($allDocs, function($doc) use ($where) {
                return $this->applyWhereFilter($doc, $where);
            });
        }

        // Apply ordering
        if (!empty($this->orders)) {
            usort($allDocs, function($a, $b) {
                foreach ($this->orders as $order) {
                    $aVal = $a[$order['field']] ?? null;
                    $bVal = $b[$order['field']] ?? null;
                    $cmp = $aVal <=> $bVal;
                    if ($cmp !== 0) {
                        return $order['direction'] === 'DESC' ? -$cmp : $cmp;
                    }
                }
                return 0;
            });
        }

        // Apply limit
        if ($this->limitValue !== null) {
            $allDocs = array_slice($allDocs, 0, $this->limitValue);
        }

        return $allDocs;
    }

    private function applyWhereFilter(array $doc, array $where): bool
    {
        $field = $where['field'];
        $operator = $where['operator'];
        $value = $where['value'];
        $docValue = $doc[$field] ?? null;

        return match ($operator) {
            '=' => $docValue == $value,
            '!=' => $docValue != $value,
            '>' => $docValue > $value,
            '>=' => $docValue >= $value,
            '<' => $docValue < $value,
            '<=' => $docValue <= $value,
            'in' => is_array($value) && in_array($docValue, $value),
            'not-in' => is_array($value) && !in_array($docValue, $value),
            'array-contains' => is_array($docValue) && in_array($value, $docValue),
            default => false,
        };
    }
}
