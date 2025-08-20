<?php

namespace JTD\FirebaseModels\Firestore;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Firestore query builder for models.
 * 
 * Extends the base FirestoreQueryBuilder with model-specific functionality
 * like hydrating results into model instances.
 */
class FirestoreModelQueryBuilder extends FirestoreQueryBuilder
{
    /**
     * The model being queried.
     */
    protected FirestoreModel $model;

    /**
     * Create a new Firestore model query builder instance.
     */
    public function __construct(FirestoreQueryBuilder $query, FirestoreModel $model)
    {
        parent::__construct($query->database, $query->collection);
        
        $this->model = $model;
        
        // Copy the state from the base query builder
        $this->wheres = $query->wheres;
        $this->orders = $query->orders;
        $this->limitValue = $query->limitValue;
        $this->offsetValue = $query->offsetValue;
        $this->selects = $query->selects;
        $this->distinct = $query->distinct;
    }

    /**
     * Set the model being queried.
     */
    public function setModel(FirestoreModel $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get the model being queried.
     */
    public function getModel(): FirestoreModel
    {
        return $this->model;
    }

    /**
     * Execute the query and get all results as model instances.
     */
    public function get(array $columns = ['*']): Collection
    {
        $results = parent::get($columns);
        
        return $this->hydrate($results->all());
    }

    /**
     * Get a single result from the query as a model instance.
     */
    public function first(array $columns = ['*']): ?FirestoreModel
    {
        $results = $this->limit(1)->get($columns);

        return $results->first();
    }

    /**
     * Get a single result or throw an exception.
     */
    public function firstOrFail(array $columns = ['*']): FirestoreModel
    {
        $result = $this->first($columns);
        
        if ($result === null) {
            throw new \Illuminate\Database\RecordNotFoundException('No query results for model ['.get_class($this->model).'].');
        }
        
        return $result;
    }

    /**
     * Find a model by its primary key.
     */
    public function find(mixed $id, array $columns = ['*']): ?FirestoreModel
    {
        if (is_array($id) || $id instanceof \Arrayable) {
            return $this->findMany($id, $columns);
        }

        try {
            // Use direct document access for better performance
            $collection = $this->toBase()->database->collection($this->collection);
            $docRef = $collection->document($id);
            $snapshot = $docRef->snapshot();

            if (!$snapshot->exists()) {
                return null;
            }

            $data = $snapshot->data();
            $data['id'] = $snapshot->id();

            return $this->model->newFromBuilder($data);
        } catch (\Exception $e) {
            // Fallback to query-based approach
            return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
        }
    }

    /**
     * Find multiple models by their primary keys.
     */
    public function findMany(array|\Arrayable $ids, array $columns = ['*']): Collection
    {
        $ids = $ids instanceof \Arrayable ? $ids->toArray() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->whereIn($this->model->getKeyName(), $ids)->get($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     */
    public function findOrFail(mixed $id, array $columns = ['*']): FirestoreModel
    {
        $result = $this->find($id, $columns);

        $id = $id instanceof \Arrayable ? $id->toArray() : $id;

        if (is_array($id)) {
            if (count($result) === count(array_unique($id))) {
                return $result;
            }
        } elseif (!is_null($result)) {
            return $result;
        }

        throw new \Illuminate\Database\RecordNotFoundException('No query results for model ['.get_class($this->model).'] '.$id);
    }

    /**
     * Find a model by its primary key or return fresh model instance.
     */
    public function findOrNew(mixed $id, array $columns = ['*']): FirestoreModel
    {
        if (!is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        return $this->newModelInstance();
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     */
    public function firstOrNew(array $attributes = [], array $values = []): FirestoreModel
    {
        if (!is_null($instance = $this->whereAttributes($attributes)->first())) {
            return $instance;
        }

        return $this->newModelInstance(array_merge($attributes, $values));
    }

    /**
     * Get the first record matching the attributes or create it.
     */
    public function firstOrCreate(array $attributes = [], array $values = []): FirestoreModel
    {
        if (!is_null($instance = $this->whereAttributes($attributes)->first())) {
            return $instance;
        }

        return $this->create(array_merge($attributes, $values));
    }

    /**
     * Apply where clauses for multiple attributes.
     */
    protected function whereAttributes(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->where($key, '=', $value);
        }

        return $this;
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     */
    public function updateOrCreate(array $attributes, array $values = []): FirestoreModel
    {
        return tap($this->firstOrNew($attributes), function ($instance) use ($values) {
            $instance->fill($values)->save();
        });
    }

    /**
     * Save a new model and return the instance.
     */
    public function create(array $attributes = []): FirestoreModel
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->save();
        });
    }

    /**
     * Save a new model and return the instance. Allow mass-assignment.
     */
    public function forceCreate(array $attributes): FirestoreModel
    {
        return $this->model->unguarded(function () use ($attributes) {
            return $this->newModelInstance()->create($attributes);
        });
    }

    /**
     * Update records in the database.
     */
    public function update(array $values): int
    {
        $updated = 0;
        
        foreach ($this->get() as $model) {
            if ($model->update($values)) {
                $updated++;
            }
        }
        
        return $updated;
    }

    /**
     * Increment a column's value by a given amount.
     */
    public function increment(string $column, float|int $amount = 1, array $extra = []): int
    {
        return $this->update(array_merge($extra, [$column => $this->raw($column.' + '.$amount)]));
    }

    /**
     * Decrement a column's value by a given amount.
     */
    public function decrement(string $column, float|int $amount = 1, array $extra = []): int
    {
        return $this->update(array_merge($extra, [$column => $this->raw($column.' - '.$amount)]));
    }

    /**
     * Delete records from the database.
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
     * Run the default delete function on the builder.
     */
    public function forceDelete(): int
    {
        return $this->delete();
    }

    /**
     * Paginate the given query.
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);
        
        // Get total count
        $total = $this->toBase()->count();
        
        // Get the items for current page
        $offset = ($page - 1) * $perPage;
        $items = $this->offset($offset)->limit($perPage)->get($columns);
        
        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Paginate the given query into a simple paginator.
     */
    public function simplePaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);
        
        // Get one extra item to determine if there are more pages
        $offset = ($page - 1) * $perPage;
        $items = $this->offset($offset)->limit($perPage + 1)->get($columns);
        
        $hasMorePages = $items->count() > $perPage;
        if ($hasMorePages) {
            $items = $items->slice(0, $perPage);
        }
        
        return new Paginator(
            $items,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
                'hasMorePages' => $hasMorePages,
            ]
        );
    }

    /**
     * Get the hydrated models without eager loading.
     */
    public function getModels(array $columns = ['*']): array
    {
        return $this->hydrate(
            $this->toBase()->get($columns)->all()
        )->all();
    }

    /**
     * Hydrate the given array of attributes into model instances.
     */
    public function hydrate(array $items): Collection
    {
        $instance = $this->newModelInstance();

        return $instance->newCollection(array_map(function ($item) use ($instance) {
            return $instance->newFromBuilder((array) $item);
        }, $items));
    }

    /**
     * Create a new instance of the model being queried.
     */
    public function newModelInstance(array $attributes = []): FirestoreModel
    {
        return $this->model->newInstance($attributes)->setCollection($this->collection);
    }

    /**
     * Get the underlying query builder instance.
     */
    public function toBase(): FirestoreQueryBuilder
    {
        $builder = new FirestoreQueryBuilder($this->database, $this->collection);

        // Copy the current state to the base builder
        $builder->wheres = $this->wheres;
        $builder->orders = $this->orders;
        $builder->limitValue = $this->limitValue;
        $builder->offsetValue = $this->offsetValue;
        $builder->selects = $this->selects;
        $builder->distinct = $this->distinct;

        return $builder;
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;
        
        do {
            $results = $this->offset(($page - 1) * $count)->limit($count)->get();
            
            if ($results->isEmpty()) {
                break;
            }
            
            if ($callback($results, $page) === false) {
                return false;
            }
            
            $page++;
        } while ($results->count() === $count);
        
        return true;
    }

    /**
     * Execute a callback over each item while chunking.
     */
    public function each(callable $callback, int $count = 1000): bool
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
            
            return true;
        });
    }

    /**
     * Get a lazy collection for the given query.
     */
    public function lazy(int $chunkSize = 1000): \Illuminate\Support\LazyCollection
    {
        return \Illuminate\Support\LazyCollection::make(function () use ($chunkSize) {
            $page = 1;
            
            do {
                $results = $this->offset(($page - 1) * $chunkSize)->limit($chunkSize)->get();
                
                foreach ($results as $result) {
                    yield $result;
                }
                
                $page++;
            } while ($results->count() === $chunkSize);
        });
    }

    /**
     * Add a where clause on the primary key to the query.
     */
    public function whereKey(mixed $id): static
    {
        if (is_array($id) || $id instanceof \Arrayable) {
            $this->whereIn($this->model->getKeyName(), $id);

            return $this;
        }

        if ($id !== null && $this->model->getKeyType() === 'string') {
            $id = (string) $id;
        }

        return $this->where($this->model->getKeyName(), '=', $id);
    }

    /**
     * Add a where clause on the primary key to the query.
     */
    public function whereKeyNot(mixed $id): static
    {
        if (is_array($id) || $id instanceof \Arrayable) {
            $this->whereNotIn($this->model->getKeyName(), $id);

            return $this;
        }

        if ($id !== null && $this->model->getKeyType() === 'string') {
            $id = (string) $id;
        }

        return $this->where($this->model->getKeyName(), '!=', $id);
    }

    /**
     * Add a basic where clause to the query, and return the first result.
     */
    public function whereFirst(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): ?FirestoreModel
    {
        return $this->where($column, $operator, $value, $boolean)->first();
    }

    /**
     * Apply the scopes to the Eloquent builder instance and return it.
     */
    public function applyScopes(): static
    {
        if (!$this->scopes) {
            return $this;
        }

        $builder = clone $this;

        foreach ($this->scopes as $identifier => $scope) {
            if (!isset($builder->scopes[$identifier])) {
                continue;
            }

            $builder->callScope(function (FirestoreModelQueryBuilder $builder) use ($scope) {
                // If the scope is a Closure we will call the scope with the builder instance
                // and our model instance so it can apply any logic that it needs to apply
                // to the query. We'll pass both of these instances to the scope closure.
                if ($scope instanceof \Closure) {
                    $scope($builder, $this->model);
                } elseif ($scope instanceof \JTD\FirebaseModels\Firestore\Scopes\ScopeInterface) {
                    $scope->apply($builder, $this->model);
                }
            });
        }

        return $builder;
    }

    /**
     * Apply the given scope on the current builder instance.
     */
    protected function callScope(callable $scope, array $parameters = []): mixed
    {
        array_unshift($parameters, $this);

        $query = $this->getQuery() ?: $this;

        $originalWhereCount = is_null($query->wheres)
                    ? 0 : count($query->wheres);

        $result = $scope(...array_values($parameters)) ?: $this;

        if (count((array) $query->wheres) > $originalWhereCount) {
            $this->addNewWheresWithinGroup($query, $originalWhereCount);
        }

        return $result;
    }

    /**
     * Add a local scope to the query.
     */
    public function scopes(array $scopes): static
    {
        foreach ($scopes as $scope => $parameters) {
            if (is_int($scope)) {
                [$scope, $parameters] = [$parameters, []];
            }

            $this->callNamedScope($scope, (array) $parameters);
        }

        return $this;
    }

    /**
     * Call a local scope on the model.
     */
    protected function callNamedScope(string $scope, array $parameters = []): static
    {
        if ($this->model->hasLocalScope($scope)) {
            $this->model->callScope($scope, array_merge([$this], $parameters));
        }

        return $this;
    }

    /**
     * Create a new query builder without a given scope.
     */
    public function withoutGlobalScope(string|\JTD\FirebaseModels\Firestore\Scopes\ScopeInterface $scope): static
    {
        // For simplicity, just create a new query without global scopes
        // In a production implementation, you'd want more sophisticated scope management
        return $this->model->newQueryWithoutGlobalScopes();
    }

    /**
     * Create a new query builder without any global scopes.
     */
    public function withoutGlobalScopes(?array $scopes = null): static
    {
        // Simple approach: just create a new query without any global scopes
        return $this->model->newQueryWithoutGlobalScopes();
    }

    /**
     * Nest where conditions by adding them to a nested where group.
     */
    protected function addNewWheresWithinGroup(FirestoreQueryBuilder $query, int $originalWhereCount): void
    {
        // Get the new where conditions that were added
        $allWheres = $query->wheres;
        $newWheres = array_slice($allWheres, $originalWhereCount);

        // Remove the new wheres from the query
        $query->wheres = array_slice($allWheres, 0, $originalWhereCount);

        // Add them as a nested group
        $this->groupWhereSlicesForScope($query, $newWheres);
    }

    /**
     * Slice where conditions at the given offset and add them to the query as a nested condition.
     */
    protected function groupWhereSlicesForScope(FirestoreQueryBuilder $query, array $whereSlice): void
    {
        $whereBooleans = collect($whereSlice)->pluck('boolean');

        // If all the where clauses are "and" conditions, we can just add them normally
        if ($whereBooleans->every(fn ($boolean) => $boolean === 'and')) {
            foreach ($whereSlice as $where) {
                $query->wheres[] = $where;
            }
            return;
        }

        // Otherwise, we need to group them
        $query->wheres[] = $this->createNestedWhere($whereSlice, $whereBooleans->first());
    }

    /**
     * Create a nested where condition.
     */
    protected function createNestedWhere(array $whereSlice, string $boolean = 'and'): array
    {
        return [
            'type' => 'nested',
            'query' => $whereSlice,
            'boolean' => $boolean,
        ];
    }

    /**
     * Dynamically handle calls to the query builder.
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Check if it's a local scope
        if ($this->model->hasLocalScope($method)) {
            return $this->callNamedScope($method, $parameters);
        }

        // Check if the method exists on this query builder
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        // Check if it's a method on the parent FirestoreQueryBuilder
        if (method_exists(parent::class, $method)) {
            $result = parent::__call($method, $parameters);

            // If the result is a query builder, return this model query builder
            if ($result instanceof \JTD\FirebaseModels\Firestore\FirestoreQueryBuilder) {
                return $this;
            }

            return $result;
        }

        throw new \BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()', static::class, $method
        ));
    }
}
