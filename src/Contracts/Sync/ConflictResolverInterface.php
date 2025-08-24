<?php

namespace JTD\FirebaseModels\Contracts\Sync;

/**
 * Interface for conflict resolution strategies when syncing data
 * between Firestore and local database.
 */
interface ConflictResolverInterface
{
    /**
     * Resolve a conflict between Firestore and local data.
     *
     * @param array $firestoreData The data from Firestore
     * @param array $localData The data from local database
     * @param array $metadata Additional metadata (timestamps, versions, etc.)
     */
    public function resolve(array $firestoreData, array $localData, array $metadata = []): ConflictResolutionInterface;

    /**
     * Detect if there's a conflict between two data sets.
     *
     * @param array $firestoreData The data from Firestore
     * @param array $localData The data from local database
     * @param array $metadata Additional metadata
     */
    public function hasConflict(array $firestoreData, array $localData, array $metadata = []): bool;

    /**
     * Get the resolver name.
     */
    public function getName(): string;

    /**
     * Get the resolver priority (higher number = higher priority).
     */
    public function getPriority(): int;
}
