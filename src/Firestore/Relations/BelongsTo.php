<?php

namespace JTD\FirebaseModels\Firestore\Relations;

use Illuminate\Support\Collection;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder;

/**
 * BelongsTo relationship for Firestore models.
 * Represents a many-to-one relationship where the parent model
 * contains a reference to the related model.
 */
class BelongsTo extends Relation
{
    /**
     * The child model instance of the relation.
     */
    protected FirestoreModel $child;

    /**
     * Create a new belongs to relationship instance.
     */
    public function __construct(FirestoreModelQueryBuilder $query, FirestoreModel $child, string $foreignKey, string $ownerKey, string $relationName)
    {
        $this->child = $child;
        
        parent::__construct($query, $child, $foreignKey, $ownerKey);
        
        $this->relationName = $relationName;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults(): ?FirestoreModel
    {
        if (is_null($this->getChildKey())) {
            return null;
        }

        return $this->query->first();
    }

    /**
     * Add the constraints for a relationship query.
     */
    public function addConstraints(): void
    {
        if (!$this->relationHasConstraints()) {
            return;
        }

        $this->query->where($this->localKey, '=', $this->getChildKey());
    }

    /**
     * Add the constraints for a relationship query on an eager load.
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getEagerModelKeys($models);

        if (empty($keys)) {
            // If no keys, add a constraint that will return no results
            $this->query->where($this->localKey, '=', '__no_match__');
            return;
        }

        $this->query->whereIn($this->localKey, array_unique($keys));
    }

    /**
     * Gather the keys from an array of related models.
     */
    protected function getEagerModelKeys(array $models): array
    {
        $keys = [];

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            if (!is_null($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $key = $result->getAttribute($this->localKey);
            $dictionary[$key] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the child model's foreign key value.
     */
    public function getChildKey(): mixed
    {
        return $this->child->getAttribute($this->foreignKey);
    }

    /**
     * Get the default value for this relation.
     */
    protected function getDefaultFor(FirestoreModel $model): mixed
    {
        return null;
    }

    /**
     * Associate the model instance to the given parent.
     */
    public function associate(FirestoreModel $model): FirestoreModel
    {
        $this->child->setAttribute($this->foreignKey, $model->getAttribute($this->localKey));
        
        if ($this->child->isDirty($this->foreignKey)) {
            $this->child->setRelation($this->relationName, $model);
        }

        return $this->child;
    }

    /**
     * Dissociate previously associated model from the given parent.
     */
    public function dissociate(): FirestoreModel
    {
        $this->child->setAttribute($this->foreignKey, null);
        
        return $this->child->setRelation($this->relationName, null);
    }

    /**
     * Update the parent model on the relationship.
     */
    public function update(array $attributes): int
    {
        $instance = $this->getResults();
        
        if ($instance) {
            return $instance->fill($attributes)->save() ? 1 : 0;
        }

        return 0;
    }

    /**
     * Get the child of the relationship.
     */
    public function getChild(): FirestoreModel
    {
        return $this->child;
    }

    /**
     * Get the qualified foreign key on the related model.
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the qualified associated key on the parent model.
     */
    public function getQualifiedOwnerKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * Get the name of the relationship.
     */
    public function getRelationExistenceQuery(FirestoreModelQueryBuilder $query, FirestoreModelQueryBuilder $parentQuery, array $columns = ['*']): FirestoreModelQueryBuilder
    {
        return $query->select($columns)->where($this->localKey, '=', $this->getChildKey());
    }

    /**
     * Determine if the related model has an auto-incrementing ID.
     */
    public function relationHasIncrementingId(): bool
    {
        return false; // Firestore doesn't use auto-incrementing IDs
    }

    /**
     * Make a new related instance for the given model.
     */
    public function newRelatedInstanceFor(FirestoreModel $parent): FirestoreModel
    {
        return $this->related->newInstance();
    }
}
