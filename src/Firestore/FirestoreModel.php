<?php

namespace JTD\FirebaseModels\Firestore;

use Google\Cloud\Firestore\DocumentSnapshot;
use Google\Cloud\Firestore\DocumentReference;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;
use ArrayAccess;
use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Firestore\Concerns\HasAttributes;
use JTD\FirebaseModels\Firestore\Concerns\HasEloquentAccessors;
use JTD\FirebaseModels\Firestore\Concerns\HasScopes;
use JTD\FirebaseModels\Firestore\Concerns\HasTimestamps;
use JTD\FirebaseModels\Firestore\Concerns\HasEvents;
use JTD\FirebaseModels\Firestore\Concerns\GuardsAttributes;
use JTD\FirebaseModels\Firestore\Concerns\HasSyncMode;
use JTD\FirebaseModels\Firestore\Concerns\HasTransactions;
use JTD\FirebaseModels\Firestore\Concerns\HasBatchOperations;
use JTD\FirebaseModels\Firestore\Concerns\HasRelationships;

/**
 * Abstract Firestore Model providing Eloquent-like functionality for Firestore documents.
 * 
 * This class provides a 1:1 compatible API with Laravel's Eloquent Model,
 * adapted for Firestore's document-based architecture.
 */
abstract class FirestoreModel implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    use HasAttributes,
        HasScopes,
        HasTimestamps,
        HasEvents,
        GuardsAttributes,
        HasSyncMode,
        HasTransactions,
        HasBatchOperations,
        HasRelationships {
            HasRelationships::getRelationValue insteadof HasAttributes;
            HasRelationships::getRelationshipFromMethod insteadof HasAttributes;
            HasEvents::fireModelEvent insteadof HasRelationships;
        }

    /**
     * The collection associated with the model.
     */
    protected ?string $collection = null;

    /**
     * The primary key for the model.
     */
    protected string $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     */
    protected string $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public bool $incrementing = false;

    /**
     * Indicates if the model exists in Firestore.
     */
    public bool $exists = false;

    /**
     * Indicates if the model was inserted during the current request lifecycle.
     */
    public bool $wasRecentlyCreated = false;

    /**
     * The model's attributes.
     */
    protected array $attributes = [];

    /**
     * The model attribute's original state.
     */
    protected array $original = [];

    /**
     * The changed model attributes.
     */
    protected array $changes = [];

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     */
    protected array $guarded = ['*'];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in serialization.
     */
    protected array $visible = [];

    /**
     * The accessors to append to the model's array form.
     */
    protected array $appends = [];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [];

    /**
     * The attributes that should be mutated to dates.
     */
    protected array $dates = [];

    /**
     * The storage format of the model's date columns.
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = true;

    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];

    /**
     * The event dispatcher instance.
     */
    protected static ?\Illuminate\Contracts\Events\Dispatcher $dispatcher = null;

    /**
     * The array of booted models.
     */
    protected static array $booted = [];

    /**
     * The array of trait initializers that will be called on each new instance.
     */
    protected static array $traitInitializers = [];

    /**
     * The array of mutated attributes for each class.
     */
    protected static array $mutatorCache = [];

    /**
     * The array of attribute mutator cache.
     */
    protected static array $attributeMutatorCache = [];

    /**
     * The array of class cast cache.
     */
    protected array $classCastCache = [];

    /**
     * The number of models to return per page.
     */
    protected int $perPage = 15;

    /**
     * Indicates whether attributes are snake cased on arrays.
     */
    public static bool $snakeAttributes = true;

    /**
     * The relation resolvers for the model.
     */
    protected static array $relationResolvers = [];

    /**
     * Create a new Firestore model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->initializeTraits();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     */
    protected function bootIfNotBooted(): void
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;
            $this->fireModelEvent('booting', false);
            static::booting();
            static::boot();
            static::booted();
            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * The "booting" method of the model.
     */
    protected static function booting(): void
    {
        //
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        static::bootTraits();
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        //
    }

    /**
     * Boot all of the bootable traits on the model.
     */
    protected static function bootTraits(): void
    {
        $class = static::class;

        $booted = [];

        static::$traitInitializers[$class] = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot'.class_basename($trait);

            if (method_exists($class, $method) && !in_array($method, $booted)) {
                forward_static_call([$class, $method]);

                $booted[] = $method;
            }

            if (method_exists($class, $method = 'initialize'.class_basename($trait))) {
                static::$traitInitializers[$class][] = $method;

                static::$traitInitializers[$class] = array_unique(
                    static::$traitInitializers[$class]
                );
            }
        }
    }

    /**
     * Initialize the traits on the model.
     */
    protected function initializeTraits(): void
    {
        foreach (static::$traitInitializers[static::class] ?? [] as $method) {
            $this->{$method}();
        }
    }

    /**
     * Get the collection name for the model.
     */
    public function getCollection(): string
    {
        return $this->collection ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Get the collection name for the model (static version).
     */
    public static function getCollectionName(): string
    {
        return (new static)->getCollection();
    }

    /**
     * Set the collection associated with the model.
     */
    public function setCollection(string $collection): static
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     */
    public function setKeyName(string $key): static
    {
        $this->primaryKey = $key;
        return $this;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the queueable identity for the entity.
     */
    public function getQueueableId(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get the value of the model's route key.
     */
    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Retrieve the child model for a bound value.
     */
    public function resolveChildRouteBinding($childType, $value, $field): ?static
    {
        return $this->resolveRouteBinding($value, $field);
    }

    /**
     * Begin querying the model.
     */
    public static function query(): FirestoreQueryBuilder
    {
        return (new static)->newQuery();
    }

    /**
     * Get a new query builder for the model's collection.
     */
    public function newQuery(): FirestoreQueryBuilder
    {
        return $this->newModelQuery();
    }

    /**
     * Get a new query builder instance for the connection.
     */
    protected function newBaseQueryBuilder(): FirestoreQueryBuilder
    {
        return FirestoreDB::table($this->getCollection());
    }

    /**
     * Create a new Firestore query builder for the model.
     */
    public function newModelQuery(): FirestoreQueryBuilder
    {
        $builder = $this->newFirestoreQueryBuilder(
            $this->newBaseQueryBuilder()
        )->setModel($this);

        // Apply global scopes
        $this->applyGlobalScopes($builder);

        return $builder;
    }

    /**
     * Create a new Firestore query builder for the model.
     */
    public function newFirestoreQueryBuilder(FirestoreQueryBuilder $query): FirestoreModelQueryBuilder
    {
        return new FirestoreModelQueryBuilder($query, $this);
    }

    /**
     * Create a new instance of the given model.
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);
        $model->exists = $exists;
        $model->setCollection($this->getCollection());
        return $model;
    }

    /**
     * Create a new model instance that is existing.
     */
    public function newFromBuilder(array $attributes = []): static
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes($attributes, true);
        $model->fireModelEvent('retrieved', false);
        return $model;
    }

    /**
     * Save the model to Firestore.
     */
    public function save(array $options = []): bool
    {
        $this->mergeAttributesFromCachedCasts();

        $query = $this->newModelQuery();

        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            $saved = $this->isDirty() ? $this->performUpdate($query) : true;
        } else {
            $saved = $this->performInsert($query);
        }

        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Save the model to Firestore without raising any events.
     */
    public function saveQuietly(array $options = []): bool
    {
        return static::withoutEvents(fn () => $this->save($options));
    }

    /**
     * Perform a model insert operation.
     */
    protected function performInsert(FirestoreQueryBuilder $query): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $attributes = $this->getAttributesForInsert();

        if (empty($attributes)) {
            return true;
        }

        if ($this->getIncrementing()) {
            $this->insertAndSetId($query, $attributes);
        } else {
            if (empty($this->getKey())) {
                $this->insertAndSetId($query, $attributes);
            } else {
                // Use insertWithId for documents with specific IDs
                $query->insertWithId($this->getKey(), $attributes);
            }
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     */
    protected function insertAndSetId(FirestoreQueryBuilder $query, array $attributes): void
    {
        $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());
        $this->setAttribute($keyName, $id);
    }

    /**
     * Get the attributes that should be converted to arrays for insert.
     */
    protected function getAttributesForInsert(): array
    {
        return $this->getAttributes();
    }

    /**
     * Perform a model update operation.
     */
    protected function performUpdate(FirestoreQueryBuilder $query): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirtyForUpdate();

        if (count($dirty) > 0) {
            $this->setKeysForSaveQuery($query)->update($dirty);
            $this->syncChanges();
            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Get the attributes that have been changed since last sync.
     */
    protected function getDirtyForUpdate(): array
    {
        return $this->getDirty();
    }

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery(FirestoreQueryBuilder $query): FirestoreQueryBuilder
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());
        return $query;
    }

    /**
     * Get the primary key value for a save query.
     */
    protected function getKeyForSaveQuery(): mixed
    {
        return $this->original[$this->getKeyName()] ?? $this->getKey();
    }

    /**
     * Finish processing on a successful save operation.
     */
    protected function finishSave(array $options): void
    {
        $this->fireModelEvent('saved', false);
        
        if ($this->isDirty() && ($options['touch'] ?? true)) {
            $this->touchOwners();
        }

        $this->syncOriginal();
    }

    /**
     * Touch the owning relations of the model.
     */
    protected function touchOwners(): void
    {
        // Implementation for touching related models
        // This will be expanded in relationship features
    }

    /**
     * Update the model's update timestamp.
     */
    public function touch(): bool
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Delete the model from Firestore.
     */
    public function delete(): ?bool
    {
        if (is_null($this->getKeyName())) {
            throw new \Exception('No primary key defined on model.');
        }

        if (!$this->exists) {
            return null;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->performDeleteOnModel();

        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * Delete the model from Firestore without raising any events.
     */
    public function deleteQuietly(): ?bool
    {
        return static::withoutEvents(fn () => $this->delete());
    }

    /**
     * Perform the actual delete query on this model instance.
     */
    protected function performDeleteOnModel(): void
    {
        $this->setKeysForSaveQuery($this->newModelQuery())->delete();
        $this->exists = false;
    }

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)).'_'.$this->getKeyName();
    }

    /**
     * Get the number of models to return per page.
     */
    public function getPerPage(): int
    {
        return $this->perPage ?? 15;
    }

    /**
     * Set the number of models to return per page.
     */
    public function setPerPage(int $perPage): static
    {
        $this->perPage = $perPage;
        return $this;
    }

    // Static query methods that delegate to the query builder
    public static function all(array $columns = ['*']): Collection
    {
        return static::query()->get($columns);
    }

    public static function find(mixed $id, array $columns = ['*']): ?static
    {
        return static::query()->find($id, $columns);
    }

    public static function findOrFail(mixed $id, array $columns = ['*']): static
    {
        return static::query()->findOrFail($id, $columns);
    }

    public static function findMany(array $ids, array $columns = ['*']): Collection
    {
        return static::query()->findMany($ids, $columns);
    }

    public static function first(array $columns = ['*']): ?static
    {
        return static::query()->first($columns);
    }

    public static function firstOrFail(array $columns = ['*']): static
    {
        return static::query()->firstOrFail($columns);
    }

    public static function firstOrNew(array $attributes = [], array $values = []): static
    {
        return static::query()->firstOrNew($attributes, $values);
    }

    public static function firstOrCreate(array $attributes = [], array $values = []): static
    {
        return static::query()->firstOrCreate($attributes, $values);
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        return static::query()->updateOrCreate($attributes, $values);
    }

    public static function create(array $attributes = []): static
    {
        return static::query()->create($attributes);
    }

    public static function forceCreate(array $attributes): static
    {
        return static::query()->forceCreate($attributes);
    }

    public static function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): FirestoreQueryBuilder
    {
        return static::query()->where($column, $operator, $value, $boolean);
    }

    public static function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): FirestoreQueryBuilder
    {
        return static::query()->whereIn($column, $values, $boolean, $not);
    }

    public static function whereNotIn(string $column, array $values, string $boolean = 'and'): FirestoreQueryBuilder
    {
        return static::query()->whereNotIn($column, $values, $boolean);
    }

    public static function orderBy(string $column, string $direction = 'asc'): FirestoreQueryBuilder
    {
        return static::query()->orderBy($column, $direction);
    }

    public static function limit(int $value): FirestoreQueryBuilder
    {
        return static::query()->limit($value);
    }

    public static function take(int $value): FirestoreQueryBuilder
    {
        return static::query()->take($value);
    }

    // ArrayAccess implementation
    public function offsetExists(mixed $offset): bool
    {
        return !is_null($this->getAttribute($offset));
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset], $this->relations[$offset]);
    }

    // Arrayable implementation
    public function toArray(): array
    {
        return array_merge($this->attributesToArray(), $this->relationsToArray());
    }

    // Jsonable implementation
    public function toJson($options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException(json_last_error_msg());
        }

        return $json;
    }

    // JsonSerializable implementation
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the model instance to a string.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     */
    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     */
    public function __unset(string $key): void
    {
        $this->offsetUnset($key);
    }

    /**
     * Handle dynamic static method calls into the model.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Handle dynamic method calls into the model.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        if ($resolver = $this->relationResolver(static::class, $method)) {
            return $resolver($this);
        }

        // Check if it's a local scope
        if ($this->hasLocalScope($method)) {
            return $this->newQuery()->$method(...$parameters);
        }

        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    /**
     * Forward a method call to the given object.
     */
    protected function forwardCallTo(object $object, string $method, array $parameters): mixed
    {
        try {
            return $object->{$method}(...$parameters);
        } catch (\Error|\BadMethodCallException $e) {
            $pattern = '~^Call to undefined method (?P<class>[^:]+)::(?P<method>[^\(]+)\(\)$~';

            if (!preg_match($pattern, $e->getMessage(), $matches)) {
                throw $e;
            }

            if ($matches['class'] != get_class($object) ||
                $matches['method'] != $method) {
                throw $e;
            }

            static::throwBadMethodCallException($method);
        }
    }

    /**
     * Determine if two models have the same ID and belong to the same collection.
     */
    public function is(?FirestoreModel $model): bool
    {
        return !is_null($model) &&
               $this->getKey() === $model->getKey() &&
               $this->getCollection() === $model->getCollection() &&
               get_class($this) === get_class($model);
    }

    /**
     * Throw a bad method call exception for the given method.
     */
    protected static function throwBadMethodCallException(string $method): void
    {
        throw new \BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()', static::class, $method
        ));
    }

    /**
     * Get the incrementing property for the model.
     */
    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    /**
     * Set whether IDs are incrementing.
     */
    public function setIncrementing(bool $value): static
    {
        $this->incrementing = $value;
        return $this;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * Set the data type for the primary key.
     */
    public function setKeyType(string $type): static
    {
        $this->keyType = $type;
        return $this;
    }

    /**
     * Create a new collection instance.
     */
    public function newCollection(array $models = []): Collection
    {
        return new Collection($models);
    }

    /**
     * Determine if a relationship is loaded.
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
     * Get the relationships that are touched on save.
     */
    public function getTouchedRelations(): array
    {
        return $this->touches ?? [];
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
     * Convert the model's attributes to an array.
     */
    public function relationsToArray(): array
    {
        $attributes = [];

        foreach ($this->getArrayableRelations() as $key => $value) {
            // If the values implements the Arrayable interface we can just call this
            // toArray method on the instances which will convert both models and
            // collections to their proper array form and we'll set the values.
            if ($value instanceof Arrayable) {
                $relation = $value->toArray();
            }

            // If the value is null, we'll still go ahead and set it in this list of
            // attributes since null is used to represent empty relationships if
            // if it a has one or belongs to type relationships on the models.
            elseif (is_null($value)) {
                $relation = $value;
            }

            // If the relationships snake-casing is enabled, we will snake case this
            // key so that the relation attribute is snake cased in this returned
            // array to the developers, making this consistent with attributes.
            if (static::$snakeAttributes) {
                $key = Str::snake($key);
            }

            // If the relation value has been set, we will set it on this attributes
            // list for returning. If it was not arrayable or null, we'll not set
            // the value on the array because it is some type of invalid value.
            if (isset($relation) || is_null($value)) {
                $attributes[$key] = $relation;
            }

            unset($relation);
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable relations.
     */
    protected function getArrayableRelations(): array
    {
        return $this->getArrayableItems($this->relations);
    }

    /**
     * Get the mutated attributes for a given instance.
     */
    public function getMutatedAttributes(): array
    {
        $class = static::class;

        if (!isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     */
    public static function cacheMutatedAttributes(string $class): void
    {
        static::$mutatorCache[$class] = collect(static::getMutatorMethods($class))->map(function ($match) {
            return lcfirst(static::$snakeAttributes ? Str::snake($match) : $match);
        })->all();
    }

    /**
     * Get all of the attribute mutator methods.
     */
    protected static function getMutatorMethods(string $class): array
    {
        preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches);

        return $matches[1];
    }

    /**
     * Get the relation resolver callback.
     */
    public function relationResolver(string $class, string $key): ?\Closure
    {
        return static::$relationResolvers[$class][$key] ?? null;
    }

    /**
     * Merge the cast class attributes back into the model.
     */
    protected function mergeAttributesFromCachedCasts(): void
    {
        $this->mergeAttributesFromClassCasts();
    }

    /**
     * Merge the cast class and attribute cast attributes back into the model.
     */
    protected function mergeAttributesFromClassCasts(): void
    {
        foreach ($this->classCastCache as $key => $value) {
            $caster = $this->resolveCasterClass($key);

            $this->attributes = array_merge(
                $this->attributes,
                $caster instanceof CastsInboundAttributes
                    ? [$key => $value]
                    : $this->normalizeCastClassResponse($key, $caster->set($this, $key, $value, $this->attributes))
            );
        }
    }

    /**
     * Normalize the response from a custom class caster.
     */
    protected function normalizeCastClassResponse(string $key, mixed $value): array
    {
        return is_array($value) ? $value : [$key => $value];
    }

    /**
     * Parse the given caster class, removing any arguments.
     */
    protected function parseCasterClass(string $class): string
    {
        return strpos($class, ':') !== false
            ? explode(':', $class, 2)[0]
            : $class;
    }

    /**
     * Get the caster class for the given key.
     */
    protected function resolveCasterClass(string $key): mixed
    {
        $castType = $this->getCasts()[$key];

        $arguments = [];

        if (is_string($castType) && strpos($castType, ':') !== false) {
            $segments = explode(':', $castType, 2);

            $castType = $segments[0];
            $arguments = explode(',', $segments[1]);
        }

        // For now, we'll handle basic casting without the Laravel casting interfaces
        // These interfaces will be implemented in a future version

        if (class_exists($castType)) {
            return new $castType(...$arguments);
        }

        throw new \InvalidArgumentException("Class [{$castType}] does not exist.");
    }

    /**
     * Clear all static caches to prevent memory leaks during testing.
     * This method should only be called during testing.
     */
    public static function clearStaticCaches(): void
    {
        static::$booted = [];
        static::$traitInitializers = [];
        static::$mutatorCache = [];
        static::$attributeMutatorCache = [];
        static::$relationResolvers = [];
    }
}
