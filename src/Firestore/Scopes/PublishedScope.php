<?php

namespace JTD\FirebaseModels\Firestore\Scopes;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder;

/**
 * Global scope to filter only published records.
 *
 * This scope automatically adds constraints to filter records
 * that are published and not scheduled for future publication.
 */
class PublishedScope implements ScopeInterface
{
    /**
     * Apply the scope to a given Firestore query builder.
     */
    public function apply(FirestoreModelQueryBuilder $builder, FirestoreModel $model): void
    {
        $builder->where('published', true);
    }

    /**
     * Remove the scope from a given Firestore query builder.
     */
    public function remove(FirestoreModelQueryBuilder $builder, FirestoreModel $model): void
    {
        // No-op: scope removal is handled by creating new queries without scopes
    }
}
