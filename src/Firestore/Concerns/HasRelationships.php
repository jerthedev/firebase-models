<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JTD\FirebaseModels\Firestore\Relations\BelongsTo;
use JTD\FirebaseModels\Firestore\Relations\HasMany;
use JTD\FirebaseModels\Firestore\Relations\Relation;
use JTD\FirebaseModels\Firestore\FirestoreModel;

/**
 * Trait for handling relationships in Firestore models.
 */
trait HasRelationships
{
    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];

    /**
     * The relationships that should be touched on save.
     */
    protected array $touches = [];

    /**
     * Define a one-to-many relationship.
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany(
            $instance->newQuery(), $this, $foreignKey, $localKey
        );
    }

    /**
     * Create a new has many relationship instance.
     */
    protected function newHasMany(
        \JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder $query,
        FirestoreModel $parent,
        string $foreignKey,
        string $localKey
    ): HasMany {
        return new HasMany($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) . '_' . $instance->getKeyName();
        }

        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsTo(
            $instance->newQuery(), $this, $foreignKey, $ownerKey, $relation
        );
    }

    /**
     * Create a new belongs to relationship instance.
     */
    protected function newBelongsTo(
        \JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder $query,
        FirestoreModel $child,
        string $foreignKey,
        string $ownerKey,
        string $relation
    ): BelongsTo {
        return new BelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Guess the "belongs to" relationship name.
     */
    protected function guessBelongsToRelation(): string
    {
        [$one, $two, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }

    /**
     * Create a new model instance for a related model.
     */
    protected function newRelatedInstance(string $class): FirestoreModel
    {
        return tap(new $class, function ($instance) {
            if (!$instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)) . '_' . $this->getKeyName();
    }

    /**
     * Get a relationship value from a method.
     */
    public function getRelationValue(string $key): mixed
    {
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * Get a relationship from a method on the model.
     */
    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        if (!$relation instanceof Relation) {
            if (is_null($relation)) {
                throw new \LogicException(sprintf(
                    '%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?',
                    static::class, $method
                ));
            }

            throw new \LogicException(sprintf(
                '%s::%s must return a relationship instance.',
                static::class, $method
            ));
        }

        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Set the given relationship on the model.
     */
    public function setRelation(string $relation, mixed $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Unset a loaded relationship.
     */
    public function unsetRelation(string $relation): static
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Get all the loaded relations for the instance.
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Set the entire relations array on the model.
     */
    public function setRelations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Duplicate the instance and unset all the loaded relations.
     */
    public function withoutRelations(): static
    {
        $model = clone $this;

        return $model->unsetRelations();
    }

    /**
     * Unset all the loaded relations for the instance.
     */
    public function unsetRelations(): static
    {
        $this->relations = [];

        return $this;
    }

    /**
     * Touch the owning relations of the model.
     */
    public function touchOwners(): void
    {
        foreach ($this->touches as $relation) {
            $this->$relation()->touch();

            if ($this->$relation instanceof Collection) {
                $this->$relation->each->touch();
            } elseif ($this->$relation !== null) {
                $this->$relation->touch();
            }
        }
    }

    /**
     * Determine if the model touches a given relation.
     */
    public function touches(string $relation): bool
    {
        return in_array($relation, $this->touches);
    }

    /**
     * Fire the given event for the model relationship.
     */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        if (!isset(static::$dispatcher)) {
            return true;
        }

        // Fire the native model event
        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $halt)
        );

        if ($result === false) {
            return false;
        }

        return empty($result) ? true : $result;
    }

    /**
     * Get the relationships that are touched on save.
     */
    public function getTouchedRelations(): array
    {
        return $this->touches;
    }

    /**
     * Set the relationships that are touched on save.
     */
    public function setTouchedRelations(array $touches): static
    {
        $this->touches = $touches;

        return $this;
    }

    /**
     * Load a set of relationships onto the model.
     */
    public function load(array|string $relations): static
    {
        $query = $this->newQueryWithoutRelationships();

        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $query->with($relations)->eagerLoadRelations([$this]);

        return $this;
    }

    /**
     * Load a set of relationships onto the collection if they are not already eager loaded.
     */
    public function loadMissing(array|string $relations): static
    {
        if (is_string($relations)) {
            $relations = func_get_args();
        }

        $this->newCollection([$this])->loadMissing($relations);

        return $this;
    }

    /**
     * Increment a column's value by a given amount.
     */
    public function increment(string $column, float|int $amount = 1, array $extra = []): int
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'increment');
    }

    /**
     * Decrement a column's value by a given amount.
     */
    public function decrement(string $column, float|int $amount = 1, array $extra = []): int
    {
        return $this->incrementOrDecrement($column, $amount, $extra, 'decrement');
    }

    /**
     * Run the increment or decrement method on the model.
     */
    protected function incrementOrDecrement(string $column, float|int $amount, array $extra, string $method): int
    {
        $query = $this->newQueryWithoutRelationships();

        if (!$this->exists) {
            return $query->{$method}($column, $amount, $extra);
        }

        $this->incrementOrDecrementAttributeValue($column, $amount, $extra, $method);

        return $this->save() ? 1 : 0;
    }

    /**
     * Increment or decrement the given attribute value.
     */
    protected function incrementOrDecrementAttributeValue(string $column, float|int $amount, array $extra, string $method): void
    {
        $this->{$column} = $this->{$column} + ($method === 'increment' ? $amount : $amount * -1);

        $this->fill($extra);
    }
}
