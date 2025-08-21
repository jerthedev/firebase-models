<?php

namespace JTD\FirebaseModels\Testing;

use Illuminate\Support\Collection;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Firestore\Relations\Relation;
use JTD\FirebaseModels\Firestore\Relations\BelongsTo;
use JTD\FirebaseModels\Firestore\Relations\HasMany;

/**
 * Testing utilities for Firestore model relationships.
 */
class RelationshipTestHelper
{
    /**
     * Assert that a model has a specific relationship.
     */
    public static function assertHasRelation(FirestoreModel $model, string $relationName, string $expectedType = null): void
    {
        if (!method_exists($model, $relationName)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Model " . get_class($model) . " does not have a '{$relationName}' relationship method."
            );
        }

        $relation = $model->$relationName();

        if (!$relation instanceof Relation) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Method '{$relationName}' does not return a Relation instance."
            );
        }

        if ($expectedType && !$relation instanceof $expectedType) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Relationship '{$relationName}' is not of type '{$expectedType}', got " . get_class($relation)
            );
        }
    }

    /**
     * Assert that a BelongsTo relationship is properly configured.
     */
    public static function assertBelongsToRelation(
        FirestoreModel $model,
        string $relationName,
        string $expectedRelatedModel,
        string $expectedForeignKey = null,
        string $expectedOwnerKey = null
    ): void {
        static::assertHasRelation($model, $relationName, BelongsTo::class);

        $relation = $model->$relationName();

        // Check related model
        $relatedModel = $relation->getRelated();
        if (!$relatedModel instanceof $expectedRelatedModel) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "BelongsTo relationship '{$relationName}' should relate to '{$expectedRelatedModel}', got " . get_class($relatedModel)
            );
        }

        // Check foreign key
        if ($expectedForeignKey && $relation->getForeignKeyName() !== $expectedForeignKey) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "BelongsTo relationship '{$relationName}' should use foreign key '{$expectedForeignKey}', got '{$relation->getForeignKeyName()}'"
            );
        }

        // Check owner key
        if ($expectedOwnerKey && $relation->getLocalKeyName() !== $expectedOwnerKey) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "BelongsTo relationship '{$relationName}' should use owner key '{$expectedOwnerKey}', got '{$relation->getLocalKeyName()}'"
            );
        }
    }

    /**
     * Assert that a HasMany relationship is properly configured.
     */
    public static function assertHasManyRelation(
        FirestoreModel $model,
        string $relationName,
        string $expectedRelatedModel,
        string $expectedForeignKey = null,
        string $expectedLocalKey = null
    ): void {
        static::assertHasRelation($model, $relationName, HasMany::class);

        $relation = $model->$relationName();

        // Check related model
        $relatedModel = $relation->getRelated();
        if (!$relatedModel instanceof $expectedRelatedModel) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "HasMany relationship '{$relationName}' should relate to '{$expectedRelatedModel}', got " . get_class($relatedModel)
            );
        }

        // Check foreign key
        if ($expectedForeignKey && $relation->getForeignKeyName() !== $expectedForeignKey) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "HasMany relationship '{$relationName}' should use foreign key '{$expectedForeignKey}', got '{$relation->getForeignKeyName()}'"
            );
        }

        // Check local key
        if ($expectedLocalKey && $relation->getLocalKeyName() !== $expectedLocalKey) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "HasMany relationship '{$relationName}' should use local key '{$expectedLocalKey}', got '{$relation->getLocalKeyName()}'"
            );
        }
    }

    /**
     * Assert that eager loading works for a relationship.
     */
    public static function assertEagerLoading(string $modelClass, string $relationName, array $testData = []): void
    {
        if (empty($testData)) {
            throw new \InvalidArgumentException('Test data is required for eager loading assertion');
        }

        // Create test models
        $models = static::createTestModels($modelClass, $testData);

        // Test eager loading
        $eagerModels = $modelClass::with($relationName)->get();

        foreach ($eagerModels as $model) {
            if (!$model->relationLoaded($relationName)) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Relationship '{$relationName}' was not eager loaded on model " . get_class($model)
                );
            }
        }

        // Clean up
        static::cleanupTestModels($models);
    }

    /**
     * Assert that a relationship returns the expected results.
     */
    public static function assertRelationshipResults(
        FirestoreModel $model,
        string $relationName,
        int $expectedCount = null,
        string $expectedType = null
    ): void {
        $results = $model->$relationName;

        if ($expectedType) {
            if ($expectedType === 'collection' && !$results instanceof Collection) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Relationship '{$relationName}' should return a Collection, got " . gettype($results)
                );
            }

            if ($expectedType === 'model' && !$results instanceof FirestoreModel && !is_null($results)) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Relationship '{$relationName}' should return a Model or null, got " . gettype($results)
                );
            }
        }

        if ($expectedCount !== null) {
            $actualCount = $results instanceof Collection ? $results->count() : ($results ? 1 : 0);
            
            if ($actualCount !== $expectedCount) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    "Relationship '{$relationName}' should return {$expectedCount} results, got {$actualCount}"
                );
            }
        }
    }

    /**
     * Create test models for relationship testing.
     */
    public static function createTestModels(string $modelClass, array $data): Collection
    {
        $models = new Collection();

        foreach ($data as $record) {
            $model = $modelClass::create($record);
            $models->push($model);
        }

        return $models;
    }

    /**
     * Clean up test models.
     */
    public static function cleanupTestModels(Collection $models): void
    {
        foreach ($models as $model) {
            if ($model->exists) {
                $model->delete();
            }
        }
    }

    /**
     * Create test data for parent-child relationships.
     */
    public static function createParentChildTestData(
        string $parentClass,
        string $childClass,
        string $foreignKey,
        int $parentCount = 2,
        int $childrenPerParent = 3
    ): array {
        $parents = [];
        $children = [];

        // Create parents
        for ($i = 1; $i <= $parentCount; $i++) {
            $parent = $parentClass::create([
                'name' => "Parent {$i}",
                'description' => "Test parent {$i}",
            ]);
            $parents[] = $parent;

            // Create children for this parent
            for ($j = 1; $j <= $childrenPerParent; $j++) {
                $child = $childClass::create([
                    'name' => "Child {$i}-{$j}",
                    'description' => "Test child {$j} of parent {$i}",
                    $foreignKey => $parent->getKey(),
                ]);
                $children[] = $child;
            }
        }

        return [
            'parents' => new Collection($parents),
            'children' => new Collection($children),
        ];
    }

    /**
     * Test relationship performance.
     */
    public static function measureRelationshipPerformance(
        FirestoreModel $model,
        string $relationName,
        int $iterations = 100
    ): array {
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $results = $model->$relationName;
            $end = microtime(true);

            $times[] = ($end - $start) * 1000; // Convert to milliseconds
        }

        return [
            'iterations' => $iterations,
            'total_time_ms' => array_sum($times),
            'average_time_ms' => array_sum($times) / count($times),
            'min_time_ms' => min($times),
            'max_time_ms' => max($times),
            'median_time_ms' => static::calculateMedian($times),
        ];
    }

    /**
     * Calculate median of an array.
     */
    protected static function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }

    /**
     * Assert that N+1 query problem is avoided with eager loading.
     */
    public static function assertNoNPlusOneQueries(
        string $modelClass,
        string $relationName,
        array $testData,
        int $tolerance = 2
    ): void {
        // Create test data
        $testModels = static::createTestModels($modelClass, $testData);

        // Count queries without eager loading
        $queriesWithoutEager = static::countQueries(function () use ($modelClass, $relationName) {
            $models = $modelClass::limit(10)->get();
            foreach ($models as $model) {
                $model->$relationName; // This should trigger individual queries
            }
        });

        // Count queries with eager loading
        $queriesWithEager = static::countQueries(function () use ($modelClass, $relationName) {
            $models = $modelClass::with($relationName)->limit(10)->get();
            foreach ($models as $model) {
                $model->$relationName; // This should not trigger additional queries
            }
        });

        // Clean up
        static::cleanupTestModels($testModels);

        // Assert that eager loading significantly reduces queries
        if ($queriesWithEager >= $queriesWithoutEager - $tolerance) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                "Eager loading did not reduce queries significantly. Without eager: {$queriesWithoutEager}, With eager: {$queriesWithEager}"
            );
        }
    }

    /**
     * Count queries executed during a callback.
     * Note: This is a simplified implementation. In a real scenario,
     * you'd want to integrate with your logging or monitoring system.
     */
    protected static function countQueries(callable $callback): int
    {
        // This is a placeholder implementation
        // In practice, you'd hook into Firestore query logging
        $callback();
        return 1; // Simplified for demonstration
    }
}
