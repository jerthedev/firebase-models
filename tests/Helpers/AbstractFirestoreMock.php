<?php

namespace JTD\FirebaseModels\Tests\Helpers;

/**
 * AbstractFirestoreMock provides a common base for all Firestore mock implementations.
 * This consolidates shared functionality and establishes a consistent interface.
 */
abstract class AbstractFirestoreMock
{
    protected static ?self $instance = null;
    protected array $documents = [];
    protected array $operations = [];
    protected array $queryMocks = [];

    /**
     * Initialize the mock instance and set up mocking.
     */
    public static function initialize(): void
    {
        static::$instance = new static();
        static::$instance->setupMocks();
    }

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::initialize();
        }

        return static::$instance;
    }

    /**
     * Clear all mock data and reset the instance.
     */
    public static function clear(): void
    {
        if (static::$instance !== null) {
            static::$instance->clearData();
            static::$instance = null;
        }
    }

    /**
     * Set up the mock implementations. Must be implemented by subclasses.
     */
    abstract protected function setupMocks(): void;

    /**
     * Clear all stored data.
     */
    protected function clearData(): void
    {
        $this->documents = [];
        $this->operations = [];
        $this->queryMocks = [];
    }

    /**
     * Store a document in the mock database.
     */
    public function storeDocument(string $collection, string $id, array $data): void
    {
        if (!isset($this->documents[$collection])) {
            $this->documents[$collection] = [];
        }
        
        $this->documents[$collection][$id] = $data;
        $this->operations[] = ['type' => 'store', 'collection' => $collection, 'id' => $id, 'data' => $data];
    }

    /**
     * Get a document from the mock database.
     */
    public function getDocument(string $collection, string $id): ?array
    {
        return $this->documents[$collection][$id] ?? null;
    }

    /**
     * Delete a document from the mock database.
     */
    public function deleteDocument(string $collection, string $id): void
    {
        if (isset($this->documents[$collection][$id])) {
            unset($this->documents[$collection][$id]);
            $this->operations[] = ['type' => 'delete', 'collection' => $collection, 'id' => $id];
        }
    }

    /**
     * Get all documents in a collection.
     */
    public function getCollectionDocuments(string $collection): array
    {
        return $this->documents[$collection] ?? [];
    }

    /**
     * Generate a unique document ID.
     */
    public function generateDocumentId(): string
    {
        return 'mock_' . uniqid() . '_' . mt_rand(1000, 9999);
    }

    /**
     * Mock a query response for a collection.
     */
    public function mockQuery(string $collection, array $documents): void
    {
        $this->queryMocks[$collection] = $documents;
    }

    /**
     * Get mocked query results.
     */
    public function getQueryResults(string $collection): array
    {
        return $this->queryMocks[$collection] ?? [];
    }

    /**
     * Get all recorded operations.
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Get operations of a specific type.
     */
    public function getOperationsByType(string $type): array
    {
        return array_filter($this->operations, fn($op) => $op['type'] === $type);
    }

    /**
     * Check if a document exists.
     */
    public function documentExists(string $collection, string $id): bool
    {
        return isset($this->documents[$collection][$id]);
    }

    /**
     * Get the count of documents in a collection.
     */
    public function getCollectionCount(string $collection): int
    {
        return count($this->documents[$collection] ?? []);
    }

    /**
     * Get memory usage information for this mock.
     */
    public function getMemoryUsage(): array
    {
        return [
            'documents_count' => array_sum(array_map('count', $this->documents)),
            'operations_count' => count($this->operations),
            'query_mocks_count' => count($this->queryMocks),
            'memory_usage_bytes' => memory_get_usage(),
            'memory_peak_bytes' => memory_get_peak_usage(),
        ];
    }

    /**
     * Force garbage collection to free memory.
     */
    public function forceGarbageCollection(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Get the mock type identifier.
     */
    abstract public function getMockType(): string;

    /**
     * Get the memory efficiency level (1-3, where 3 is most efficient).
     */
    abstract public function getMemoryEfficiencyLevel(): int;

    /**
     * Get the feature completeness level (1-3, where 3 is most complete).
     */
    abstract public function getFeatureCompletenessLevel(): int;
}
