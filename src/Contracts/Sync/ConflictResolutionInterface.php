<?php

namespace JTD\FirebaseModels\Contracts\Sync;

/**
 * Interface for conflict resolution results.
 */
interface ConflictResolutionInterface
{
    /**
     * Get the resolved data.
     *
     * @return array
     */
    public function getResolvedData(): array;

    /**
     * Get the resolution action taken.
     *
     * @return string
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
     *
     * @return bool
     */
    public function requiresManualIntervention(): bool;

    /**
     * Get any additional metadata about the resolution.
     *
     * @return array
     */
    public function getMetadata(): array;

    /**
     * Get a human-readable description of the resolution.
     *
     * @return string
     */
    public function getDescription(): string;
}
