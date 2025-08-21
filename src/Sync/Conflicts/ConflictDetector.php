<?php

namespace JTD\FirebaseModels\Sync\Conflicts;

use Illuminate\Support\Collection;
use JTD\FirebaseModels\Contracts\Sync\ConflictResolverInterface;

/**
 * Conflict detection system that analyzes differences between
 * Firestore and local database data.
 */
class ConflictDetector
{
    /**
     * Registered conflict resolvers.
     */
    protected Collection $resolvers;

    /**
     * Fields to ignore during conflict detection.
     */
    protected array $ignoredFields = [
        'id',
        'created_at',
        'updated_at',
        '_last_synced_at',
        '_sync_metadata'
    ];

    /**
     * Create a new conflict detector.
     */
    public function __construct()
    {
        $this->resolvers = new Collection();
    }

    /**
     * Register a conflict resolver.
     */
    public function addResolver(ConflictResolverInterface $resolver): void
    {
        $this->resolvers->push($resolver);
        
        // Sort by priority (highest first)
        $this->resolvers = $this->resolvers->sortByDesc(function ($resolver) {
            return $resolver->getPriority();
        });
    }

    /**
     * Detect conflicts between Firestore and local data.
     */
    public function detectConflicts(array $firestoreData, array $localData, array $metadata = []): array
    {
        $conflicts = [];

        // Check each resolver for conflicts
        foreach ($this->resolvers as $resolver) {
            if ($resolver->hasConflict($firestoreData, $localData, $metadata)) {
                $conflicts[] = [
                    'resolver' => $resolver->getName(),
                    'priority' => $resolver->getPriority(),
                    'detected_at' => now()->toISOString(),
                    'metadata' => $metadata
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Analyze the differences between two data sets.
     */
    public function analyzeDifferences(array $firestoreData, array $localData): array
    {
        $differences = [
            'added' => [],
            'removed' => [],
            'modified' => [],
            'type_changes' => []
        ];

        // Prepare data for comparison
        $firestoreFiltered = $this->filterData($firestoreData);
        $localFiltered = $this->filterData($localData);

        // Find added fields (in Firestore but not in local)
        foreach ($firestoreFiltered as $key => $value) {
            if (!array_key_exists($key, $localFiltered)) {
                $differences['added'][$key] = $value;
            }
        }

        // Find removed fields (in local but not in Firestore)
        foreach ($localFiltered as $key => $value) {
            if (!array_key_exists($key, $firestoreFiltered)) {
                $differences['removed'][$key] = $value;
            }
        }

        // Find modified fields
        foreach ($firestoreFiltered as $key => $firestoreValue) {
            if (array_key_exists($key, $localFiltered)) {
                $localValue = $localFiltered[$key];
                
                // Check for type changes
                if (gettype($firestoreValue) !== gettype($localValue)) {
                    $differences['type_changes'][$key] = [
                        'firestore' => ['type' => gettype($firestoreValue), 'value' => $firestoreValue],
                        'local' => ['type' => gettype($localValue), 'value' => $localValue]
                    ];
                } elseif ($this->valuesAreDifferent($firestoreValue, $localValue)) {
                    $differences['modified'][$key] = [
                        'firestore' => $firestoreValue,
                        'local' => $localValue
                    ];
                }
            }
        }

        return $differences;
    }

    /**
     * Get the best resolver for a conflict.
     */
    public function getBestResolver(array $firestoreData, array $localData, array $metadata = []): ?ConflictResolverInterface
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->hasConflict($firestoreData, $localData, $metadata)) {
                return $resolver;
            }
        }

        return null;
    }

    /**
     * Check if there are any conflicts using any resolver.
     */
    public function hasAnyConflict(array $firestoreData, array $localData, array $metadata = []): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->hasConflict($firestoreData, $localData, $metadata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get conflict severity based on the type of differences.
     */
    public function getConflictSeverity(array $differences): string
    {
        if (!empty($differences['type_changes'])) {
            return 'high';
        }

        if (!empty($differences['removed'])) {
            return 'medium';
        }

        if (!empty($differences['modified'])) {
            $modifiedCount = count($differences['modified']);
            return $modifiedCount > 5 ? 'medium' : 'low';
        }

        if (!empty($differences['added'])) {
            return 'low';
        }

        return 'none';
    }

    /**
     * Generate a conflict report.
     */
    public function generateConflictReport(array $firestoreData, array $localData, array $metadata = []): array
    {
        $differences = $this->analyzeDifferences($firestoreData, $localData);
        $conflicts = $this->detectConflicts($firestoreData, $localData, $metadata);
        $severity = $this->getConflictSeverity($differences);
        $bestResolver = $this->getBestResolver($firestoreData, $localData, $metadata);

        return [
            'has_conflict' => !empty($conflicts),
            'severity' => $severity,
            'differences' => $differences,
            'conflicts' => $conflicts,
            'best_resolver' => $bestResolver ? $bestResolver->getName() : null,
            'resolver_count' => $this->resolvers->count(),
            'generated_at' => now()->toISOString(),
            'metadata' => $metadata
        ];
    }

    /**
     * Filter data by removing ignored fields.
     */
    protected function filterData(array $data): array
    {
        $filtered = $data;

        foreach ($this->ignoredFields as $field) {
            unset($filtered[$field]);
        }

        return $filtered;
    }

    /**
     * Check if two values are different, handling special cases.
     */
    protected function valuesAreDifferent($value1, $value2): bool
    {
        // Handle arrays
        if (is_array($value1) && is_array($value2)) {
            return $this->arraysAreDifferent($value1, $value2);
        }

        // Handle objects
        if (is_object($value1) && is_object($value2)) {
            return serialize($value1) !== serialize($value2);
        }

        // Handle null comparisons
        if ($value1 === null || $value2 === null) {
            return $value1 !== $value2;
        }

        // Handle numeric comparisons with tolerance
        if (is_numeric($value1) && is_numeric($value2)) {
            return abs($value1 - $value2) > 0.0001;
        }

        // Default comparison
        return $value1 !== $value2;
    }

    /**
     * Compare arrays for differences.
     */
    protected function arraysAreDifferent(array $array1, array $array2): bool
    {
        if (count($array1) !== count($array2)) {
            return true;
        }

        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                return true;
            }

            if ($this->valuesAreDifferent($value, $array2[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a field to ignore during conflict detection.
     */
    public function addIgnoredField(string $field): void
    {
        if (!in_array($field, $this->ignoredFields)) {
            $this->ignoredFields[] = $field;
        }
    }

    /**
     * Remove a field from the ignored list.
     */
    public function removeIgnoredField(string $field): void
    {
        $this->ignoredFields = array_filter($this->ignoredFields, function ($ignoredField) use ($field) {
            return $ignoredField !== $field;
        });
    }

    /**
     * Get all ignored fields.
     */
    public function getIgnoredFields(): array
    {
        return $this->ignoredFields;
    }

    /**
     * Get all registered resolvers.
     */
    public function getResolvers(): Collection
    {
        return $this->resolvers;
    }
}
