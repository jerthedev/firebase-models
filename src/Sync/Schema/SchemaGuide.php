<?php

namespace JTD\FirebaseModels\Sync\Schema;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

/**
 * Schema guidance utility for creating local database tables
 * that mirror Firestore collections.
 */
class SchemaGuide
{
    /**
     * Generate a migration for a Firestore collection.
     */
    public static function generateMigration(string $collection, array $sampleData = []): string
    {
        $tableName = Str::snake($collection);
        $className = 'Create' . Str::studly($tableName) . 'Table';
        
        $columns = static::analyzeFields($sampleData);
        $columnDefinitions = static::generateColumnDefinitions($columns);

        return static::buildMigrationTemplate($className, $tableName, $columnDefinitions);
    }

    /**
     * Analyze Firestore document fields to determine column types.
     */
    public static function analyzeFields(array $data): array
    {
        $columns = [];

        foreach ($data as $field => $value) {
            $columns[$field] = static::inferColumnType($field, $value);
        }

        // Add standard columns
        $columns = array_merge([
            'id' => ['type' => 'string', 'primary' => true],
        ], $columns, [
            'created_at' => ['type' => 'timestamp', 'nullable' => true],
            'updated_at' => ['type' => 'timestamp', 'nullable' => true],
        ]);

        return $columns;
    }

    /**
     * Infer the column type from a Firestore field value.
     */
    protected static function inferColumnType(string $field, mixed $value): array
    {
        // Handle null values
        if ($value === null) {
            return ['type' => 'string', 'nullable' => true];
        }

        // Handle different value types
        switch (gettype($value)) {
            case 'boolean':
                return ['type' => 'boolean', 'default' => false];
                
            case 'integer':
                return ['type' => 'integer'];
                
            case 'double':
                return ['type' => 'decimal', 'precision' => 10, 'scale' => 2];
                
            case 'string':
                // Check for common patterns
                if (Str::endsWith($field, '_at') || Str::endsWith($field, '_date')) {
                    return ['type' => 'timestamp', 'nullable' => true];
                }
                
                if (Str::endsWith($field, '_id') || $field === 'id') {
                    return ['type' => 'string'];
                }
                
                if (Str::endsWith($field, '_email') || $field === 'email') {
                    return ['type' => 'string', 'unique' => true];
                }
                
                // Default string length based on content
                $length = strlen($value);
                if ($length > 500) {
                    return ['type' => 'text'];
                } elseif ($length > 255) {
                    return ['type' => 'string', 'length' => 500];
                } else {
                    return ['type' => 'string'];
                }
                
            case 'array':
                return ['type' => 'json'];
                
            default:
                return ['type' => 'text'];
        }
    }

    /**
     * Generate column definitions for migration.
     */
    protected static function generateColumnDefinitions(array $columns): string
    {
        $definitions = [];

        foreach ($columns as $name => $config) {
            $definition = static::buildColumnDefinition($name, $config);
            if ($definition) {
                $definitions[] = $definition;
            }
        }

        return implode("\n            ", $definitions);
    }

    /**
     * Build a single column definition.
     */
    protected static function buildColumnDefinition(string $name, array $config): string
    {
        $type = $config['type'];
        $definition = "\$table->{$type}('{$name}'";

        // Add type-specific parameters
        if (isset($config['length'])) {
            $definition .= ", {$config['length']}";
        } elseif (isset($config['precision']) && isset($config['scale'])) {
            $definition .= ", {$config['precision']}, {$config['scale']}";
        }

        $definition .= ')';

        // Add modifiers
        if (isset($config['nullable']) && $config['nullable']) {
            $definition .= '->nullable()';
        }

        if (isset($config['default'])) {
            $default = is_string($config['default']) ? "'{$config['default']}'" : var_export($config['default'], true);
            $definition .= "->default({$default})";
        }

        if (isset($config['unique']) && $config['unique']) {
            $definition .= '->unique()';
        }

        if (isset($config['primary']) && $config['primary']) {
            $definition .= '->primary()';
        }

        $definition .= ';';

        return $definition;
    }

    /**
     * Build the complete migration template.
     */
    protected static function buildMigrationTemplate(string $className, string $tableName, string $columns): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            {$columns}
            
            // Add indexes for common query patterns
            \$table->index(['created_at']);
            \$table->index(['updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
    }

    /**
     * Get recommended indexes for a collection.
     */
    public static function getRecommendedIndexes(string $collection, array $queryPatterns = []): array
    {
        $indexes = [
            ['columns' => ['created_at'], 'type' => 'index'],
            ['columns' => ['updated_at'], 'type' => 'index'],
        ];

        // Add indexes based on query patterns
        foreach ($queryPatterns as $pattern) {
            if (isset($pattern['where'])) {
                foreach ($pattern['where'] as $field) {
                    $indexes[] = ['columns' => [$field], 'type' => 'index'];
                }
            }
        }

        return array_unique($indexes, SORT_REGULAR);
    }

    /**
     * Validate a table schema against Firestore collection structure.
     */
    public static function validateSchema(string $tableName, array $firestoreFields): array
    {
        $issues = [];
        
        if (!Schema::hasTable($tableName)) {
            $issues[] = "Table '{$tableName}' does not exist";
            return $issues;
        }

        $tableColumns = Schema::getColumnListing($tableName);
        
        // Check for missing columns
        foreach ($firestoreFields as $field) {
            if (!in_array($field, $tableColumns)) {
                $issues[] = "Missing column '{$field}' in table '{$tableName}'";
            }
        }

        // Check for required columns
        $requiredColumns = ['id', 'created_at', 'updated_at'];
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $tableColumns)) {
                $issues[] = "Missing required column '{$column}' in table '{$tableName}'";
            }
        }

        return $issues;
    }
}
PHP;
    }
}
