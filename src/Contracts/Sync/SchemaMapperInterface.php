<?php

namespace JTD\FirebaseModels\Contracts\Sync;

/**
 * Interface for mapping Firestore collections to local database tables.
 */
interface SchemaMapperInterface
{
    /**
     * Get the local table name for a Firestore collection.
     *
     * @param string $collection The Firestore collection name
     */
    public function getTableName(string $collection): string;

    /**
     * Map Firestore document data to local database columns.
     *
     * @param string $collection The Firestore collection name
     * @param array $firestoreData The document data from Firestore
     */
    public function mapToLocal(string $collection, array $firestoreData): array;

    /**
     * Map local database data to Firestore document format.
     *
     * @param string $collection The Firestore collection name
     * @param array $localData The data from local database
     */
    public function mapToFirestore(string $collection, array $localData): array;

    /**
     * Get the column mapping for a collection.
     *
     * @param string $collection The Firestore collection name
     */
    public function getColumnMapping(string $collection): array;

    /**
     * Check if a collection has a local table mapping.
     *
     * @param string $collection The Firestore collection name
     */
    public function hasMapping(string $collection): bool;

    /**
     * Register a new collection mapping.
     *
     * @param string $collection The Firestore collection name
     * @param string $table The local table name
     * @param array $columnMapping Column mapping configuration
     */
    public function registerMapping(string $collection, string $table, array $columnMapping = []): void;
}
