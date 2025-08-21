<?php

namespace JTD\FirebaseModels\Contracts\Sync;

/**
 * Interface for sync operation results.
 */
interface SyncResultInterface
{
    /**
     * Check if the sync operation was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool;

    /**
     * Get the number of documents processed.
     *
     * @return int
     */
    public function getProcessedCount(): int;

    /**
     * Get the number of documents successfully synced.
     *
     * @return int
     */
    public function getSyncedCount(): int;

    /**
     * Get the number of conflicts encountered.
     *
     * @return int
     */
    public function getConflictCount(): int;

    /**
     * Get the number of errors encountered.
     *
     * @return int
     */
    public function getErrorCount(): int;

    /**
     * Get any errors that occurred during sync.
     *
     * @return array
     */
    public function getErrors(): array;

    /**
     * Get conflicts that were encountered.
     *
     * @return array
     */
    public function getConflicts(): array;

    /**
     * Get the sync operation summary.
     *
     * @return array
     */
    public function getSummary(): array;

    /**
     * Add an error to the result.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function addError(string $message, array $context = []): void;

    /**
     * Add a conflict to the result.
     *
     * @param string $documentId
     * @param array $conflict
     * @return void
     */
    public function addConflict(string $documentId, array $conflict): void;

    /**
     * Increment the processed count.
     *
     * @return void
     */
    public function incrementProcessed(): void;

    /**
     * Increment the synced count.
     *
     * @return void
     */
    public function incrementSynced(): void;
}
