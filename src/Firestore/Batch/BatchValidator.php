<?php

namespace JTD\FirebaseModels\Firestore\Batch;

use JTD\FirebaseModels\Firestore\Batch\Exceptions\BatchException;

/**
 * Validator for batch operations with comprehensive validation rules.
 */
class BatchValidator
{
    /**
     * Firestore limits and constraints.
     */
    protected static array $limits = [
        'max_operations_per_batch' => 500,
        'max_document_size' => 1048576, // 1MB in bytes
        'max_field_name_length' => 1500,
        'max_field_value_length' => 1048487, // ~1MB
        'max_array_elements' => 20000,
        'max_subcollection_depth' => 100,
    ];

    /**
     * Validation rules for different operation types.
     */
    protected static array $rules = [
        'create' => ['collection', 'data'],
        'update' => ['collection', 'id', 'data'],
        'delete' => ['collection', 'id'],
        'set' => ['collection', 'id', 'data'],
    ];

    /**
     * Validate a batch operation.
     */
    public static function validateBatchOperation(BatchOperation $batch): array
    {
        $errors = [];
        $operations = $batch->getOperations();

        // Check operation count
        if (count($operations) > static::$limits['max_operations_per_batch']) {
            $errors[] = 'Too many operations: '.count($operations).' (max: '.static::$limits['max_operations_per_batch'].')';
        }

        // Validate each operation
        foreach ($operations as $index => $operation) {
            $operationErrors = static::validateOperation($operation, $index);
            $errors = array_merge($errors, $operationErrors);
        }

        return $errors;
    }

    /**
     * Validate a single operation.
     */
    public static function validateOperation(array $operation, int $index = 0): array
    {
        $errors = [];
        $type = $operation['type'] ?? null;

        // Check operation type
        if (!$type || !isset(static::$rules[$type])) {
            $errors[] = "Operation {$index}: Invalid or missing operation type";

            return $errors;
        }

        // Check required fields
        foreach (static::$rules[$type] as $field) {
            if (!isset($operation[$field]) || $operation[$field] === null) {
                $errors[] = "Operation {$index}: Missing required field '{$field}'";
            }
        }

        // Validate collection name
        if (isset($operation['collection'])) {
            $collectionErrors = static::validateCollectionName($operation['collection'], $index);
            $errors = array_merge($errors, $collectionErrors);
        }

        // Validate document ID
        if (isset($operation['id'])) {
            $idErrors = static::validateDocumentId($operation['id'], $index);
            $errors = array_merge($errors, $idErrors);
        }

        // Validate document data
        if (isset($operation['data'])) {
            $dataErrors = static::validateDocumentData($operation['data'], $index);
            $errors = array_merge($errors, $dataErrors);
        }

        return $errors;
    }

    /**
     * Validate collection name.
     */
    public static function validateCollectionName(string $collection, int $index = 0): array
    {
        $errors = [];

        if (empty($collection)) {
            $errors[] = "Operation {$index}: Collection name cannot be empty";

            return $errors;
        }

        // Check for invalid characters
        if (preg_match('/[\/\x00-\x1f\x7f]/', $collection)) {
            $errors[] = "Operation {$index}: Collection name contains invalid characters";
        }

        // Check length
        if (strlen($collection) > 1500) {
            $errors[] = "Operation {$index}: Collection name too long (max: 1500 characters)";
        }

        // Check for reserved names
        $reserved = ['__.*__'];
        foreach ($reserved as $pattern) {
            if (preg_match("/{$pattern}/", $collection)) {
                $errors[] = "Operation {$index}: Collection name '{$collection}' is reserved";
            }
        }

        return $errors;
    }

    /**
     * Validate document ID.
     */
    public static function validateDocumentId(string $id, int $index = 0): array
    {
        $errors = [];

        if (empty($id)) {
            $errors[] = "Operation {$index}: Document ID cannot be empty";

            return $errors;
        }

        // Check for invalid characters
        if (preg_match('/[\/\x00-\x1f\x7f]/', $id)) {
            $errors[] = "Operation {$index}: Document ID contains invalid characters";
        }

        // Check length
        if (strlen($id) > 1500) {
            $errors[] = "Operation {$index}: Document ID too long (max: 1500 characters)";
        }

        // Check for reserved patterns
        if (preg_match('/^\.\.?$/', $id)) {
            $errors[] = "Operation {$index}: Document ID cannot be '.' or '..'";
        }

        return $errors;
    }

    /**
     * Validate document data.
     */
    public static function validateDocumentData(array $data, int $index = 0): array
    {
        $errors = [];

        // Check document size
        $size = static::calculateDocumentSize($data);
        if ($size > static::$limits['max_document_size']) {
            $errors[] = "Operation {$index}: Document size ({$size} bytes) exceeds limit (".static::$limits['max_document_size'].' bytes)';
        }

        // Validate fields
        $fieldErrors = static::validateFields($data, $index);
        $errors = array_merge($errors, $fieldErrors);

        return $errors;
    }

    /**
     * Validate document fields recursively.
     */
    public static function validateFields(array $data, int $index = 0, string $path = ''): array
    {
        $errors = [];

        foreach ($data as $key => $value) {
            $fieldPath = $path ? "{$path}.{$key}" : $key;

            // Validate field name
            $nameErrors = static::validateFieldName($key, $index, $fieldPath);
            $errors = array_merge($errors, $nameErrors);

            // Validate field value
            $valueErrors = static::validateFieldValue($value, $index, $fieldPath);
            $errors = array_merge($errors, $valueErrors);

            // Recursively validate nested objects
            if (is_array($value) && static::isAssociativeArray($value)) {
                $nestedErrors = static::validateFields($value, $index, $fieldPath);
                $errors = array_merge($errors, $nestedErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate field name.
     */
    public static function validateFieldName(string $name, int $index = 0, string $path = ''): array
    {
        $errors = [];

        // Check for invalid characters
        if (preg_match('/[\x00-\x1f\x7f]/', $name)) {
            $errors[] = "Operation {$index}: Field name '{$path}' contains invalid characters";
        }

        // Check length
        if (strlen($name) > static::$limits['max_field_name_length']) {
            $errors[] = "Operation {$index}: Field name '{$path}' too long (max: ".static::$limits['max_field_name_length'].' characters)';
        }

        // Check for reserved field names
        if (preg_match('/^__.*__$/', $name)) {
            $errors[] = "Operation {$index}: Field name '{$path}' is reserved";
        }

        return $errors;
    }

    /**
     * Validate field value.
     */
    public static function validateFieldValue(mixed $value, int $index = 0, string $path = ''): array
    {
        $errors = [];

        if (is_string($value)) {
            if (strlen($value) > static::$limits['max_field_value_length']) {
                $errors[] = "Operation {$index}: String field '{$path}' too long (max: ".static::$limits['max_field_value_length'].' characters)';
            }
        } elseif (is_array($value) && !static::isAssociativeArray($value)) {
            // Array validation
            if (count($value) > static::$limits['max_array_elements']) {
                $errors[] = "Operation {$index}: Array field '{$path}' has too many elements (max: ".static::$limits['max_array_elements'].')';
            }
        }

        return $errors;
    }

    /**
     * Calculate document size in bytes.
     */
    public static function calculateDocumentSize(array $data): int
    {
        return strlen(json_encode($data));
    }

    /**
     * Check if array is associative (object-like).
     */
    public static function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Validate and throw exception if errors found.
     */
    public static function validateOrFail(BatchOperation $batch): void
    {
        $errors = static::validateBatchOperation($batch);

        if (!empty($errors)) {
            throw new BatchException(
                'Batch validation failed: '.implode('; ', $errors),
                0,
                null,
                ['validation_errors' => $errors],
                $batch->getOperationCount(),
                'validation'
            );
        }
    }

    /**
     * Get current validation limits.
     */
    public static function getLimits(): array
    {
        return static::$limits;
    }

    /**
     * Set custom validation limits.
     */
    public static function setLimits(array $limits): void
    {
        static::$limits = array_merge(static::$limits, $limits);
    }

    /**
     * Get validation rules.
     */
    public static function getRules(): array
    {
        return static::$rules;
    }
}
