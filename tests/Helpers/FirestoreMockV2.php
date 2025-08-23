<?php

namespace JTD\FirebaseModels\Tests\Helpers;

use Mockery;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\CollectionReference;
use Google\Cloud\Firestore\DocumentReference;
use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\Query;

/**
 * Enhanced FirebaseMock v2 with advanced query operations, compound indexes,
 * field transforms, and improved transaction/batch support.
 */
class FirestoreMockV2 extends FirestoreMock
{
    protected static ?FirestoreMockV2 $v2Instance = null;

    protected array $compoundIndexes = [];
    protected array $fieldTransforms = [];
    protected array $transactionState = [];
    protected bool $strictIndexValidation = false;

    /**
     * Initialize the v2 mock system
     */
    public static function initialize(): void
    {
        static::$v2Instance = new static();
        static::$v2Instance->setupMocks();

        // Also set the parent instance to our v2 instance for compatibility
        static::$instance = static::$v2Instance;
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (static::$v2Instance === null) {
            static::$v2Instance = new static();
        }

        return static::$v2Instance;
    }

    /**
     * Enable strict index validation (simulates production Firestore behavior)
     */
    public function enableStrictIndexValidation(): void
    {
        $this->strictIndexValidation = true;
    }

    /**
     * Add a compound index definition for validation
     */
    public function addCompoundIndex(string $collection, array $fields): void
    {
        if (!isset($this->compoundIndexes[$collection])) {
            $this->compoundIndexes[$collection] = [];
        }
        
        $this->compoundIndexes[$collection][] = $fields;
    }

    /**
     * Validate if a query requires a compound index
     */
    protected function validateQueryIndex(string $collection, array $wheres, array $orders): void
    {
        if (!$this->strictIndexValidation) {
            return;
        }

        // Check if query needs compound index
        $needsIndex = $this->queryNeedsCompoundIndex($wheres, $orders);
        
        if ($needsIndex && !$this->hasMatchingIndex($collection, $wheres, $orders)) {
            $indexFields = $this->generateIndexFields($wheres, $orders);
            throw new \Google\Cloud\Core\Exception\FailedPreconditionException(
                "The query requires an index. You can create it here: " .
                "https://console.firebase.google.com/project/test-project/firestore/indexes?" .
                "create_composite=" . urlencode(json_encode([
                    'collection' => $collection,
                    'fields' => $indexFields
                ]))
            );
        }
    }

    /**
     * Check if query needs a compound index
     */
    protected function queryNeedsCompoundIndex(array $wheres, array $orders): bool
    {
        // Multiple where clauses need compound index
        if (count($wheres) > 1) {
            return true;
        }

        // Where + order by different field needs compound index
        if (count($wheres) > 0 && count($orders) > 0) {
            $whereField = $wheres[0]['field'] ?? null;
            $orderField = $orders[0]['field'] ?? null;
            
            if ($whereField !== $orderField) {
                return true;
            }
        }

        // Array queries with other conditions need compound index
        foreach ($wheres as $where) {
            if (in_array($where['operator'], ['array-contains', 'array-contains-any'])) {
                if (count($wheres) > 1 || count($orders) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if collection has matching compound index
     */
    protected function hasMatchingIndex(string $collection, array $wheres, array $orders): bool
    {
        $indexes = $this->compoundIndexes[$collection] ?? [];
        $requiredFields = $this->generateIndexFields($wheres, $orders);

        foreach ($indexes as $index) {
            if ($this->indexMatches($index, $requiredFields)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate required index fields from query
     */
    protected function generateIndexFields(array $wheres, array $orders): array
    {
        $fields = [];

        // Add where fields (equality filters first)
        foreach ($wheres as $where) {
            $fields[] = [
                'field' => $where['field'],
                'order' => 'ASCENDING'
            ];
        }

        // Add order fields
        foreach ($orders as $order) {
            $direction = strtoupper($order['direction'] ?? 'ASC');
            if ($direction === 'DESC') {
                $direction = 'DESCENDING';
            } else {
                $direction = 'ASCENDING';
            }

            $fields[] = [
                'field' => $order['field'],
                'order' => $direction
            ];
        }

        return $fields;
    }

    /**
     * Check if index matches required fields
     */
    protected function indexMatches(array $index, array $requiredFields): bool
    {
        if (count($index) < count($requiredFields)) {
            return false;
        }

        foreach ($requiredFields as $i => $required) {
            if (!isset($index[$i]) || 
                $index[$i]['field'] !== $required['field'] ||
                $index[$i]['order'] !== $required['order']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Enhanced query execution with advanced operators
     */
    protected function executeQuery(string $collection, array $wheres = [], array $orders = [], ?int $limit = null, ?int $offset = null): array
    {
        // Validate index requirements
        $this->validateQueryIndex($collection, $wheres, $orders);

        $documents = $this->getCollectionDocuments($collection);
        
        // Apply enhanced where filters
        foreach ($wheres as $where) {
            $documents = array_filter($documents, function ($doc) use ($where) {
                return $this->evaluateWhereCondition($doc, $where);
            });
        }

        // Apply ordering
        if (!empty($orders)) {
            $documents = $this->applyOrdering($documents, $orders);
        }

        // Apply offset
        if ($offset !== null) {
            $documents = array_slice($documents, $offset);
        }

        // Apply limit
        if ($limit !== null) {
            $documents = array_slice($documents, 0, $limit);
        }

        return array_values($documents);
    }

    /**
     * Enhanced where condition evaluation with advanced operators
     */
    protected function evaluateWhereCondition($doc, array $where): bool
    {
        $data = $doc->data();
        $fieldValue = $this->getNestedFieldValue($data, $where['field']);
        
        return match ($where['operator']) {
            '=', '==' => $fieldValue == $where['value'],
            '!=' => $fieldValue != $where['value'],
            '>' => $fieldValue > $where['value'],
            '>=' => $fieldValue >= $where['value'],
            '<' => $fieldValue < $where['value'],
            '<=' => $fieldValue <= $where['value'],
            'in' => in_array($fieldValue, (array)$where['value']),
            'not-in' => !in_array($fieldValue, (array)$where['value']),
            'array-contains' => is_array($fieldValue) && in_array($where['value'], $fieldValue),
            'array-contains-any' => is_array($fieldValue) && !empty(array_intersect($fieldValue, (array)$where['value'])),
            'like' => str_contains(strtolower((string)$fieldValue), strtolower(str_replace('%', '', (string)$where['value']))),
            default => false,
        };
    }

    /**
     * Get nested field value using dot notation
     */
    protected function getNestedFieldValue(array $data, string $field): mixed
    {
        if (!str_contains($field, '.')) {
            return $data[$field] ?? null;
        }

        $keys = explode('.', $field);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Apply ordering to documents
     */
    protected function applyOrdering(array $documents, array $orders): array
    {
        usort($documents, function ($a, $b) use ($orders) {
            foreach ($orders as $order) {
                $aValue = $this->getNestedFieldValue($a->data(), $order['field']);
                $bValue = $this->getNestedFieldValue($b->data(), $order['field']);
                
                $comparison = $this->compareValues($aValue, $bValue);
                
                if ($comparison !== 0) {
                    $direction = strtoupper($order['direction'] ?? 'ASC');
                    return $direction === 'DESC' ? -$comparison : $comparison;
                }
            }
            return 0;
        });

        return $documents;
    }

    /**
     * Compare two values for sorting
     */
    protected function compareValues($a, $b): int
    {
        if ($a === $b) return 0;
        if ($a === null) return -1;
        if ($b === null) return 1;
        
        return $a <=> $b;
    }

    /**
     * Process field transforms (serverTimestamp, increment, etc.)
     */
    protected function processFieldTransforms(array $data): array
    {
        $processed = [];

        foreach ($data as $field => $value) {
            if ($this->isFieldTransform($value)) {
                $processed[$field] = $this->executeFieldTransform($value);
            } else {
                $processed[$field] = $value;
            }
        }

        return $processed;
    }

    /**
     * Check if value is a field transform
     */
    protected function isFieldTransform($value): bool
    {
        return is_array($value) && isset($value['_transform_type']);
    }

    /**
     * Execute field transform
     */
    protected function executeFieldTransform(array $transform): mixed
    {
        return match ($transform['_transform_type']) {
            'serverTimestamp' => new \DateTime(),
            'increment' => $transform['_value'] ?? 1,
            'arrayUnion' => $transform['_elements'] ?? [],
            'arrayRemove' => ['_remove' => $transform['_elements'] ?? []],
            default => $transform,
        };
    }

    /**
     * Override document storage to handle field transforms
     */
    public function storeDocument(string $collection, string $id, array $data): void
    {
        $processedData = $this->processFieldTransforms($data);
        parent::storeDocument($collection, $id, $processedData);
    }

    /**
     * Override document update to handle field transforms
     */
    public function updateDocument(string $collection, string $id, array $data): void
    {
        $existingData = $this->documents[$collection][$id] ?? [];

        // Apply field transforms with proper merging logic
        foreach ($data as $field => $value) {
            if ($this->isFieldTransform($value)) {
                $existingData[$field] = $this->applyFieldTransform($existingData[$field] ?? null, $value);
            } else {
                $existingData[$field] = $value;
            }
        }

        parent::updateDocument($collection, $id, $existingData);
    }

    /**
     * Apply field transform to existing value
     */
    protected function applyFieldTransform($currentValue, array $transform): mixed
    {
        return match ($transform['_transform_type']) {
            'serverTimestamp' => new \DateTime(),
            'increment' => ($currentValue ?? 0) + ($transform['_value'] ?? 1),
            'arrayUnion' => $this->applyArrayUnion($currentValue, $transform['_elements'] ?? []),
            'arrayRemove' => $this->applyArrayRemove($currentValue, $transform['_elements'] ?? []),
            default => $transform,
        };
    }

    /**
     * Apply array union operation
     */
    protected function applyArrayUnion($currentValue, array $elements): array
    {
        $current = is_array($currentValue) ? $currentValue : [];

        foreach ($elements as $element) {
            if (!in_array($element, $current, true)) {
                $current[] = $element;
            }
        }

        return $current;
    }

    /**
     * Apply array remove operation
     */
    protected function applyArrayRemove($currentValue, array $elements): array
    {
        if (!is_array($currentValue)) {
            return [];
        }

        return array_values(array_filter($currentValue, function ($item) use ($elements) {
            return !in_array($item, $elements, true);
        }));
    }

    /**
     * Clear all mock data including v2 specific data
     */
    public static function clear(): void
    {
        if (static::$v2Instance !== null) {
            $instance = static::$v2Instance;
            $instance->compoundIndexes = [];
            $instance->fieldTransforms = [];
            $instance->transactionState = [];
            $instance->strictIndexValidation = false;
        }

        parent::clear();
    }
}
