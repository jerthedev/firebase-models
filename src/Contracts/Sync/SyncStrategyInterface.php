<?php

namespace JTD\FirebaseModels\Contracts\Sync;

use Illuminate\Support\Collection;

/**
 * Interface for sync strategies that handle data synchronization
 * between Firestore and local database.
 */
interface SyncStrategyInterface
{
    /**
     * Sync data for a specific collection.
     *
     * @param string $collection The Firestore collection name
     * @param array $options Sync options (since, limit, etc.)
     */
    public function sync(string $collection, array $options = []): SyncResultInterface;

    /**
     * Sync a specific document.
     *
     * @param string $collection The Firestore collection name
     * @param string $documentId The document ID
     * @param array $options Sync options
     */
    public function syncDocument(string $collection, string $documentId, array $options = []): SyncResultInterface;

    /**
     * Get the strategy name.
     */
    public function getName(): string;

    /**
     * Check if the strategy supports bidirectional sync.
     */
    public function supportsBidirectional(): bool;

    /**
     * Set the conflict resolver for this strategy.
     */
    public function setConflictResolver(ConflictResolverInterface $resolver): void;

    /**
     * Set the schema mapper for this strategy.
     */
    public function setSchemaMapper(SchemaMapperInterface $mapper): void;
}
