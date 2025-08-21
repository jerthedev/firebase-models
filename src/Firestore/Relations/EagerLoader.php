<?php

namespace JTD\FirebaseModels\Firestore\Relations;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JTD\FirebaseModels\Firestore\FirestoreModel;

/**
 * Eager loader for Firestore model relationships.
 */
class EagerLoader
{
    /**
     * The relationships being eager loaded.
     */
    protected array $eagerLoad = [];

    /**
     * Create a new eager loader instance.
     */
    public function __construct(array $eagerLoad = [])
    {
        $this->eagerLoad = $this->parseWithRelations($eagerLoad);
    }

    /**
     * Parse a list of relations into individuals.
     */
    protected function parseWithRelations(array $relations): array
    {
        $results = [];

        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $results = $this->addNestedWiths($name, $results);

            if ($constraints) {
                $results[$name] = $constraints;
            }
        }

        return $results;
    }

    /**
     * Parse the nested relationships in a relation.
     */
    protected function addNestedWiths(string $name, array $results): array
    {
        $progress = [];

        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;

            if (!isset($results[$last = implode('.', $progress)])) {
                $results[$last] = null;
            }
        }

        return $results;
    }

    /**
     * Eager load the relationships for the models.
     */
    public function load(Collection $models): Collection
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            if (strpos($name, '.') === false) {
                $models = $this->eagerLoadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     */
    protected function eagerLoadRelation(Collection $models, string $name, ?\Closure $constraints): Collection
    {
        $relation = $this->getRelation($models, $name);

        $relation->addEagerConstraints($models->all());

        if ($constraints) {
            $constraints($relation);
        }

        return $this->match($models, $relation->getEager(), $name);
    }

    /**
     * Get the relation instance for the given relation name.
     */
    protected function getRelation(Collection $models, string $name): Relation
    {
        $model = $models->first();

        $relation = Relation::noConstraints(function () use ($model, $name) {
            try {
                return $model->getRelationValue($name);
            } catch (\BadMethodCallException $e) {
                throw new \InvalidArgumentException("Relation [{$name}] does not exist on model [" . get_class($model) . "].");
            }
        });

        if (!$relation instanceof Relation) {
            $relation = $model->$name();
        }

        $nested = $this->relationsNestedUnder($name);

        if (count($nested) > 0) {
            $relation->getQuery()->with($nested);
        }

        return $relation;
    }

    /**
     * Get the deeply nested relations for a given top-level relation.
     */
    protected function relationsNestedUnder(string $relation): array
    {
        $nested = [];

        foreach ($this->eagerLoad as $name => $constraints) {
            if ($this->isNestedUnder($relation, $name)) {
                $nested[substr($name, strlen($relation) + 1)] = $constraints;
            }
        }

        return $nested;
    }

    /**
     * Determine if the given relation is nested.
     */
    protected function isNestedUnder(string $relation, string $name): bool
    {
        return Str::contains($name, '.') && Str::startsWith($name, $relation . '.');
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    protected function match(Collection $models, Collection $results, string $name): Collection
    {
        $relation = $this->getRelation($models, $name);

        return $relation->match($models->all(), $results, $name);
    }

    /**
     * Get the relationship count query.
     */
    public function loadCount(Collection $models, array $relations): Collection
    {
        foreach ($relations as $name => $constraints) {
            if (is_numeric($name)) {
                $name = $constraints;
                $constraints = null;
            }

            $models = $this->loadRelationCount($models, $name, $constraints);
        }

        return $models;
    }

    /**
     * Load the relationship count for the given models.
     */
    protected function loadRelationCount(Collection $models, string $relation, ?\Closure $constraints): Collection
    {
        $model = $models->first();

        $relationInstance = $model->$relation();

        $relationInstance->addEagerConstraints($models->all());

        if ($constraints) {
            $constraints($relationInstance);
        }

        $counts = $this->getRelationCounts($relationInstance, $models, $relation);

        foreach ($models as $model) {
            $key = $model->getAttribute($relationInstance->getLocalKeyName());
            $model->setAttribute($relation . '_count', $counts[$key] ?? 0);
        }

        return $models;
    }

    /**
     * Get the relationship counts.
     */
    protected function getRelationCounts(Relation $relation, Collection $models, string $relationName): array
    {
        $counts = [];

        // For HasMany relationships
        if ($relation instanceof HasMany) {
            $results = $relation->getQuery()->get();
            
            foreach ($results as $result) {
                $key = $result->getAttribute($relation->getForeignKeyName());
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        // For BelongsTo relationships
        if ($relation instanceof BelongsTo) {
            foreach ($models as $model) {
                $key = $model->getAttribute($relation->getLocalKeyName());
                $foreignKey = $model->getAttribute($relation->getForeignKeyName());
                $counts[$key] = $foreignKey ? 1 : 0;
            }
        }

        return $counts;
    }

    /**
     * Add eager loading constraints to the query.
     */
    public function applyConstraints(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder $query): void
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            if ($constraints instanceof \Closure) {
                $query->with([$name => $constraints]);
            } else {
                $query->with($name);
            }
        }
    }

    /**
     * Get the eager load array.
     */
    public function getEagerLoads(): array
    {
        return $this->eagerLoad;
    }

    /**
     * Set the eager load array.
     */
    public function setEagerLoads(array $eagerLoad): static
    {
        $this->eagerLoad = $this->parseWithRelations($eagerLoad);

        return $this;
    }

    /**
     * Add a relationship to the eager load array.
     */
    public function with(array|string $relations): static
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $this->eagerLoad = array_merge($this->eagerLoad, $this->parseWithRelations($relations));

        return $this;
    }

    /**
     * Remove a relationship from the eager load array.
     */
    public function without(array|string $relations): static
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        foreach ($relations as $relation) {
            unset($this->eagerLoad[$relation]);
        }

        return $this;
    }

    /**
     * Check if a relationship is being eager loaded.
     */
    public function isEagerLoading(string $relation): bool
    {
        return array_key_exists($relation, $this->eagerLoad);
    }
}
