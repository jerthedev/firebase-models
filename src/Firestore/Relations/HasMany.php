<?php

namespace JTD\FirebaseModels\Firestore\Relations;

use Illuminate\Support\Collection;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder;

/**
 * HasMany relationship for Firestore models.
 * Represents a one-to-many relationship where the related models
 * contain a reference to the parent model.
 */
class HasMany extends Relation
{
    /**
     * Create a new has many relationship instance.
     */
    public function __construct(FirestoreModelQueryBuilder $query, FirestoreModel $parent, string $foreignKey, string $localKey)
    {
        parent::__construct($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults(): Collection
    {
        return $this->query->get();
    }

    /**
     * Add the constraints for a relationship query.
     */
    public function addConstraints(): void
    {
        if (!$this->relationHasConstraints()) {
            return;
        }

        $this->query->where($this->foreignKey, '=', $this->getParentKey());
    }

    /**
     * Add the constraints for a relationship query on an eager load.
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = $this->getKeys($models, $this->localKey);

        if (empty($keys)) {
            // If no keys, add a constraint that will return no results
            $this->query->where($this->foreignKey, '=', '__no_match__');
            return;
        }

        $this->query->whereIn($this->foreignKey, array_unique($keys));
    }

    /**
     * Gather the keys from an array of related models.
     */
    protected function getKeys(array $models, string $key): array
    {
        $keys = [];

        foreach ($models as $model) {
            $value = $model->getAttribute($key);
            if (!is_null($value)) {
                $keys[] = $value;
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
            $model->setRelation($relation, new Collection());
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
            $key = $model->getAttribute($this->localKey);
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, new Collection($dictionary[$key]));
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
            $key = $result->getAttribute($this->foreignKey);
            
            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            
            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the default value for this relation.
     */
    protected function getDefaultFor(FirestoreModel $model): Collection
    {
        return new Collection();
    }

    /**
     * Create a new instance of the related model.
     */
    public function create(array $attributes = []): FirestoreModel
    {
        $attributes[$this->foreignKey] = $this->getParentKey();
        
        return $this->related->newQuery()->create($attributes);
    }

    /**
     * Create a new instance of the related model and save it.
     */
    public function save(FirestoreModel $model): FirestoreModel
    {
        $model->setAttribute($this->foreignKey, $this->getParentKey());
        
        return $model->save() ? $model : false;
    }

    /**
     * Create an array of new instances of the related model.
     */
    public function createMany(array $records): Collection
    {
        $instances = new Collection();

        foreach ($records as $record) {
            $instances->push($this->create($record));
        }

        return $instances;
    }

    /**
     * Save an array of new instances of the related model.
     */
    public function saveMany(array $models): Collection
    {
        $instances = new Collection();

        foreach ($models as $model) {
            $instances->push($this->save($model));
        }

        return $instances;
    }

    /**
     * Find a related model by its primary key.
     */
    public function find(string $id): ?FirestoreModel
    {
        return $this->where($this->related->getKeyName(), '=', $id)->first();
    }

    /**
     * Find multiple related models by their primary keys.
     */
    public function findMany(array $ids): Collection
    {
        return $this->whereIn($this->related->getKeyName(), $ids)->get();
    }

    /**
     * Get the first related model matching the attributes or create it.
     */
    public function firstOrCreate(array $attributes = [], array $values = []): FirestoreModel
    {
        $attributes[$this->foreignKey] = $this->getParentKey();
        
        if ($instance = $this->where($attributes)->first()) {
            return $instance;
        }

        return $this->create(array_merge($attributes, $values));
    }

    /**
     * Get the first related model matching the attributes or instantiate it.
     */
    public function firstOrNew(array $attributes = [], array $values = []): FirestoreModel
    {
        $attributes[$this->foreignKey] = $this->getParentKey();
        
        if ($instance = $this->where($attributes)->first()) {
            return $instance;
        }

        return $this->related->newInstance(array_merge($attributes, $values));
    }

    /**
     * Create or update a related model matching the attributes.
     */
    public function updateOrCreate(array $attributes, array $values = []): FirestoreModel
    {
        $attributes[$this->foreignKey] = $this->getParentKey();
        
        if ($instance = $this->where($attributes)->first()) {
            $instance->fill($values);
            $instance->save();
            return $instance;
        }

        return $this->create(array_merge($attributes, $values));
    }

    /**
     * Update related models.
     */
    public function update(array $attributes): int
    {
        $updated = 0;
        
        foreach ($this->get() as $model) {
            if ($model->fill($attributes)->save()) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Delete related models.
     */
    public function delete(): int
    {
        $deleted = 0;
        
        foreach ($this->get() as $model) {
            if ($model->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get the relationship for eager loading.
     */
    public function getEager(): Collection
    {
        return $this->get();
    }

    /**
     * Add a basic where clause to the query, and return the first result.
     */
    public function whereFirst(string $column, mixed $operator = null, mixed $value = null): ?FirestoreModel
    {
        return $this->where($column, $operator, $value)->first();
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        return $this->query->chunk($count, $callback);
    }

    /**
     * Execute the query and get the first result or throw an exception.
     */
    public function firstOrFail(): FirestoreModel
    {
        if ($model = $this->first()) {
            return $model;
        }

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
    }

    /**
     * Paginate the given query.
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): \Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->query->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Get a paginator only supporting simple next and previous links.
     */
    public function simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): \Illuminate\Pagination\Paginator
    {
        return $this->query->simplePaginate($perPage, $columns, $pageName, $page);
    }
}
