<?php

namespace JTD\FirebaseModels\Firestore\Relations;

use Illuminate\Support\Collection;
use JTD\FirebaseModels\Contracts\Relations\RelationInterface;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder;

/**
 * Base class for Firestore model relationships.
 */
abstract class Relation implements RelationInterface
{
    /**
     * The parent model instance.
     */
    protected FirestoreModel $parent;

    /**
     * The related model instance.
     */
    protected FirestoreModel $related;

    /**
     * The query builder for the relation.
     */
    protected FirestoreModelQueryBuilder $query;

    /**
     * The foreign key of the relationship.
     */
    protected string $foreignKey;

    /**
     * The local key of the relationship.
     */
    protected string $localKey;

    /**
     * The relationship name.
     */
    protected string $relationName;

    /**
     * Indicates if the relation is adding constraints.
     */
    protected static bool $constraints = true;

    /**
     * Create a new relation instance.
     */
    public function __construct(FirestoreModelQueryBuilder $query, FirestoreModel $parent, string $foreignKey, string $localKey)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        $this->addConstraints();
    }

    /**
     * Run a callback with constraints disabled on the relation.
     */
    public static function noConstraints(callable $callback): mixed
    {
        $previous = static::$constraints;
        static::$constraints = false;

        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
        }
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults(): mixed
    {
        return $this->query->get();
    }

    /**
     * Execute the relationship query.
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    /**
     * Get the first result of the relationship.
     */
    public function first(): mixed
    {
        return $this->query->first();
    }

    /**
     * Get the relationship name.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Set the relationship name.
     */
    public function setRelationName(string $name): static
    {
        $this->relationName = $name;

        return $this;
    }

    /**
     * Get the parent model.
     */
    public function getParent(): FirestoreModel
    {
        return $this->parent;
    }

    /**
     * Get the related model.
     */
    public function getRelated(): FirestoreModel
    {
        return $this->related;
    }

    /**
     * Get the foreign key for the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the local key for the relationship.
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * Get the query builder for the relationship.
     */
    public function getQuery(): FirestoreModelQueryBuilder
    {
        return $this->query;
    }

    /**
     * Get the parent model's key value.
     */
    public function getParentKey(): mixed
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Determine if the relationship is constrained.
     */
    protected function relationHasConstraints(): bool
    {
        return static::$constraints;
    }

    /**
     * Add a where clause to the relationship query.
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query->where($column, $operator, $value);

        return $this;
    }

    /**
     * Add an or where clause to the relationship query.
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query->orWhere($column, $operator, $value);

        return $this;
    }

    /**
     * Add a where in clause to the relationship query.
     */
    public function whereIn(string $column, array $values): static
    {
        $this->query->whereIn($column, $values);

        return $this;
    }

    /**
     * Add an order by clause to the relationship query.
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * Limit the number of results.
     */
    public function limit(int $limit): static
    {
        $this->query->limit($limit);

        return $this;
    }

    /**
     * Add an offset to the query.
     */
    public function offset(int $offset): static
    {
        $this->query->offset($offset);

        return $this;
    }

    /**
     * Count the number of related models.
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Check if any related models exist.
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Check if no related models exist.
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Get the default value for this relation.
     */
    abstract protected function getDefaultFor(FirestoreModel $model): mixed;

    /**
     * Handle dynamic method calls to the relationship.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->query, $method)) {
            $result = $this->query->{$method}(...$parameters);

            if ($result === $this->query) {
                return $this;
            }

            return $result;
        }

        throw new \BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()',
            static::class,
            $method
        ));
    }
}
