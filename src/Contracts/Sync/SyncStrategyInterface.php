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
     * @return SyncResultInterface
     */
    public function sync(string $collection, array $options = []): SyncResultInterface;

    /**
     * Sync a specific document.
     *
     * @param string $collection The Firestore collection name
     * @param string $documentId The document ID
     * @param array $options Sync options
     * @return SyncResultInterface
     */
    public function syncDocument(string $collection, string $documentId, array $options = []): SyncResultInterface;

    /**
     * Get the strategy name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if the strategy supports bidirectional sync.
     *
     * @return bool
     */
    public function supportsBidirectional(): bool;

    /**
     * Set the conflict resolver for this strategy.
     *
     * @param ConflictResolverInterface $resolver
     * @return void
     */
    public function setConflictResolver(ConflictResolverInterface $resolver): void;

    /**
     * Set the schema mapper for this strategy.
     *
     * @param SchemaMapperInterface $mapper
     * @return void
     */
    public function setSchemaMapper(SchemaMapperInterface $mapper): void;
}
