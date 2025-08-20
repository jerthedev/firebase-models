<?php

namespace JTD\FirebaseModels\Tests\Helpers;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\Query;

/**
 * LightweightFirestoreMock provides a memory-efficient alternative to the full FirestoreMock
 * for tests that encounter memory issues with Mockery.
 */
class LightweightFirestoreMock
{
    protected static ?self $instance = null;
    protected array $documents = [];
    protected array $operations = [];
    protected array $queryMocks = [];

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
        // Create a simple mock that just tracks operations
        $mockClient = new class {
            private $mock;
            
            public function __construct()
            {
                $this->mock = LightweightFirestoreMock::getInstance();
            }
            
            public function collection(string $name)
            {
                return new class($name, $this->mock) {
                    private $name;
                    private $mock;
                    
                    public function __construct($name, $mock)
                    {
                        $this->name = $name;
                        $this->mock = $mock;
                    }
                    
                    public function document(string $id = null)
                    {
                        $id = $id ?: $this->mock->generateDocumentId();
                        return new class($this->name, $id, $this->mock) {
                            private $collection;
                            private $id;
                            private $mock;
                            
                            public function __construct($collection, $id, $mock)
                            {
                                $this->collection = $collection;
                                $this->id = $id;
                                $this->mock = $mock;
                            }
                            
                            public function id()
                            {
                                return $this->id;
                            }
                            
                            public function path()
                            {
                                return $this->collection . '/' . $this->id;
                            }
                            
                            public function set(array $data, array $options = [])
                            {
                                $this->mock->storeDocument($this->collection, $this->id, $data);
                                $this->mock->recordOperation('set', $this->collection, $this->id);
                                return true;
                            }
                            
                            public function update(array $data)
                            {
                                $this->mock->updateDocument($this->collection, $this->id, $data);
                                $this->mock->recordOperation('update', $this->collection, $this->id);
                                return true;
                            }
                            
                            public function delete()
                            {
                                $this->mock->deleteDocument($this->collection, $this->id);
                                $this->mock->recordOperation('delete', $this->collection, $this->id);
                                return true;
                            }
                            
                            public function snapshot()
                            {
                                $data = $this->mock->getDocument($this->collection, $this->id);
                                return new class($this->id, $data) {
                                    private $id;
                                    private $data;
                                    
                                    public function __construct($id, $data)
                                    {
                                        $this->id = $id;
                                        $this->data = $data;
                                    }
                                    
                                    public function exists()
                                    {
                                        return $this->data !== null;
                                    }
                                    
                                    public function id()
                                    {
                                        return $this->id;
                                    }
                                    
                                    public function data()
                                    {
                                        return $this->data;
                                    }
                                    
                                    public function get(string $field)
                                    {
                                        return $this->data[$field] ?? null;
                                    }
                                };
                            }
                        };
                    }
                    
                    public function add(array $data)
                    {
                        $id = $this->mock->generateDocumentId();
                        $this->mock->storeDocument($this->name, $id, $data);
                        $this->mock->recordOperation('create', $this->name, $id);
                        return $this->document($id);
                    }
                    
                    public function where(string $field, string $operator, $value)
                    {
                        return new class($this->name, [['field' => $field, 'operator' => $operator, 'value' => $value]], [], null, null, $this->mock) {
                            private $collection;
                            private $wheres;
                            private $orders;
                            private $limit;
                            private $offset;
                            private $mock;
                            
                            public function __construct($collection, $wheres, $orders, $limit, $offset, $mock)
                            {
                                $this->collection = $collection;
                                $this->wheres = $wheres;
                                $this->orders = $orders;
                                $this->limit = $limit;
                                $this->offset = $offset;
                                $this->mock = $mock;
                            }
                            
                            public function where(string $field, string $operator, $value)
                            {
                                $newWheres = array_merge($this->wheres, [['field' => $field, 'operator' => $operator, 'value' => $value]]);
                                return new self($this->collection, $newWheres, $this->orders, $this->limit, $this->offset, $this->mock);
                            }
                            
                            public function orderBy(string $field, string $direction = 'ASC')
                            {
                                $newOrders = array_merge($this->orders, [['field' => $field, 'direction' => $direction]]);
                                return new self($this->collection, $this->wheres, $newOrders, $this->limit, $this->offset, $this->mock);
                            }
                            
                            public function limit(int $limit)
                            {
                                return new self($this->collection, $this->wheres, $this->orders, $limit, $this->offset, $this->mock);
                            }
                            
                            public function offset(int $offset)
                            {
                                return new self($this->collection, $this->wheres, $this->orders, $this->limit, $offset, $this->mock);
                            }
                            
                            public function documents()
                            {
                                $this->mock->recordQuery($this->collection, $this->wheres, $this->orders, $this->limit, $this->offset);
                                return $this->mock->executeQuery($this->collection, $this->wheres, $this->orders, $this->limit, $this->offset);
                            }
                        };
                    }
                    
                    public function orderBy(string $field, string $direction = 'ASC')
                    {
                        return $this->where('__dummy__', '!=', null)->orderBy($field, $direction);
                    }
                    
                    public function limit(int $limit)
                    {
                        return $this->where('__dummy__', '!=', null)->limit($limit);
                    }
                    
                    public function offset(int $offset)
                    {
                        return $this->where('__dummy__', '!=', null)->offset($offset);
                    }
                    
                    public function documents()
                    {
                        return $this->mock->getCollectionDocuments($this->name);
                    }
                };
            }
        };

        // Bind the mock client to the container
        app()->instance(FirestoreClient::class, $mockClient);

        // Also mock the Kreait Firestore contract
        $mockFirestore = new class($mockClient) {
            private $client;
            
            public function __construct($client)
            {
                $this->client = $client;
            }
            
            public function database()
            {
                return $this->client;
            }
        };
        
        app()->instance(\Kreait\Firebase\Contract\Firestore::class, $mockFirestore);
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

    public function recordOperation(string $operation, string $collection, string $id): void
    {
        $this->operations[] = [
            'operation' => $operation,
            'collection' => $collection,
            'id' => $id,
            'timestamp' => microtime(true),
        ];
    }

    public function recordQuery(string $collection, array $wheres, array $orders, ?int $limit, ?int $offset): void
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

    public function executeQuery(string $collection, array $wheres = [], array $orders = [], ?int $limit = null, ?int $offset = null): array
    {
        $documents = $this->getCollectionDocuments($collection);
        
        // Apply where filters
        foreach ($wheres as $where) {
            if ($where['field'] === '__dummy__') continue; // Skip dummy filters
            
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

    public function getCollectionDocuments(string $collection): array
    {
        $documents = [];
        
        foreach ($this->documents[$collection] ?? [] as $id => $data) {
            $documents[] = new class($id, $data) {
                private $id;
                private $data;
                
                public function __construct($id, $data)
                {
                    $this->id = $id;
                    $this->data = $data;
                }
                
                public function exists()
                {
                    return true;
                }
                
                public function id()
                {
                    return $this->id;
                }
                
                public function data()
                {
                    return $this->data;
                }
                
                public function get(string $field)
                {
                    return $this->data[$field] ?? null;
                }
            };
        }
        
        return $documents;
    }

    // Public API methods for testing (same as FirestoreMock)

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
        $instance = static::getInstance();
        $instance->documents = [];
        $instance->operations = [];
        $instance->queryMocks = [];
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
}
