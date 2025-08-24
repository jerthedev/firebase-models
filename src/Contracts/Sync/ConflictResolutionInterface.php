<?php

namespace JTD\FirebaseModels\Contracts\Sync;

/**
 * Interface for conflict resolution results.
 */
interface ConflictResolutionInterface
{
    /**
     * Get the resolved data.
     */
    public function getResolvedData(): array;

    /**
     * Get the resolution action taken.
     */
    public function getAction(): string;

    /**
     * Get the source of the winning data.
     *
     * @return string (firestore|local|merged)
     */
    public function getWinningSource(): string;

    /**
     * Check if the resolution requires manual intervention.
     */
    public function requiresManualIntervention(): bool;

    /**
     * Get any additional metadata about the resolution.
     */
    public function getMetadata(): array;

    /**
     * Get a human-readable description of the resolution.
     */
    public function getDescription(): string;
}
