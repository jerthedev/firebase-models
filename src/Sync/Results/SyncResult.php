<?php

namespace JTD\FirebaseModels\Sync\Results;

use JTD\FirebaseModels\Contracts\Sync\SyncResultInterface;

/**
 * Implementation of sync operation results.
 */
class SyncResult implements SyncResultInterface
{
    protected int $processedCount = 0;
    protected int $syncedCount = 0;
    protected int $conflictCount = 0;
    protected int $errorCount = 0;
    protected array $errors = [];
    protected array $conflicts = [];
    protected bool $successful = true;

    /**
     * Check if the sync operation was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->successful && $this->errorCount === 0;
    }

    /**
     * Get the number of documents processed.
     */
    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    /**
     * Get the number of documents successfully synced.
     */
    public function getSyncedCount(): int
    {
        return $this->syncedCount;
    }

    /**
     * Get the number of conflicts encountered.
     */
    public function getConflictCount(): int
    {
        return $this->conflictCount;
    }

    /**
     * Get the number of errors encountered.
     */
    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * Get any errors that occurred during sync.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get conflicts that were encountered.
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * Get the sync operation summary.
     */
    public function getSummary(): array
    {
        return [
            'successful' => $this->isSuccessful(),
            'processed' => $this->processedCount,
            'synced' => $this->syncedCount,
            'conflicts' => $this->conflictCount,
            'errors' => $this->errorCount,
            'success_rate' => $this->processedCount > 0 ? 
                round(($this->syncedCount / $this->processedCount) * 100, 2) : 0,
        ];
    }

    /**
     * Add an error to the result.
     */
    public function addError(string $message, array $context = []): void
    {
        $this->errors[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ];
        $this->errorCount++;
        $this->successful = false;
    }

    /**
     * Add a conflict to the result.
     */
    public function addConflict(string $documentId, array $conflict): void
    {
        $this->conflicts[$documentId] = array_merge($conflict, [
            'timestamp' => now()->toISOString(),
        ]);
        $this->conflictCount++;
    }

    /**
     * Increment the processed count.
     */
    public function incrementProcessed(): void
    {
        $this->processedCount++;
    }

    /**
     * Increment the synced count.
     */
    public function incrementSynced(): void
    {
        $this->syncedCount++;
    }

    /**
     * Mark the operation as failed.
     */
    public function markAsFailed(): void
    {
        $this->successful = false;
    }

    /**
     * Reset all counters.
     */
    public function reset(): void
    {
        $this->processedCount = 0;
        $this->syncedCount = 0;
        $this->conflictCount = 0;
        $this->errorCount = 0;
        $this->errors = [];
        $this->conflicts = [];
        $this->successful = true;
    }
}
