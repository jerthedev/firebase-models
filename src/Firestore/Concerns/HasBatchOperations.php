<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use JTD\FirebaseModels\Firestore\Batch\BatchManager;
use JTD\FirebaseModels\Firestore\Batch\BatchOperation;
use JTD\FirebaseModels\Firestore\Batch\BatchResult;

/**
 * Trait for handling batch operations in Firestore models.
 */
trait HasBatchOperations
{
    /**
     * Create multiple models in a batch operation.
     */
    public static function createManyInBatch(array $records, array $options = []): BatchResult
    {
        $collection = (new static)->getCollection();
        return BatchManager::bulkInsert($collection, $records, $options);
    }

    /**
     * Update multiple models in a batch operation.
     */
    public static function updateManyInBatch(array $updates, array $options = []): BatchResult
    {
        $collection = (new static)->getCollection();
        return BatchManager::bulkUpdate($collection, $updates, $options);
    }

    /**
     * Delete multiple models in a batch operation.
     */
    public static function deleteManyInBatch(array $ids, array $options = []): BatchResult
    {
        $collection = (new static)->getCollection();
        return BatchManager::bulkDelete($collection, $ids, $options);
    }

    /**
     * Upsert multiple models in a batch operation.
     */
    public static function upsertManyInBatch(array $documents, array $options = []): BatchResult
    {
        $collection = (new static)->getCollection();
        return BatchManager::bulkUpsert($collection, $documents, $options);
    }

    /**
     * Create a batch operation for this model's collection.
     */
    public static function batch(array $options = []): BatchOperation
    {
        return BatchManager::create($options);
    }

    /**
     * Save this model as part of a batch operation.
     */
    public function addToBatch(BatchOperation $batch): BatchOperation
    {
        $collection = $this->getCollection();
        
        if ($this->exists) {
            // Update existing model
            $dirty = $this->getDirtyForUpdate();
            if (!empty($dirty)) {
                $batch->update($collection, $this->getKey(), $dirty);
            }
        } else {
            // Create new model
            if ($this->usesTimestamps()) {
                $this->updateTimestamps();
            }
            
            $attributes = $this->getAttributesForInsert();
            
            if ($this->getKey()) {
                $batch->set($collection, $this->getKey(), $attributes);
            } else {
                $batch->create($collection, $attributes);
            }
        }

        return $batch;
    }

    /**
     * Delete this model as part of a batch operation.
     */
    public function addDeleteToBatch(BatchOperation $batch): BatchOperation
    {
        if ($this->exists) {
            $batch->delete($this->getCollection(), $this->getKey());
        }
        return $batch;
    }

    /**
     * Batch save multiple model instances.
     */
    public static function saveManyInBatch(array $models, array $options = []): BatchResult
    {
        $batch = static::batch($options);
        
        foreach ($models as $model) {
            if ($model instanceof static) {
                $model->addToBatch($batch);
            }
        }

        return $batch->execute();
    }

    /**
     * Batch delete multiple model instances.
     */
    public static function deleteManyInstancesInBatch(array $models, array $options = []): BatchResult
    {
        $batch = static::batch($options);
        
        foreach ($models as $model) {
            if ($model instanceof static) {
                $model->addDeleteToBatch($batch);
            }
        }

        return $batch->execute();
    }

    /**
     * Perform a bulk operation with validation.
     */
    public static function bulkOperation(callable $callback, array $options = []): BatchResult
    {
        $batch = static::batch($options);
        $callback($batch);
        return $batch->execute();
    }

    /**
     * Create models from array data with batch processing.
     */
    public static function createFromArray(array $data, array $options = []): BatchResult
    {
        $models = [];
        
        foreach ($data as $record) {
            $model = new static($record);
            $models[] = $model;
        }

        return static::saveManyInBatch($models, $options);
    }

    /**
     * Sync models with batch operations (create, update, delete as needed).
     */
    public static function syncWithBatch(array $data, string $keyField = 'id', array $options = []): BatchResult
    {
        $batch = static::batch($options);
        $collection = (new static)->getCollection();
        
        // Get existing models
        $existingKeys = array_column($data, $keyField);
        $existingModels = static::whereIn($keyField, $existingKeys)->get()->keyBy($keyField);
        
        foreach ($data as $record) {
            $key = $record[$keyField] ?? null;
            
            if ($key && $existingModels->has($key)) {
                // Update existing
                $model = $existingModels->get($key);
                $model->fill($record);
                $model->addToBatch($batch);
            } else {
                // Create new
                $batch->create($collection, $record, $key);
            }
        }

        return $batch->execute();
    }

    /**
     * Get batch operation statistics for this model.
     */
    public static function getBatchStats(): array
    {
        return [
            'collection' => (new static)->getCollection(),
            'batch_limits' => BatchManager::getDefaultOptions(),
            'model_class' => static::class,
        ];
    }

    /**
     * Validate data before batch operation.
     */
    public static function validateForBatch(array $data): array
    {
        $errors = [];
        
        foreach ($data as $index => $record) {
            try {
                $model = new static($record);
                // You could add custom validation here
                // $model->validate(); // if you have validation
            } catch (\Exception $e) {
                $errors[] = "Record {$index}: " . $e->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Chunk large datasets for batch processing.
     */
    public static function processInChunks(array $data, callable $processor, int $chunkSize = 100): array
    {
        $results = [];
        $chunks = array_chunk($data, $chunkSize, true);
        
        foreach ($chunks as $index => $chunk) {
            $results[$index] = $processor($chunk, $index);
        }

        return $results;
    }
}
