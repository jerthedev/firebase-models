<?php

namespace JTD\FirebaseModels\Contracts\Relations;

use Illuminate\Support\Collection;

/**
 * Interface for Firestore model relationships.
 */
interface RelationInterface
{
    /**
     * Get the results of the relationship.
     */
    public function getResults(): mixed;

    /**
     * Execute the relationship query.
     */
    public function get(): Collection;

    /**
     * Get the first result of the relationship.
     */
    public function first(): mixed;

    /**
     * Add constraints to the relationship query.
     */
    public function addConstraints(): void;

    /**
     * Add eager loading constraints to the relationship query.
     */
    public function addEagerConstraints(array $models): void;

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, string $relation): array;

    /**
     * Get the relationship name.
     */
    public function getRelationName(): string;

    /**
     * Get the parent model.
     */
    public function getParent(): mixed;

    /**
     * Get the related model.
     */
    public function getRelated(): mixed;

    /**
     * Get the foreign key for the relationship.
     */
    public function getForeignKeyName(): string;

    /**
     * Get the local key for the relationship.
     */
    public function getLocalKeyName(): string;
}
