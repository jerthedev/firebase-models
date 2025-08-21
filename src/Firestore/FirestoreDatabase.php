<?php

namespace JTD\FirebaseModels\Firestore;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\Query;
use Google\Cloud\Firestore\Transaction;
use Google\Cloud\Firestore\WriteBatch;
use Kreait\Firebase\Contract\Firestore;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Closure;

/**
 * Laravel-style wrapper around the Kreait Firebase Firestore client.
 *
 * This class provides a 1:1 compatible API with Laravel's DB facade for Firestore,
 * including query builder, pagination, transactions, and event system.
 */
class FirestoreDatabase
{
    protected FirestoreClient $client;
    protected array $queryListeners = [];
    protected ?Closure $queryCallback = null;
    protected int $queryTimeThreshold = 0;

    public function __construct(Firestore $firestore)
    {
        $this->client = $firestore->database();
    }

    /**
     * Get the underlying Firestore client.
     */
    public function getClient(): FirestoreClient
    {
        return $this->client;
    }

    /**
     * Begin a fluent query against a collection (Laravel DB::table equivalent).
     */
    public function table(string $collection): FirestoreQueryBuilder
    {
        return new FirestoreQueryBuilder($this, $collection);
    }

    /**
     * Get a collection reference.
     */
    public function collection(string $path): CollectionReference
    {
        return $this->client->collection($path);
    }

    /**
     * Get a document reference.
     */
    public function document(string $path): DocumentReference
    {
        return $this->client->document($path);
    }

    /**
     * Get a document by path.
     */
    public function doc(string $path): DocumentReference
    {
        return $this->document($path);
    }

    /**
     * Execute a raw Firestore query (Laravel DB::select equivalent).
     */
    public function select(string $collection, array $constraints = []): Collection
    {
        $startTime = microtime(true);

        try {
            $query = $this->collection($collection);

            foreach ($constraints as $constraint) {
                $query = $query->where($constraint['field'], $constraint['operator'], $constraint['value']);
            }

            $documents = $query->documents();
            $results = new Collection();

            foreach ($documents as $document) {
                $data = $document->data();
                $data['id'] = $document->id();
                $results->push((object) $data);
            }

            $this->logQuery('select', $collection, $constraints, microtime(true) - $startTime);

            return $results;
        } catch (\Exception $e) {
            $this->logQuery('select', $collection, $constraints, microtime(true) - $startTime, $e);
            throw $e;
        }
    }

    /**
     * Insert a new document (Laravel DB::insert equivalent).
     */
    public function insert(string $collection, array $data): bool
    {
        $startTime = microtime(true);

        try {
            if (isset($data[0]) && is_array($data[0])) {
                // Multiple records
                $batch = $this->client->batch();
                foreach ($data as $record) {
                    $docRef = $this->collection($collection)->newDocument();
                    $batch->set($docRef, $record);
                }
                $batch->commit();
            } else {
                // Single record
                $this->collection($collection)->add($data);
            }

            $this->logQuery('insert', $collection, $data, microtime(true) - $startTime);
            return true;
        } catch (\Exception $e) {
            $this->logQuery('insert', $collection, $data, microtime(true) - $startTime, $e);
            throw $e;
        }
    }

    /**
     * Insert a new document and get the ID (Laravel DB::insertGetId equivalent).
     */
    public function insertGetId(string $collection, array $data): string
    {
        $startTime = microtime(true);

        try {
            $docRef = $this->collection($collection)->add($data);
            $id = $docRef->id();

            $this->logQuery('insertGetId', $collection, $data, microtime(true) - $startTime);
            return $id;
        } catch (\Exception $e) {
            $this->logQuery('insertGetId', $collection, $data, microtime(true) - $startTime, $e);
            throw $e;
        }
    }

    /**
     * Create a new document with auto-generated ID.
     */
    public function add(string $collection, array $data): DocumentReference
    {
        return $this->collection($collection)->add($data);
    }

    /**
     * Set a document (create or overwrite).
     */
    public function set(string $path, array $data, array $options = []): void
    {
        $this->document($path)->set($data, $options);
    }

    /**
     * Update a document.
     */
    public function update(string $path, array $data, ?array $precondition = null): void
    {
        $options = [];
        if ($precondition !== null) {
            $options['precondition'] = $precondition;
        }

        $this->document($path)->update($data, $options);
    }

    /**
     * Delete a document.
     */
    public function delete(string $path, ?array $precondition = null): void
    {
        $options = [];
        if ($precondition !== null) {
            $options['precondition'] = $precondition;
        }

        $this->document($path)->delete($options);
    }

    /**
     * Get a document snapshot.
     */
    public function get(string $path): ?\Google\Cloud\Firestore\DocumentSnapshot
    {
        $snapshot = $this->document($path)->snapshot();
        return $snapshot->exists() ? $snapshot : null;
    }

    /**
     * Check if a document exists.
     */
    public function exists(string $path): bool
    {
        return $this->document($path)->snapshot()->exists();
    }

    /**
     * Execute a closure within a database transaction (Laravel DB::transaction equivalent).
     */
    public function transaction(callable $callback, int $attempts = 1): mixed
    {
        $startTime = microtime(true);

        try {
            $result = $this->client->runTransaction(function (Transaction $transaction) use ($callback) {
                return $callback($transaction);
            });

            $this->logQuery('transaction', 'multiple', [], microtime(true) - $startTime);
            return $result;
        } catch (\Exception $e) {
            $this->logQuery('transaction', 'multiple', [], microtime(true) - $startTime, $e);

            if ($attempts > 1) {
                // Add exponential backoff delay
                $delay = (4 - $attempts) * 100; // 100ms, 200ms, 300ms
                if ($delay > 0) {
                    usleep($delay * 1000);
                }
                return $this->transaction($callback, $attempts - 1);
            }

            throw $e;
        }
    }

    /**
     * Execute a transaction with enhanced retry logic.
     */
    public function transactionWithRetry(callable $callback, int $maxAttempts = 3, array $options = []): mixed
    {
        return \JTD\FirebaseModels\Firestore\Transactions\TransactionManager::executeWithRetry(
            $callback,
            $maxAttempts,
            $options
        );
    }

    /**
     * Execute a transaction and return detailed result.
     */
    public function transactionWithResult(callable $callback, array $options = []): \JTD\FirebaseModels\Firestore\Transactions\TransactionResult
    {
        return \JTD\FirebaseModels\Firestore\Transactions\TransactionManager::executeWithResult(
            $callback,
            $options
        );
    }

    /**
     * Create a transaction builder for complex operations.
     */
    public function transactionBuilder(): \JTD\FirebaseModels\Firestore\Transactions\TransactionBuilder
    {
        return \JTD\FirebaseModels\Firestore\Transactions\TransactionManager::builder();
    }

    /**
     * Create a batch operation builder.
     */
    public function batchBuilder(array $options = []): \JTD\FirebaseModels\Firestore\Batch\BatchOperation
    {
        return \JTD\FirebaseModels\Firestore\Batch\BatchManager::create($options);
    }

    /**
     * Bulk insert documents.
     */
    public function bulkInsert(string $collection, array $documents, array $options = []): \JTD\FirebaseModels\Firestore\Batch\BatchResult
    {
        return \JTD\FirebaseModels\Firestore\Batch\BatchManager::bulkInsert($collection, $documents, $options);
    }

    /**
     * Bulk update documents.
     */
    public function bulkUpdate(string $collection, array $updates, array $options = []): \JTD\FirebaseModels\Firestore\Batch\BatchResult
    {
        return \JTD\FirebaseModels\Firestore\Batch\BatchManager::bulkUpdate($collection, $updates, $options);
    }

    /**
     * Bulk delete documents.
     */
    public function bulkDelete(string $collection, array $documentIds, array $options = []): \JTD\FirebaseModels\Firestore\Batch\BatchResult
    {
        return \JTD\FirebaseModels\Firestore\Batch\BatchManager::bulkDelete($collection, $documentIds, $options);
    }

    /**
     * Bulk upsert documents.
     */
    public function bulkUpsert(string $collection, array $documents, array $options = []): \JTD\FirebaseModels\Firestore\Batch\BatchResult
    {
        return \JTD\FirebaseModels\Firestore\Batch\BatchManager::bulkUpsert($collection, $documents, $options);
    }

    /**
     * Run a transaction (direct Firestore API).
     */
    public function runTransaction(callable $updateFunction, array $options = []): mixed
    {
        return $this->client->runTransaction($updateFunction, $options);
    }

    /**
     * Create a new write batch.
     */
    public function batch(): WriteBatch
    {
        return $this->client->batch();
    }

    /**
     * Begin a transaction manually.
     */
    public function beginTransaction(): void
    {
        // Firestore doesn't support manual transaction control like SQL databases
        // Transactions must be run within the transaction() method
        throw new \BadMethodCallException('Firestore does not support manual transaction control. Use transaction() method instead.');
    }

    /**
     * Commit a transaction manually.
     */
    public function commit(): void
    {
        throw new \BadMethodCallException('Firestore does not support manual transaction control. Use transaction() method instead.');
    }

    /**
     * Rollback a transaction manually.
     */
    public function rollBack(): void
    {
        throw new \BadMethodCallException('Firestore does not support manual transaction control. Use transaction() method instead.');
    }

    /**
     * Create a query for a collection.
     */
    public function query(string $collection): Query
    {
        return $this->collection($collection);
    }

    /**
     * Execute a collection group query.
     */
    public function collectionGroup(string $collectionId): Query
    {
        return $this->client->collectionGroup($collectionId);
    }

    /**
     * Get all documents from a collection.
     */
    public function getCollection(string $path): \Google\Cloud\Firestore\QuerySnapshot
    {
        return $this->collection($path)->documents();
    }

    /**
     * Get documents with a simple where clause.
     */
    public function where(string $collection, string $field, string $operator, mixed $value): Query
    {
        return $this->collection($collection)->where($field, $operator, $value);
    }

    /**
     * Order documents by a field.
     */
    public function orderBy(string $collection, string $field, string $direction = 'ASC'): Query
    {
        return $this->collection($collection)->orderBy($field, $direction);
    }

    /**
     * Limit the number of documents returned.
     */
    public function limit(string $collection, int $limit): Query
    {
        return $this->collection($collection)->limit($limit);
    }

    /**
     * Get the project ID.
     */
    public function getProjectId(): string
    {
        return $this->client->projectId();
    }

    /**
     * Get the database ID.
     */
    public function getDatabaseId(): string
    {
        return $this->client->databaseId();
    }

    /**
     * Register a query event listener (Laravel DB::listen equivalent).
     */
    public function listen(Closure $callback): void
    {
        $this->queryListeners[] = $callback;
    }

    /**
     * Set a callback to be executed when queries exceed a time threshold.
     */
    public function whenQueryingForLongerThan(int $threshold, Closure $callback): void
    {
        $this->queryTimeThreshold = $threshold;
        $this->queryCallback = $callback;
    }

    /**
     * Get the connection name (for compatibility).
     */
    public function getName(): string
    {
        return 'firestore';
    }

    /**
     * Get the PDO instance (not applicable for Firestore).
     */
    public function getPdo(): void
    {
        throw new \BadMethodCallException('Firestore does not use PDO connections.');
    }

    /**
     * Log a query execution for monitoring and events.
     */
    protected function logQuery(string $type, string $collection, array $bindings, float $time, ?\Exception $exception = null): void
    {
        $timeMs = $time * 1000;

        // Create a pseudo-SQL query for logging
        $sql = $this->createPseudoSql($type, $collection, $bindings);

        // Fire Laravel query event
        if (class_exists(QueryExecuted::class)) {
            Event::dispatch(new QueryExecuted($sql, $bindings, $timeMs, $this));
        }

        // Call registered listeners
        foreach ($this->queryListeners as $listener) {
            $listener((object) [
                'sql' => $sql,
                'bindings' => $bindings,
                'time' => $timeMs,
                'connection' => $this,
                'exception' => $exception,
            ]);
        }

        // Check time threshold
        if ($this->queryCallback && $timeMs > $this->queryTimeThreshold) {
            ($this->queryCallback)($this, (object) [
                'sql' => $sql,
                'bindings' => $bindings,
                'time' => $timeMs,
            ]);
        }
    }

    /**
     * Create a pseudo-SQL representation for logging.
     */
    protected function createPseudoSql(string $type, string $collection, array $bindings): string
    {
        switch ($type) {
            case 'select':
                return "SELECT * FROM {$collection}";
            case 'insert':
                return "INSERT INTO {$collection}";
            case 'insertGetId':
                return "INSERT INTO {$collection} (auto-generated ID)";
            case 'update':
                return "UPDATE {$collection}";
            case 'delete':
                return "DELETE FROM {$collection}";
            case 'transaction':
                return "TRANSACTION";
            default:
                return strtoupper($type) . " {$collection}";
        }
    }
}
