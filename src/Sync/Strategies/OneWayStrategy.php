<?php

namespace JTD\FirebaseModels\Sync\Strategies;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JTD\FirebaseModels\Contracts\Sync\ConflictResolverInterface;
use JTD\FirebaseModels\Contracts\Sync\SchemaMapperInterface;
use JTD\FirebaseModels\Contracts\Sync\SyncResultInterface;
use JTD\FirebaseModels\Contracts\Sync\SyncStrategyInterface;
use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Sync\Results\SyncResult;

/**
 * One-way sync strategy that syncs data from Firestore to local database.
 * This strategy treats Firestore as the source of truth.
 */
class OneWayStrategy implements SyncStrategyInterface
{
    protected ConflictResolverInterface $conflictResolver;

    protected SchemaMapperInterface $schemaMapper;

    /**
     * Sync data for a specific collection.
     */
    public function sync(string $collection, array $options = []): SyncResultInterface
    {
        $result = new SyncResult();

        try {
            // Get sync options
            $batchSize = $options['batch_size'] ?? 100;
            $since = $options['since'] ?? null;
            $dryRun = $options['dry_run'] ?? false;

            Log::info("Starting one-way sync for collection: {$collection}", [
                'batch_size' => $batchSize,
                'since' => $since,
                'dry_run' => $dryRun,
            ]);

            // Check if we have a local table mapping
            if (!$this->schemaMapper->hasMapping($collection)) {
                $result->addError("No local table mapping found for collection: {$collection}");

                return $result;
            }

            // Get documents from Firestore
            $query = FirestoreDB::collection($collection);

            // Apply since filter if provided
            if ($since) {
                $query = $query->where('updated_at', '>=', $since);
            }

            $documents = $query->documents();

            foreach ($documents as $document) {
                $result->incrementProcessed();

                try {
                    $this->syncDocument($collection, $document->id(), array_merge($options, [
                        '_document_data' => $document->data(),
                        '_result' => $result,
                    ]));
                } catch (\Exception $e) {
                    $result->addError("Failed to sync document {$document->id()}: ".$e->getMessage(), [
                        'document_id' => $document->id(),
                        'collection' => $collection,
                    ]);
                }
            }

            return $result;
        } catch (\Exception $e) {
            $result->addError("Sync failed for collection {$collection}: ".$e->getMessage());

            return $result;
        }
    }

    /**
     * Sync a specific document.
     */
    public function syncDocument(string $collection, string $documentId, array $options = []): SyncResultInterface
    {
        $result = $options['_result'] ?? new SyncResult();
        $dryRun = $options['dry_run'] ?? false;

        try {
            // Get document data (either from options or fetch from Firestore)
            $firestoreData = $options['_document_data'] ?? null;

            if (!$firestoreData) {
                $document = FirestoreDB::document("{$collection}/{$documentId}")->snapshot();
                if (!$document->exists()) {
                    $result->addError("Document not found in Firestore: {$documentId}");

                    return $result;
                }
                $firestoreData = $document->data();
            }

            // Map Firestore data to local format
            $localData = $this->schemaMapper->mapToLocal($collection, $firestoreData);
            $tableName = $this->schemaMapper->getTableName($collection);

            // Check if document exists locally
            $existingRecord = DB::table($tableName)->where('id', $documentId)->first();

            if ($existingRecord) {
                // Check for conflicts
                $existingData = (array) $existingRecord;

                if ($this->conflictResolver->hasConflict($firestoreData, $existingData)) {
                    $resolution = $this->conflictResolver->resolve($firestoreData, $existingData, [
                        'collection' => $collection,
                        'document_id' => $documentId,
                    ]);

                    $result->addConflict($documentId, [
                        'action' => $resolution->getAction(),
                        'winning_source' => $resolution->getWinningSource(),
                        'description' => $resolution->getDescription(),
                    ]);

                    if ($resolution->requiresManualIntervention()) {
                        Log::warning("Manual intervention required for document: {$documentId}");

                        return $result;
                    }

                    $localData = $resolution->getResolvedData();
                }

                // Update existing record
                if (!$dryRun) {
                    DB::table($tableName)->where('id', $documentId)->update($localData);
                }

                Log::debug("Updated local record for document: {$documentId}");
            } else {
                // Insert new record
                if (!$dryRun) {
                    $localData['id'] = $documentId;
                    DB::table($tableName)->insert($localData);
                }

                Log::debug("Inserted new local record for document: {$documentId}");
            }

            $result->incrementSynced();

            return $result;
        } catch (\Exception $e) {
            $result->addError("Failed to sync document {$documentId}: ".$e->getMessage(), [
                'document_id' => $documentId,
                'collection' => $collection,
            ]);

            return $result;
        }
    }

    /**
     * Get the strategy name.
     */
    public function getName(): string
    {
        return 'one_way';
    }

    /**
     * Check if the strategy supports bidirectional sync.
     */
    public function supportsBidirectional(): bool
    {
        return false;
    }

    /**
     * Set the conflict resolver for this strategy.
     */
    public function setConflictResolver(ConflictResolverInterface $resolver): void
    {
        $this->conflictResolver = $resolver;
    }

    /**
     * Set the schema mapper for this strategy.
     */
    public function setSchemaMapper(SchemaMapperInterface $mapper): void
    {
        $this->schemaMapper = $mapper;
    }
}
