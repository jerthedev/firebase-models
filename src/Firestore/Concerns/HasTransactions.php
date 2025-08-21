<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Google\Cloud\Firestore\Transaction;
use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Firestore\Transactions\TransactionManager;
use JTD\FirebaseModels\Firestore\Transactions\TransactionResult;

/**
 * Trait for handling transactions in Firestore models.
 */
trait HasTransactions
{
    /**
     * Execute a closure within a transaction.
     */
    public static function transaction(callable $callback, array $options = []): mixed
    {
        return TransactionManager::execute($callback, $options);
    }

    /**
     * Execute a closure within a transaction with retry logic.
     */
    public static function transactionWithRetry(callable $callback, int $maxAttempts = 3, array $options = []): mixed
    {
        return TransactionManager::executeWithRetry($callback, $maxAttempts, $options);
    }

    /**
     * Save this model within a transaction.
     */
    public function saveInTransaction(array $options = []): bool
    {
        return static::transaction(function (Transaction $transaction) use ($options) {
            return $this->saveWithTransaction($transaction, $options);
        });
    }

    /**
     * Save this model using an existing transaction.
     */
    public function saveWithTransaction(Transaction $transaction, array $options = []): bool
    {
        $this->mergeAttributesFromCachedCasts();

        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            $saved = $this->isDirty() ? $this->performUpdateWithTransaction($transaction) : true;
        } else {
            $saved = $this->performInsertWithTransaction($transaction);
        }

        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Delete this model within a transaction.
     */
    public function deleteInTransaction(): bool
    {
        return static::transaction(function (Transaction $transaction) {
            return $this->deleteWithTransaction($transaction);
        });
    }

    /**
     * Delete this model using an existing transaction.
     */
    public function deleteWithTransaction(Transaction $transaction): bool
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->performDeleteWithTransaction($transaction);
        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * Perform an insert operation within a transaction.
     */
    protected function performInsertWithTransaction(Transaction $transaction): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $attributes = $this->getAttributesForInsert();

        if (empty($attributes)) {
            return true;
        }

        $docRef = $this->getDocumentReference();
        
        if (empty($this->getKey())) {
            // Generate a new document ID
            $docRef = FirestoreDB::collection($this->getCollection())->newDocument();
            $this->setAttribute($this->getKeyName(), $docRef->id());
        }

        $transaction->set($docRef, $attributes);

        $this->exists = true;
        $this->wasRecentlyCreated = true;
        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Perform an update operation within a transaction.
     */
    protected function performUpdateWithTransaction(Transaction $transaction): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirtyForUpdate();

        if (count($dirty) > 0) {
            $docRef = $this->getDocumentReference();
            $transaction->update($docRef, $dirty);
            $this->syncChanges();
            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Perform a delete operation within a transaction.
     */
    protected function performDeleteWithTransaction(Transaction $transaction): void
    {
        $docRef = $this->getDocumentReference();
        $transaction->delete($docRef);
        $this->exists = false;
    }

    /**
     * Create multiple models within a single transaction.
     */
    public static function createManyInTransaction(array $records): array
    {
        $models = [];

        static::transaction(function (Transaction $transaction) use ($records, &$models) {
            foreach ($records as $record) {
                $model = new static($record);
                $model->saveWithTransaction($transaction);
                $models[] = $model;
            }
        });

        return $models;
    }

    /**
     * Update multiple models within a single transaction.
     */
    public static function updateManyInTransaction(array $updates): bool
    {
        return static::transaction(function (Transaction $transaction) use ($updates) {
            foreach ($updates as $id => $data) {
                $model = static::find($id);
                if ($model) {
                    $model->fill($data);
                    $model->saveWithTransaction($transaction);
                }
            }
            return true;
        });
    }

    /**
     * Delete multiple models within a single transaction.
     */
    public static function deleteManyInTransaction(array $ids): bool
    {
        return static::transaction(function (Transaction $transaction) use ($ids) {
            foreach ($ids as $id) {
                $model = static::find($id);
                if ($model) {
                    $model->deleteWithTransaction($transaction);
                }
            }
            return true;
        });
    }

    /**
     * Perform a conditional update within a transaction.
     */
    public function conditionalUpdate(array $conditions, array $updates): bool
    {
        return static::transaction(function (Transaction $transaction) use ($conditions, $updates) {
            // Read the current document
            $docRef = $this->getDocumentReference();
            $snapshot = $transaction->snapshot($docRef);

            if (!$snapshot->exists()) {
                return false;
            }

            $data = $snapshot->data();

            // Check conditions
            foreach ($conditions as $field => $expectedValue) {
                if (($data[$field] ?? null) !== $expectedValue) {
                    return false;
                }
            }

            // Apply updates
            $this->fill($updates);
            return $this->saveWithTransaction($transaction);
        });
    }

    /**
     * Increment a field atomically within a transaction.
     */
    public function incrementInTransaction(string $field, int $amount = 1): bool
    {
        return static::transaction(function (Transaction $transaction) use ($field, $amount) {
            $docRef = $this->getDocumentReference();
            $snapshot = $transaction->snapshot($docRef);

            if (!$snapshot->exists()) {
                return false;
            }

            $currentValue = $snapshot->data()[$field] ?? 0;
            $newValue = $currentValue + $amount;

            $transaction->update($docRef, [$field => $newValue]);
            $this->setAttribute($field, $newValue);

            return true;
        });
    }

    /**
     * Get the document reference for this model.
     */
    protected function getDocumentReference(): \Google\Cloud\Firestore\DocumentReference
    {
        return FirestoreDB::collection($this->getCollection())->document($this->getKey());
    }
}
