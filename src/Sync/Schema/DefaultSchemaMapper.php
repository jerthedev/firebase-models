<?php

namespace JTD\FirebaseModels\Sync\Schema;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use JTD\FirebaseModels\Contracts\Sync\SchemaMapperInterface;

/**
 * Default schema mapper that provides basic mapping between
 * Firestore collections and local database tables.
 */
class DefaultSchemaMapper implements SchemaMapperInterface
{
    /**
     * Registered collection mappings.
     */
    protected array $mappings = [];

    /**
     * Default column mappings for common Firestore fields.
     */
    protected array $defaultMappings = [
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'deleted_at' => 'deleted_at',
    ];

    /**
     * Get the local table name for a Firestore collection.
     */
    public function getTableName(string $collection): string
    {
        if (isset($this->mappings[$collection]['table'])) {
            return $this->mappings[$collection]['table'];
        }

        // Default: use collection name as table name
        return $collection;
    }

    /**
     * Map Firestore document data to local database columns.
     */
    public function mapToLocal(string $collection, array $firestoreData): array
    {
        $mapping = $this->getColumnMapping($collection);
        $localData = [];

        foreach ($firestoreData as $field => $value) {
            // Get the local column name
            $localColumn = $mapping[$field] ?? $field;
            
            // Skip if mapped to null (excluded field)
            if ($localColumn === null) {
                continue;
            }

            // Transform the value based on type
            $localData[$localColumn] = $this->transformToLocal($field, $value);
        }

        // Add timestamps if not present
        $this->addDefaultTimestamps($localData);

        return $localData;
    }

    /**
     * Map local database data to Firestore document format.
     */
    public function mapToFirestore(string $collection, array $localData): array
    {
        $mapping = array_flip($this->getColumnMapping($collection));
        $firestoreData = [];

        foreach ($localData as $column => $value) {
            // Get the Firestore field name
            $firestoreField = $mapping[$column] ?? $column;
            
            // Skip if mapped to null or is the ID field
            if ($firestoreField === null || $column === 'id') {
                continue;
            }

            // Transform the value based on type
            $firestoreData[$firestoreField] = $this->transformToFirestore($column, $value);
        }

        return $firestoreData;
    }

    /**
     * Get the column mapping for a collection.
     */
    public function getColumnMapping(string $collection): array
    {
        $customMapping = $this->mappings[$collection]['columns'] ?? [];
        
        return array_merge($this->defaultMappings, $customMapping);
    }

    /**
     * Check if a collection has a local table mapping.
     */
    public function hasMapping(string $collection): bool
    {
        // For now, assume all collections can be mapped
        // In the future, this could check if a table exists
        return true;
    }

    /**
     * Register a new collection mapping.
     */
    public function registerMapping(string $collection, string $table, array $columnMapping = []): void
    {
        $this->mappings[$collection] = [
            'table' => $table,
            'columns' => $columnMapping,
        ];
    }

    /**
     * Transform a value from Firestore to local database format.
     */
    protected function transformToLocal(string $field, mixed $value): mixed
    {
        // Handle Firestore timestamps
        if ($value instanceof \Google\Cloud\Core\Timestamp) {
            return Carbon::createFromTimestamp($value->get()->getSeconds());
        }

        // Handle Firestore DateTime objects
        if ($value instanceof \DateTime) {
            return Carbon::instance($value);
        }

        // Handle arrays (convert to JSON for storage)
        if (is_array($value)) {
            return json_encode($value);
        }

        // Handle boolean values
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    /**
     * Transform a value from local database to Firestore format.
     */
    protected function transformToFirestore(string $column, mixed $value): mixed
    {
        // Handle timestamp columns
        if (in_array($column, ['created_at', 'updated_at', 'deleted_at']) && $value) {
            if (is_string($value)) {
                return Carbon::parse($value);
            }
            if ($value instanceof Carbon) {
                return $value;
            }
        }

        // Handle JSON columns (convert back to array)
        if (is_string($value) && $this->isJsonColumn($column)) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        }

        // Handle boolean values
        if (is_int($value) && in_array($value, [0, 1])) {
            return (bool) $value;
        }

        return $value;
    }

    /**
     * Add default timestamps to local data if not present.
     */
    protected function addDefaultTimestamps(array &$localData): void
    {
        $now = now();

        if (!isset($localData['created_at'])) {
            $localData['created_at'] = $now;
        }

        if (!isset($localData['updated_at'])) {
            $localData['updated_at'] = $now;
        }
    }

    /**
     * Check if a column should be treated as JSON.
     */
    protected function isJsonColumn(string $column): bool
    {
        // Common JSON column patterns
        $jsonPatterns = ['_data', '_meta', '_config', '_settings', '_attributes'];
        
        foreach ($jsonPatterns as $pattern) {
            if (Str::contains($column, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all registered mappings.
     */
    public function getAllMappings(): array
    {
        return $this->mappings;
    }

    /**
     * Load mappings from configuration.
     */
    public function loadFromConfig(array $config): void
    {
        foreach ($config as $collection => $mapping) {
            $this->registerMapping(
                $collection,
                $mapping['table'] ?? $collection,
                $mapping['columns'] ?? []
            );
        }
    }
}
