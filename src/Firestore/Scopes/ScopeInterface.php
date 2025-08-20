<?php

namespace JTD\FirebaseModels\Firestore\Scopes;

use JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder;
use JTD\FirebaseModels\Firestore\FirestoreModel;

/**
 * Interface for global scopes.
 * 
 * Global scopes allow you to add constraints to all queries for a given model.
 * This is useful for implementing features like soft deletes, multi-tenancy,
 * or any other global filtering logic.
 */
interface ScopeInterface
{
    /**
     * Apply the scope to a given Firestore query builder.
     */
    public function apply(FirestoreModelQueryBuilder $builder, FirestoreModel $model): void;

    /**
     * Remove the scope from a given Firestore query builder.
     * 
     * This method is called when using withoutGlobalScope() or similar methods
     * to temporarily disable the scope for a specific query.
     */
    public function remove(FirestoreModelQueryBuilder $builder, FirestoreModel $model): void;
}
