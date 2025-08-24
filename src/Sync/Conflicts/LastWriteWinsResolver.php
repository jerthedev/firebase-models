<?php

namespace JTD\FirebaseModels\Sync\Conflicts;

use Illuminate\Support\Carbon;
use JTD\FirebaseModels\Contracts\Sync\ConflictResolutionInterface;
use JTD\FirebaseModels\Contracts\Sync\ConflictResolverInterface;

/**
 * Last-write-wins conflict resolver that uses timestamps to determine
 * which version of the data should be kept.
 */
class LastWriteWinsResolver implements ConflictResolverInterface
{
    /**
     * The timestamp field to use for comparison.
     */
    protected string $timestampField = 'updated_at';

    /**
     * Create a new last-write-wins resolver.
     */
    public function __construct(string $timestampField = 'updated_at')
    {
        $this->timestampField = $timestampField;
    }

    /**
     * Resolve a conflict between Firestore and local data.
     */
    public function resolve(array $firestoreData, array $localData, array $metadata = []): ConflictResolutionInterface
    {
        $firestoreTimestamp = $this->extractTimestamp($firestoreData);
        $localTimestamp = $this->extractTimestamp($localData);

        // If we can't determine timestamps, default to Firestore
        if (!$firestoreTimestamp && !$localTimestamp) {
            return new ConflictResolution(
                $firestoreData,
                'default_to_firestore',
                'firestore',
                'Unable to determine timestamps, defaulting to Firestore data'
            );
        }

        // If only one has a timestamp, use that one
        if ($firestoreTimestamp && !$localTimestamp) {
            return new ConflictResolution(
                $firestoreData,
                'firestore_has_timestamp',
                'firestore',
                'Local data missing timestamp, using Firestore data'
            );
        }

        if (!$firestoreTimestamp && $localTimestamp) {
            return new ConflictResolution(
                $localData,
                'local_has_timestamp',
                'local',
                'Firestore data missing timestamp, using local data'
            );
        }

        // Compare timestamps
        if ($firestoreTimestamp->greaterThan($localTimestamp)) {
            return new ConflictResolution(
                $firestoreData,
                'firestore_newer',
                'firestore',
                "Firestore data is newer ({$firestoreTimestamp->toISOString()} > {$localTimestamp->toISOString()})"
            );
        } elseif ($localTimestamp->greaterThan($firestoreTimestamp)) {
            return new ConflictResolution(
                $localData,
                'local_newer',
                'local',
                "Local data is newer ({$localTimestamp->toISOString()} > {$firestoreTimestamp->toISOString()})"
            );
        } else {
            // Timestamps are equal, default to Firestore as source of truth
            return new ConflictResolution(
                $firestoreData,
                'timestamps_equal',
                'firestore',
                'Timestamps are equal, defaulting to Firestore as source of truth'
            );
        }
    }

    /**
     * Detect if there's a conflict between two data sets.
     */
    public function hasConflict(array $firestoreData, array $localData, array $metadata = []): bool
    {
        // Remove timestamps and IDs for comparison
        $firestoreComparison = $this->prepareForComparison($firestoreData);
        $localComparison = $this->prepareForComparison($localData);

        // If the data is identical (excluding timestamps), no conflict
        return $firestoreComparison !== $localComparison;
    }

    /**
     * Get the resolver name.
     */
    public function getName(): string
    {
        return 'last_write_wins';
    }

    /**
     * Get the resolver priority.
     */
    public function getPriority(): int
    {
        return 100; // Medium priority
    }

    /**
     * Extract timestamp from data array.
     */
    protected function extractTimestamp(array $data): ?Carbon
    {
        $timestamp = $data[$this->timestampField] ?? null;

        if (!$timestamp) {
            return null;
        }

        try {
            // Handle different timestamp formats
            if ($timestamp instanceof Carbon) {
                return $timestamp;
            }

            if ($timestamp instanceof \DateTime) {
                return Carbon::instance($timestamp);
            }

            if ($timestamp instanceof \Google\Cloud\Core\Timestamp) {
                return Carbon::createFromTimestamp($timestamp->get()->getSeconds());
            }

            if (is_string($timestamp)) {
                return Carbon::parse($timestamp);
            }

            if (is_numeric($timestamp)) {
                return Carbon::createFromTimestamp($timestamp);
            }

            return null;
        } catch (\Exception $e) {
            \Log::warning("Failed to parse timestamp: {$timestamp}", [
                'error' => $e->getMessage(),
                'resolver' => $this->getName(),
            ]);

            return null;
        }
    }

    /**
     * Prepare data for comparison by removing timestamps and metadata.
     */
    protected function prepareForComparison(array $data): array
    {
        $comparison = $data;

        // Remove fields that shouldn't be compared
        $excludeFields = [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            '_firestore_id',
            '_sync_version',
            '_last_synced_at',
        ];

        foreach ($excludeFields as $field) {
            unset($comparison[$field]);
        }

        // Sort array to ensure consistent comparison
        ksort($comparison);

        return $comparison;
    }

    /**
     * Set the timestamp field to use for comparison.
     */
    public function setTimestampField(string $field): void
    {
        $this->timestampField = $field;
    }

    /**
     * Get the timestamp field being used.
     */
    public function getTimestampField(): string
    {
        return $this->timestampField;
    }
}
