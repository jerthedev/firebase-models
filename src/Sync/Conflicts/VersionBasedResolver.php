<?php

namespace JTD\FirebaseModels\Sync\Conflicts;

use JTD\FirebaseModels\Contracts\Sync\ConflictResolutionInterface;
use JTD\FirebaseModels\Contracts\Sync\ConflictResolverInterface;

/**
 * Version-based conflict resolver that uses version numbers to determine
 * which version of the data should be kept.
 */
class VersionBasedResolver implements ConflictResolverInterface
{
    /**
     * The version field to use for comparison.
     */
    protected string $versionField = '_version';

    /**
     * Whether to auto-increment version on resolution.
     */
    protected bool $autoIncrement = true;

    /**
     * Create a new version-based resolver.
     */
    public function __construct(string $versionField = '_version', bool $autoIncrement = true)
    {
        $this->versionField = $versionField;
        $this->autoIncrement = $autoIncrement;
    }

    /**
     * Resolve a conflict between Firestore and local data.
     */
    public function resolve(array $firestoreData, array $localData, array $metadata = []): ConflictResolutionInterface
    {
        $firestoreVersion = $this->extractVersion($firestoreData);
        $localVersion = $this->extractVersion($localData);

        // If neither has a version, initialize both and use Firestore
        if ($firestoreVersion === null && $localVersion === null) {
            $resolvedData = $firestoreData;
            $resolvedData[$this->versionField] = 1;

            return new ConflictResolution(
                $resolvedData,
                'initialize_version',
                'firestore',
                'No version found in either source, initialized version to 1 and using Firestore data'
            );
        }

        // If only one has a version, use that one and increment if needed
        if ($firestoreVersion !== null && $localVersion === null) {
            $resolvedData = $firestoreData;
            if ($this->autoIncrement) {
                $resolvedData[$this->versionField] = $firestoreVersion + 1;
            }

            return new ConflictResolution(
                $resolvedData,
                'firestore_has_version',
                'firestore',
                "Local data missing version, using Firestore data (version {$firestoreVersion})"
            );
        }

        if ($firestoreVersion === null && $localVersion !== null) {
            $resolvedData = $localData;
            if ($this->autoIncrement) {
                $resolvedData[$this->versionField] = $localVersion + 1;
            }

            return new ConflictResolution(
                $resolvedData,
                'local_has_version',
                'local',
                "Firestore data missing version, using local data (version {$localVersion})"
            );
        }

        // Both have versions - compare them
        if ($firestoreVersion > $localVersion) {
            $resolvedData = $firestoreData;
            if ($this->autoIncrement) {
                $resolvedData[$this->versionField] = $firestoreVersion + 1;
            }

            return new ConflictResolution(
                $resolvedData,
                'firestore_newer_version',
                'firestore',
                "Firestore has newer version ({$firestoreVersion} > {$localVersion})"
            );
        } elseif ($localVersion > $firestoreVersion) {
            $resolvedData = $localData;
            if ($this->autoIncrement) {
                $resolvedData[$this->versionField] = $localVersion + 1;
            }

            return new ConflictResolution(
                $resolvedData,
                'local_newer_version',
                'local',
                "Local has newer version ({$localVersion} > {$firestoreVersion})"
            );
        } else {
            // Versions are equal - this indicates a true conflict
            // We need to check if the data is actually different
            if (!$this->hasDataConflict($firestoreData, $localData)) {
                // Same version, same data - no real conflict
                return new ConflictResolution(
                    $firestoreData,
                    'same_version_same_data',
                    'firestore',
                    "Same version ({$firestoreVersion}) and identical data, no conflict"
                );
            }

            // Same version but different data - requires manual intervention
            $resolvedData = $firestoreData;
            $resolvedData[$this->versionField] = $firestoreVersion + 1;
            $resolvedData['_conflict_detected'] = true;
            $resolvedData['_conflict_timestamp'] = now()->toISOString();

            return new ConflictResolution(
                $resolvedData,
                'version_conflict',
                'firestore',
                "Version conflict detected: same version ({$firestoreVersion}) but different data",
                true, // Requires manual intervention
                [
                    'firestore_version' => $firestoreVersion,
                    'local_version' => $localVersion,
                    'conflict_type' => 'version_mismatch',
                ]
            );
        }
    }

    /**
     * Detect if there's a conflict between two data sets.
     */
    public function hasConflict(array $firestoreData, array $localData, array $metadata = []): bool
    {
        $firestoreVersion = $this->extractVersion($firestoreData);
        $localVersion = $this->extractVersion($localData);

        // If versions are different, there's a potential conflict
        if ($firestoreVersion !== $localVersion) {
            return true;
        }

        // If versions are the same, check if data is different
        return $this->hasDataConflict($firestoreData, $localData);
    }

    /**
     * Get the resolver name.
     */
    public function getName(): string
    {
        return 'version_based';
    }

    /**
     * Get the resolver priority.
     */
    public function getPriority(): int
    {
        return 200; // Higher priority than timestamp-based
    }

    /**
     * Extract version from data array.
     */
    protected function extractVersion(array $data): ?int
    {
        $version = $data[$this->versionField] ?? null;

        if ($version === null) {
            return null;
        }

        if (is_numeric($version)) {
            return (int) $version;
        }

        return null;
    }

    /**
     * Check if there's a data conflict (excluding version fields).
     */
    protected function hasDataConflict(array $firestoreData, array $localData): bool
    {
        $firestoreComparison = $this->prepareForComparison($firestoreData);
        $localComparison = $this->prepareForComparison($localData);

        return $firestoreComparison !== $localComparison;
    }

    /**
     * Prepare data for comparison by removing version and metadata fields.
     */
    protected function prepareForComparison(array $data): array
    {
        $comparison = $data;

        // Remove fields that shouldn't be compared
        $excludeFields = [
            'id',
            '_version',
            '_sync_version',
            '_last_synced_at',
            '_conflict_detected',
            '_conflict_timestamp',
            'created_at',
            'updated_at',
        ];

        foreach ($excludeFields as $field) {
            unset($comparison[$field]);
        }

        // Sort array to ensure consistent comparison
        ksort($comparison);

        return $comparison;
    }

    /**
     * Set the version field to use.
     */
    public function setVersionField(string $field): void
    {
        $this->versionField = $field;
    }

    /**
     * Get the version field being used.
     */
    public function getVersionField(): string
    {
        return $this->versionField;
    }

    /**
     * Set whether to auto-increment versions.
     */
    public function setAutoIncrement(bool $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    /**
     * Check if auto-increment is enabled.
     */
    public function isAutoIncrementEnabled(): bool
    {
        return $this->autoIncrement;
    }
}
