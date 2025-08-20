<?php

namespace JTD\FirebaseModels\Firestore\Scopes;

use JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder;
use JTD\FirebaseModels\Firestore\FirestoreModel;

/**
 * Global scope to filter only active records.
 * 
 * This scope automatically adds a where clause to filter records
 * where the 'active' field is true. Useful for models that have
 * an active/inactive status.
 */
class ActiveScope implements ScopeInterface
{
    /**
     * The column name to check for active status.
     */
    protected string $column;

    /**
     * The value that indicates an active record.
     */
    protected mixed $activeValue;

    /**
     * Create a new active scope instance.
     */
    public function __construct(string $column = 'active', mixed $activeValue = true)
    {
        $this->column = $column;
        $this->activeValue = $activeValue;
    }

    /**
     * Apply the scope to a given Firestore query builder.
     */
    public function apply(FirestoreModelQueryBuilder $builder, FirestoreModel $model): void
    {
        $builder->where($this->column, $this->activeValue);
    }

    /**
     * Remove the scope from a given Firestore query builder.
     */
    public function remove(FirestoreModelQueryBuilder $builder, FirestoreModel $model): void
    {
        // No-op: scope removal is handled by creating new queries without scopes
    }

    /**
     * Get the column name being used for active status.
     */
    public function getColumn(): string
    {
        return $this->column;
    }

    /**
     * Get the value that indicates an active record.
     */
    public function getActiveValue(): mixed
    {
        return $this->activeValue;
    }
}
