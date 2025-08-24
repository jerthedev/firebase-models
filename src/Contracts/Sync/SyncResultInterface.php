<?php

namespace JTD\FirebaseModels\Contracts\Sync;

/**
 * Interface for sync operation results.
 */
interface SyncResultInterface
{
    /**
     * Check if the sync operation was successful.
     */
    public function isSuccessful(): bool;

    /**
     * Get the number of documents processed.
     */
    public function getProcessedCount(): int;

    /**
     * Get the number of documents successfully synced.
     */
    public function getSyncedCount(): int;

    /**
     * Get the number of conflicts encountered.
     */
    public function getConflictCount(): int;

    /**
     * Get the number of errors encountered.
     */
    public function getErrorCount(): int;

    /**
     * Get any errors that occurred during sync.
     */
    public function getErrors(): array;

    /**
     * Get conflicts that were encountered.
     */
    public function getConflicts(): array;

    /**
     * Get the sync operation summary.
     */
    public function getSummary(): array;

    /**
     * Add an error to the result.
     */
    public function addError(string $message, array $context = []): void;

    /**
     * Add a conflict to the result.
     */
    public function addConflict(string $documentId, array $conflict): void;

    /**
     * Increment the processed count.
     */
    public function incrementProcessed(): void;

    /**
     * Increment the synced count.
     */
    public function incrementSynced(): void;
}
