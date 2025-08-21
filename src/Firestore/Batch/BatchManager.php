<?php

namespace JTD\FirebaseModels\Firestore\Batch;

use Google\Cloud\Firestore\WriteBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Firestore\Batch\BatchResult;
use JTD\FirebaseModels\Firestore\Batch\Exceptions\BatchException;

/**
 * Manager for Firestore batch operations with enhanced functionality.
 */
class BatchManager
{
    /**
     * Default batch options.
     */
    protected static array $defaultOptions = [
        'max_operations' => 500, // Firestore limit
        'chunk_size' => 100,
        'validate_operations' => true,
        'log_operations' => true,
        'auto_commit' => true,
    ];

    /**
     * Create a new batch operation.
     */
    public static function create(array $options = []): BatchOperation
    {
        $options = array_merge(static::$defaultOptions, $options);
        return new BatchOperation($options);
    }

    /**
     * Bulk insert documents.
     */
    public static function bulkInsert(string $collection, array $documents, array $options = []): BatchResult
    {
        $options = array_merge(static::$defaultOptions, $options);
        $startTime = microtime(true);

        try {
            $results = [];
            $chunks = array_chunk($documents, $options['chunk_size']);

            foreach ($chunks as $chunkIndex => $chunk) {
                $batch = FirestoreDB::batch();
                $chunkResults = [];

                foreach ($chunk as $document) {
                    $docRef = FirestoreDB::collection($collection)->newDocument();
                    $batch->set($docRef, $document);
                    $chunkResults[] = $docRef->id();
                }

                $batch->commit();
                $results = array_merge($results, $chunkResults);

                if ($options['log_operations']) {
                    Log::info("Bulk insert chunk {$chunkIndex} completed", [
                        'collection' => $collection,
                        'chunk_size' => count($chunk),
                        'total_chunks' => count($chunks)
                    ]);
                }
            }

            $result = BatchResult::success([
                'operation' => 'bulk_insert',
                'collection' => $collection,
                'document_ids' => $results,
                'total_documents' => count($documents),
                'chunks_processed' => count($chunks)
            ]);

            $result->setDuration(microtime(true) - $startTime);
            return $result;

        } catch (\Exception $e) {
            if ($options['log_operations']) {
                Log::error('Bulk insert failed', [
                    'collection' => $collection,
                    'error' => $e->getMessage(),
                    'documents_count' => count($documents)
                ]);
            }

            throw new BatchException(
                "Bulk insert failed for collection {$collection}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Bulk update documents.
     */
    public static function bulkUpdate(string $collection, array $updates, array $options = []): BatchResult
    {
        $options = array_merge(static::$defaultOptions, $options);
        $startTime = microtime(true);

        try {
            $chunks = array_chunk($updates, $options['chunk_size'], true);
            $processedCount = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                $batch = FirestoreDB::batch();

                foreach ($chunk as $documentId => $data) {
                    $docRef = FirestoreDB::collection($collection)->document($documentId);
                    $batch->update($docRef, $data);
                    $processedCount++;
                }

                $batch->commit();

                if ($options['log_operations']) {
                    Log::info("Bulk update chunk {$chunkIndex} completed", [
                        'collection' => $collection,
                        'chunk_size' => count($chunk),
                        'total_chunks' => count($chunks)
                    ]);
                }
            }

            $result = BatchResult::success([
                'operation' => 'bulk_update',
                'collection' => $collection,
                'updated_documents' => $processedCount,
                'chunks_processed' => count($chunks)
            ]);

            $result->setDuration(microtime(true) - $startTime);
            return $result;

        } catch (\Exception $e) {
            if ($options['log_operations']) {
                Log::error('Bulk update failed', [
                    'collection' => $collection,
                    'error' => $e->getMessage(),
                    'updates_count' => count($updates)
                ]);
            }

            throw new BatchException(
                "Bulk update failed for collection {$collection}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Bulk delete documents.
     */
    public static function bulkDelete(string $collection, array $documentIds, array $options = []): BatchResult
    {
        $options = array_merge(static::$defaultOptions, $options);
        $startTime = microtime(true);

        try {
            $chunks = array_chunk($documentIds, $options['chunk_size']);
            $processedCount = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                $batch = FirestoreDB::batch();

                foreach ($chunk as $documentId) {
                    $docRef = FirestoreDB::collection($collection)->document($documentId);
                    $batch->delete($docRef);
                    $processedCount++;
                }

                $batch->commit();

                if ($options['log_operations']) {
                    Log::info("Bulk delete chunk {$chunkIndex} completed", [
                        'collection' => $collection,
                        'chunk_size' => count($chunk),
                        'total_chunks' => count($chunks)
                    ]);
                }
            }

            $result = BatchResult::success([
                'operation' => 'bulk_delete',
                'collection' => $collection,
                'deleted_documents' => $processedCount,
                'chunks_processed' => count($chunks)
            ]);

            $result->setDuration(microtime(true) - $startTime);
            return $result;

        } catch (\Exception $e) {
            if ($options['log_operations']) {
                Log::error('Bulk delete failed', [
                    'collection' => $collection,
                    'error' => $e->getMessage(),
                    'document_ids_count' => count($documentIds)
                ]);
            }

            throw new BatchException(
                "Bulk delete failed for collection {$collection}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Bulk upsert documents (set with merge).
     */
    public static function bulkUpsert(string $collection, array $documents, array $options = []): BatchResult
    {
        $options = array_merge(static::$defaultOptions, $options);
        $startTime = microtime(true);

        try {
            $chunks = array_chunk($documents, $options['chunk_size'], true);
            $processedCount = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                $batch = FirestoreDB::batch();

                foreach ($chunk as $documentId => $data) {
                    $docRef = FirestoreDB::collection($collection)->document($documentId);
                    $batch->set($docRef, $data, ['merge' => true]);
                    $processedCount++;
                }

                $batch->commit();

                if ($options['log_operations']) {
                    Log::info("Bulk upsert chunk {$chunkIndex} completed", [
                        'collection' => $collection,
                        'chunk_size' => count($chunk),
                        'total_chunks' => count($chunks)
                    ]);
                }
            }

            $result = BatchResult::success([
                'operation' => 'bulk_upsert',
                'collection' => $collection,
                'upserted_documents' => $processedCount,
                'chunks_processed' => count($chunks)
            ]);

            $result->setDuration(microtime(true) - $startTime);
            return $result;

        } catch (\Exception $e) {
            if ($options['log_operations']) {
                Log::error('Bulk upsert failed', [
                    'collection' => $collection,
                    'error' => $e->getMessage(),
                    'documents_count' => count($documents)
                ]);
            }

            throw new BatchException(
                "Bulk upsert failed for collection {$collection}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Execute multiple batch operations in sequence.
     */
    public static function executeSequence(array $operations, array $options = []): Collection
    {
        $results = new Collection();
        $options = array_merge(static::$defaultOptions, $options);

        foreach ($operations as $key => $operation) {
            try {
                $result = $operation();
                $results->put($key, $result);
            } catch (BatchException $e) {
                if ($options['stop_on_failure'] ?? true) {
                    throw $e;
                }
                $results->put($key, BatchResult::failure($e->getMessage(), $e));
            }
        }

        return $results;
    }

    /**
     * Set default options for all batch operations.
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
}
