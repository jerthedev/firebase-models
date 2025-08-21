<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Google\Cloud\Firestore\Timestamp;

/**
 * Trait for handling model attributes, casting, and serialization.
 */
trait HasAttributes
{
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
     * The built-in, primitive cast types supported.
     */
    protected static array $primitiveCastTypes = [
        'array', 'bool', 'boolean', 'collection', 'custom_datetime', 'date', 'datetime',
        'decimal', 'double', 'encrypted', 'encrypted:array', 'encrypted:collection',
        'encrypted:json', 'encrypted:object', 'float', 'hashed', 'int', 'integer',
        'json', 'object', 'real', 'string', 'timestamp',
    ];

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key): mixed
    {
        if (!$key) {
            return null;
        }

        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship's value. This covers both types of values.
        if (array_key_exists($key, $this->attributes) ||
            array_key_exists($key, $this->casts) ||
            $this->hasGetMutator($key) ||
            $this->hasAttributeMutator($key)) {
            return $this->getAttributeValue($key);
        }

        // Here we will determine if the model base class itself contains this given key
        // since we don't want to treat any of those methods as relationships because
        // they are all intended as helper methods and none of these are relations.
        if (method_exists(self::class, $key)) {
            return null;
        }

        return $this->getRelationValue($key);
    }

    /**
     * Get a plain attribute (not a relationship).
     */
    public function getAttributeValue(string $key): mixed
    {
        return $this->transformModelValue($key, $this->getAttributeFromArray($key));
    }

    /**
     * Get an attribute from the $attributes array.
     */
    protected function getAttributeFromArray(string $key): mixed
    {
        return $this->getAttributes()[$key] ?? null;
    }

    /**
     * Get a relationship value from a method.
     */
    public function getRelationValue(string $key): mixed
    {
        // If the key already exists in the relationships array, it just means the
        // relationship has already been loaded, so we'll just return it out of
        // here because there is no need to query within the relations twice.
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        // If the "attribute" exists as a method on the model, we will just assume
        // it is a relationship and will load and return results from the query
        // and hydrate the relationship's value on the "relationships" array.
        if (method_exists($this, $key) ||
            (static::$relationResolvers[get_class($this)][$key] ?? null)) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * Get a relationship value from a method.
     */
    protected function getRelationshipFromMethod(string $method): mixed
    {
        $relation = $this->$method();

        if (!$relation instanceof Relation) {
            if (is_null($relation)) {
                throw new LogicException(sprintf(
                    '%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?',
                    static::class,
                    $method
                ));
            }

            throw new LogicException(sprintf(
                '%s::%s must return a relationship instance.',
                static::class,
                $method
            ));
        }

        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    /**
     * Determine if the given attribute exists.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Set a given attribute on the model.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // this model, such as "json_encoding" a listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        elseif ($value && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isClassCastable($key)) {
            $this->setClassCastableAttribute($key, $value);

            return $this;
        }

        if (!is_null($value) && $this->isJsonCastable($key)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (Str::contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        if (!is_null($value) && $this->isEncryptedCastable($key)) {
            $value = $this->castAttributeAsEncryptedString($key, $value);
        }

        // Cast primitive types during setting for proper dirty tracking
        if ($this->hasCast($key) && in_array($this->getCastType($key), static::$primitiveCastTypes)) {
            $value = $this->castAttribute($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     */
    public function hasSetMutator(string $key): bool
    {
        return $this->hasLegacySetMutator($key) || $this->hasAttributeMutator($key);
    }

    /**
     * Determine if a legacy set mutator exists for an attribute.
     */
    public function hasLegacySetMutator(string $key): bool
    {
        return method_exists($this, 'set'.Str::studly($key).'Attribute');
    }



    /**
     * Set the value of an attribute using its mutator.
     */
    protected function setMutatedAttributeValue(string $key, mixed $value): static
    {
        // Try legacy mutator first
        if ($this->hasLegacySetMutator($key)) {
            $this->{'set'.Str::studly($key).'Attribute'}($value);
            return $this;
        }

        // Try attribute mutator
        if ($this->hasAttributeMutator($key)) {
            $this->setAttributeMutatorValue($key, $value);
            return $this;
        }

        // Fallback to direct assignment
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Set the value using an attribute mutator.
     */
    protected function setAttributeMutatorValue(string $key, mixed $value): void
    {
        $method = Str::camel($key);

        if (!method_exists($this, $method)) {
            $this->attributes[$key] = $value;
            return;
        }

        // Get the Attribute object
        $attribute = $this->$method();

        if (!$attribute instanceof Attribute) {
            $this->attributes[$key] = $value;
            return;
        }

        // Call the set method if it exists
        $set = $attribute->set;

        if ($set instanceof \Closure) {
            $result = $set($value, $this->attributes);

            // If the setter returns an array, merge it with attributes
            if (is_array($result)) {
                foreach ($result as $k => $v) {
                    $this->attributes[$k] = $v;
                }
            } else {
                $this->attributes[$key] = $result;
            }
        } else {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Create an Attribute object for modern accessor/mutator definition.
     */
    public static function make(?callable $get = null, ?callable $set = null): Attribute
    {
        return new Attribute($get, $set);
    }

    /**
     * Set multiple attributes using mutators.
     */
    public function setMutatedAttributes(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->hasSetMutator($key)) {
                $this->setMutatedAttributeValue($key, $value);
            } else {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Get the original value of a mutated attribute.
     */
    public function getOriginalMutatedValue(string $key): mixed
    {
        return $this->original[$key] ?? null;
    }

    /**
     * Determine if a mutated attribute has changed.
     */
    public function mutatedAttributeHasChanged(string $key): bool
    {
        if (!$this->hasSetMutator($key)) {
            return false;
        }

        $current = $this->attributes[$key] ?? null;
        $original = $this->original[$key] ?? null;

        return $current !== $original;
    }

    /**
     * Get all changed mutated attributes.
     */
    public function getChangedMutatedAttributes(): array
    {
        $changed = [];

        // Check all current attributes to see if they have mutators and have changed
        foreach (array_keys($this->attributes) as $key) {
            if ($this->hasSetMutator($key) && $this->mutatedAttributeHasChanged($key)) {
                $changed[$key] = $this->attributes[$key] ?? null;
            }
        }

        return $changed;
    }

    /**
     * Clear the mutator cache for a class.
     */
    public static function clearMutatorCache(?string $class = null): void
    {
        if ($class) {
            unset(static::$mutatorCache[$class]);
        } else {
            static::$mutatorCache = [];
        }
    }

    /**
     * Get all cached mutators.
     */
    public static function getCachedMutators(): array
    {
        return [
            'legacy' => static::$mutatorCache,
            'modern' => [], // Modern mutators are detected on-demand
        ];
    }

    /**
     * Reset all mutated attributes to their original values.
     */
    public function resetMutatedAttributes(): static
    {
        // Check all current attributes to see if they have mutators
        foreach (array_keys($this->attributes) as $key) {
            if ($this->hasSetMutator($key)) {
                if (array_key_exists($key, $this->original)) {
                    $this->attributes[$key] = $this->original[$key];
                } else {
                    unset($this->attributes[$key]);
                }
            }
        }

        return $this;
    }

    /**
     * Determine if the given attribute is a date or date castable.
     */
    protected function isDateAttribute(string $key): bool
    {
        return in_array($key, $this->getDates(), true) ||
               $this->isDateCastable($key);
    }

    /**
     * Get the attributes that should be converted to dates.
     */
    public function getDates(): array
    {
        $defaults = [static::CREATED_AT, static::UPDATED_AT];

        return $this->usesTimestamps() ? array_unique(array_merge($this->dates, $defaults)) : $this->dates;
    }

    /**
     * Convert a DateTime to a storable string.
     */
    public function fromDateTime(mixed $value): ?string
    {
        return empty($value) ? $value : $this->asDateTime($value)->format(
            $this->getDateFormat()
        );
    }

    /**
     * Return a boolean value.
     */
    protected function asBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['1', 'true', 'yes', 'on'], true);
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return (bool) $value;
    }

    /**
     * Return a timestamp as DateTime object.
     */
    protected function asDateTime(mixed $value): Carbon
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon) {
            return $value;
        }

        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse(
                $value->format('Y-m-d H:i:s.u'), $value->getTimezone()
            );
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return Carbon::instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }

        $format = $this->getDateFormat();

        // https://bugs.php.net/bug.php?id=75577
        if (version_compare(PHP_VERSION, '7.3.0-dev', '<')) {
            $format = str_replace('.v', '.u', $format);
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        try {
            $date = Carbon::createFromFormat($format, $value);
        } catch (\InvalidArgumentException $e) {
            $date = false;
        }

        return $date ?: Carbon::parse($value);
    }

    /**
     * Determine if the given value is a standard date format.
     */
    protected function isStandardDateFormat(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Get the format for database stored dates.
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    /**
     * Set the date format used by the model.
     */
    public function setDateFormat(string $format): static
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     */
    public function hasCast(string $key, array|string|null $types = null): bool
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * Get the casts array.
     */
    public function getCasts(): array
    {
        if ($this->getIncrementing()) {
            return array_merge([$this->getKeyName() => $this->getKeyType()], $this->casts);
        }

        return $this->casts;
    }

    /**
     * Determine whether a value is Date / DateTime castable for inbound manipulation.
     */
    protected function isDateCastable(string $key): bool
    {
        return $this->hasCast($key, ['date', 'datetime', 'immutable_date', 'immutable_datetime']);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     */
    protected function isJsonCastable(string $key): bool
    {
        return $this->hasCast($key, ['array', 'json', 'object', 'collection']);
    }

    /**
     * Determine if the given key is cast using a custom class.
     */
    protected function isClassCastable(string $key): bool
    {
        $casts = $this->getCasts();

        if (!array_key_exists($key, $casts)) {
            return false;
        }

        $castType = $this->parseCasterClass($casts[$key]);

        if (in_array($castType, static::$primitiveCastTypes)) {
            return false;
        }

        if (class_exists($castType)) {
            return true;
        }

        throw new \InvalidArgumentException("Class [{$castType}] does not exist.");
    }

    /**
     * Determine whether a value is an encrypted castable for inbound manipulation.
     */
    protected function isEncryptedCastable(string $key): bool
    {
        return $this->hasCast($key, ['encrypted', 'encrypted:array', 'encrypted:collection', 'encrypted:json', 'encrypted:object']);
    }

    /**
     * Cast an attribute to a native PHP type.
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        $castType = $this->getCastType($key);

        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return $value;
        }

        // Handle Firestore Timestamp objects
        if ($value instanceof Timestamp) {
            $value = $value->get();
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return $this->asBoolean($value);
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new Collection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'immutable_date':
                return $this->asDate($value)->toImmutable();
            case 'immutable_custom_datetime':
            case 'immutable_datetime':
                return $this->asDateTime($value)->toImmutable();
            case 'timestamp':
                return $this->asTimestamp($value);
        }

        if ($this->isClassCastable($key)) {
            return $this->getClassCastableAttributeValue($key, $value);
        }

        return $value;
    }

    /**
     * Cast the given attribute using a custom cast class.
     */
    protected function getClassCastableAttributeValue(string $key, mixed $value): mixed
    {
        $caster = $this->resolveCasterClass($key);

        $objectCachingDisabled = $caster instanceof CastsInboundAttributes ||
                                $caster instanceof CastsAttributes && $caster->withoutObjectCaching;

        if (isset($this->classCastCache[$key]) && !$objectCachingDisabled) {
            return $this->classCastCache[$key];
        } else {
            $value = $caster instanceof CastsInboundAttributes
                        ? $value
                        : $caster->get($this, $key, $value, $this->attributes);

            if ($caster instanceof CastsInboundAttributes ||
                ($caster instanceof CastsAttributes && $caster->withoutObjectCaching) ||
                is_null($value)) {
                unset($this->classCastCache[$key]);
            } else {
                $this->classCastCache[$key] = $value;
            }

            return $value;
        }
    }

    /**
     * Get the type of cast for a model attribute.
     */
    protected function getCastType(string $key): string
    {
        if ($this->isCustomDateTimeCast($this->getCasts()[$key])) {
            return 'custom_datetime';
        }

        if ($this->isImmutableCustomDateTimeCast($this->getCasts()[$key])) {
            return 'immutable_custom_datetime';
        }

        if ($this->isDecimalCast($this->getCasts()[$key])) {
            return 'decimal';
        }

        return trim(strtolower($this->getCasts()[$key]));
    }

    /**
     * Determine if the cast type is a custom date time cast.
     */
    protected function isCustomDateTimeCast(string $cast): bool
    {
        return strncmp($cast, 'date:', 5) === 0 ||
               strncmp($cast, 'datetime:', 9) === 0;
    }

    /**
     * Determine if the cast type is an immutable custom date time cast.
     */
    protected function isImmutableCustomDateTimeCast(string $cast): bool
    {
        return strncmp($cast, 'immutable_date:', 15) === 0 ||
               strncmp($cast, 'immutable_datetime:', 19) === 0;
    }

    /**
     * Determine if the cast type is a decimal cast.
     */
    protected function isDecimalCast(string $cast): bool
    {
        return strncmp($cast, 'decimal:', 8) === 0;
    }

    /**
     * Set the array of model attributes. No checking is done.
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        $this->classCastCache = [];

        return $this;
    }

    /**
     * Get all of the current attributes on the model.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get all of the current attributes on the model for an insert operation.
     */
    protected function getAttributesForInsert(): array
    {
        return $this->getAttributes();
    }

    /**
     * Set the array of model attributes.
     */
    public function setAttributes(array $attributes): static
    {
        $this->attributes = [];

        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Sync the original attributes with the current.
     */
    public function syncOriginal(): static
    {
        $this->original = $this->getAttributes();

        return $this;
    }

    /**
     * Sync a single original attribute with its current value.
     */
    public function syncOriginalAttribute(string $attribute): static
    {
        return $this->syncOriginalAttributes($attribute);
    }

    /**
     * Sync multiple original attribute with their current values.
     */
    public function syncOriginalAttributes(array|string $attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $modelAttributes = $this->getAttributes();

        foreach ($attributes as $attribute) {
            $this->original[$attribute] = $modelAttributes[$attribute];
        }

        return $this;
    }

    /**
     * Sync the changed attributes.
     */
    public function syncChanges(): static
    {
        $this->changes = $this->getDirty();

        // After recording changes, sync original to make model clean
        $this->syncOriginal();

        return $this;
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getDirty(), is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Determine if the model and all the given attribute(s) have remained the same.
     */
    public function isClean(array|string|null $attributes = null): bool
    {
        return !$this->isDirty(...func_get_args());
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     */
    public function wasChanged(array|string|null $attributes = null): bool
    {
        return $this->hasChanges(
            $this->getChanges(), is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Determine if any of the given attributes were changed.
     */
    protected function hasChanges(array $changes, array|null $attributes = null): bool
    {
        // If no specific attributes were provided, we will just see if the dirty array
        // already contains any attributes. If it does we will just return that this
        // count is greater than zero. Else, we need to check specific attributes.
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        // Here we will spin through every attribute and see if this is in the array of
        // dirty attributes. If it is, we will return true and if we make it through
        // all of the attributes for the entire array we will return false at end.
        foreach (Arr::wrap($attributes) as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that have been changed since last sync.
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->getAttributes() as $key => $value) {
            if (!$this->originalIsEquivalent($key)) {
                // Return the cast value for consistency
                $dirty[$key] = $this->hasCast($key) ? $this->castAttribute($key, $value) : $value;
            }
        }

        return $dirty;
    }

    /**
     * Get the attributes that were changed.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Get the model's original attribute values.
     */
    public function getOriginal(?string $key = null): mixed
    {
        if ($key) {
            return $this->original[$key] ?? null;
        }

        return $this->original;
    }

    /**
     * Determine if the new and old values for a given key are equivalent.
     */
    public function originalIsEquivalent(string $key): bool
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }

        $attribute = Arr::get($this->attributes, $key);
        $original = Arr::get($this->original, $key);

        if ($attribute === $original) {
            return true;
        } elseif (is_null($attribute)) {
            return false;
        } elseif ($this->isDateAttribute($key)) {
            return $this->fromDateTime($attribute) ===
                   $this->fromDateTime($original);
        } elseif ($this->hasCast($key, ['object', 'collection'])) {
            return $this->castAttribute($key, $attribute) ==
                   $this->castAttribute($key, $original);
        } elseif ($this->hasCast($key, ['real', 'float', 'double'])) {
            if (($attribute === null) !== ($original === null)) {
                return false;
            }

            return abs($attribute - $original) < PHP_FLOAT_EPSILON * 4;
        } elseif ($this->hasCast($key, static::$primitiveCastTypes)) {
            return $this->castAttribute($key, $attribute) ===
                   $this->castAttribute($key, $original);
        }

        return is_numeric($attribute) && is_numeric($original)
                && strcmp((string) $attribute, (string) $original) === 0;
    }

    /**
     * Transform a raw model value using mutators, casts, etc.
     */
    protected function transformModelValue(string $key, mixed $value): mixed
    {
        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependent upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if ($value !== null
            && \in_array($key, $this->getDates(), true)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Determine if a get mutator exists for an attribute.
     */
    public function hasGetMutator(string $key): bool
    {
        return $this->hasLegacyGetMutator($key) || $this->hasAttributeAccessor($key);
    }

    /**
     * Determine if a legacy get mutator exists for an attribute.
     */
    public function hasLegacyGetMutator(string $key): bool
    {
        return method_exists($this, 'get'.Str::studly($key).'Attribute');
    }

    /**
     * Determine if an attribute accessor exists for an attribute.
     */
    public function hasAttributeAccessor(string $key): bool
    {
        $method = Str::camel($key);

        if (!method_exists($this, $method)) {
            return false;
        }

        try {
            $reflection = new \ReflectionMethod($this, $method);

            // Skip if method has parameters
            if ($reflection->getNumberOfParameters() > 0) {
                return false;
            }

            // Check return type
            $returnType = $reflection->getReturnType();
            return $returnType && $returnType->getName() === Attribute::class;
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Get the value of an attribute using its mutator.
     */
    protected function mutateAttribute(string $key, mixed $value): mixed
    {
        // Try legacy accessor first
        if ($this->hasLegacyGetMutator($key)) {
            return $this->{'get'.Str::studly($key).'Attribute'}($value);
        }

        // Try attribute accessor
        if ($this->hasAttributeAccessor($key)) {
            return $this->getAttributeAccessorValue($key, $value);
        }

        return $value;
    }

    /**
     * Get the value using an attribute accessor.
     */
    protected function getAttributeAccessorValue(string $key, mixed $value): mixed
    {
        $method = Str::camel($key);

        if (!method_exists($this, $method)) {
            return $value;
        }

        // Get the Attribute object
        $attribute = $this->$method();

        if (!$attribute instanceof Attribute) {
            return $value;
        }

        // Call the get method if it exists
        $get = $attribute->get;

        if ($get instanceof \Closure) {
            return $get($value, $this->attributes);
        }

        return $value;
    }

    /**
     * Determine if an attribute mutator exists for an attribute.
     */
    public function hasAttributeMutator(string $key): bool
    {
        if (isset(static::$attributeMutatorCache[get_class($this)][$key])) {
            return static::$attributeMutatorCache[get_class($this)][$key];
        }

        if (!method_exists($this, $method = Str::camel($key))) {
            return static::$attributeMutatorCache[get_class($this)][$key] = false;
        }

        $returnType = (new \ReflectionMethod($this, $method))->getReturnType();

        return static::$attributeMutatorCache[get_class($this)][$key] =
            ($returnType instanceof \ReflectionNamedType &&
             $returnType->getName() === Attribute::class) ||
            ($returnType instanceof \ReflectionUnionType &&
             collect($returnType->getTypes())->contains(
                 fn ($type) => $type->getName() === Attribute::class
             ));
    }

    /**
     * Convert the model's attributes to an array.
     */
    public function attributesToArray(): array
    {
        // If an attribute is a date, we will cast it to a string after converting it
        // to a DateTime / Carbon instance. This is so we will get some consistent
        // formatting while accessing attributes vs. arraying / JSONing a model.
        $attributes = $this->addDateAttributesToArray(
            $attributes = $this->getArrayableAttributes()
        );

        $attributes = $this->addMutatedAttributesToArray(
            $attributes, $mutatedAttributes = $this->getMutatedAttributes()
        );

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->addCastAttributesToArray(
            $attributes, $mutatedAttributes
        );

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * Add the date attributes to the attributes array.
     */
    protected function addDateAttributesToArray(array $attributes): array
    {
        foreach ($this->getDates() as $key) {
            if (!isset($attributes[$key])) {
                continue;
            }

            $attributes[$key] = $this->serializeDate(
                $this->asDateTime($attributes[$key])
            );
        }

        return $attributes;
    }

    /**
     * Add the mutated attributes to the attributes array.
     */
    protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes): array
    {
        foreach ($mutatedAttributes as $key) {
            // We want to spin through all the mutated attributes for this model and call
            // the mutator for the attribute. We cache off every mutated attributes so
            // we don't have to constantly check on attributes that actually change.
            if (!array_key_exists($key, $attributes)) {
                continue;
            }

            // Next, we will call the mutator for this attribute so that we can get these
            // mutated attribute's actual values. After we finish mutating each of the
            // attributes we will return this final array of the mutated attributes.
            $attributes[$key] = $this->mutateAttributeForArray(
                $key, $attributes[$key]
            );
        }

        // Also check for any attributes that have accessors but aren't in the mutated list
        foreach (array_keys($attributes) as $key) {
            if (!in_array($key, $mutatedAttributes) && $this->hasGetMutator($key)) {
                $attributes[$key] = $this->mutateAttributeForArray(
                    $key, $attributes[$key]
                );
            }
        }

        return $attributes;
    }

    /**
     * Add the casted attributes to the attributes array.
     */
    protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes): array
    {
        foreach ($this->getCasts() as $key => $value) {
            if (!array_key_exists($key, $attributes) ||
                in_array($key, $mutatedAttributes)) {
                continue;
            }

            // Here we will cast the attribute. We will also type-hint the return value
            // if the cast is a custom class cast. This will allow the IDE to know
            // the return type of the attribute when using the model in the IDE.
            $attributes[$key] = $this->castAttribute(
                $key, $attributes[$key]
            );

            // If the attribute cast was a date or a datetime, we will serialize the date
            // for the array. This will convert the dates to strings based on the date
            // serialization format specified for these Eloquent model instances.
            if ($attributes[$key] &&
                ($value === 'date' || $value === 'datetime')) {
                $attributes[$key] = $this->serializeDate($attributes[$key]);
            }

            if ($attributes[$key] && $this->isCustomDateTimeCast($value)) {
                $attributes[$key] = $this->serializeDate($attributes[$key]);
            }

            if ($attributes[$key] instanceof Arrayable) {
                $attributes[$key] = $attributes[$key]->toArray();
            }
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable attributes.
     */
    protected function getArrayableAttributes(): array
    {
        return $this->getArrayableItems($this->getAttributes());
    }

    /**
     * Get an attribute array of all arrayable values.
     */
    protected function getArrayableItems(array $values): array
    {
        if (count($this->getVisible()) > 0) {
            $values = array_intersect_key($values, array_flip($this->getVisible()));
        }

        if (count($this->getHidden()) > 0) {
            $values = array_diff_key($values, array_flip($this->getHidden()));
        }

        return $values;
    }

    /**
     * Get the visible attributes for the model.
     */
    public function getVisible(): array
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     */
    public function setVisible(array $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Get the hidden attributes for the model.
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     */
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Get the accessors that are being appended to the model's array form.
     */
    public function getAppends(): array
    {
        return $this->appends;
    }

    /**
     * Set the accessors to append to model arrays.
     */
    public function setAppends(array $appends): static
    {
        $this->appends = $appends;

        return $this;
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

        // Also include modern Attribute accessors/mutators
        $modernAttributes = $this->getModernAttributeAccessors();

        return array_unique(array_merge(static::$mutatorCache[$class], $modernAttributes));
    }

    /**
     * Get all modern Attribute accessor/mutator methods.
     */
    protected function getModernAttributeAccessors(): array
    {
        static $cache = [];
        $class = static::class;

        if (isset($cache[$class])) {
            return $cache[$class];
        }

        $methods = get_class_methods($class);
        $attributes = [];

        foreach ($methods as $method) {
            // Skip magic methods and known framework methods
            if (str_starts_with($method, '__') ||
                str_starts_with($method, 'get') ||
                str_starts_with($method, 'set') ||
                in_array($method, [
                    'boot', 'booted', 'bootIfNotBooted', 'initializeTraits', 'fill', 'save',
                    'delete', 'update', 'create', 'find', 'where', 'first', 'get', 'all',
                    'toArray', 'toJson', 'getAttribute', 'setAttribute', 'hasGetMutator',
                    'hasSetMutator', 'mutateAttribute', 'getMutatedAttributes', 'getModernAttributeAccessors'
                ])) {
                continue;
            }

            try {
                $reflection = new \ReflectionMethod($class, $method);

                // Skip if method has parameters
                if ($reflection->getNumberOfParameters() > 0) {
                    continue;
                }

                // Skip if method is not public
                if (!$reflection->isPublic()) {
                    continue;
                }

                // Check if method returns an Attribute object by calling it
                try {
                    $result = $this->$method();
                    if ($result instanceof Attribute) {
                        $attributeName = Str::snake($method);
                        $attributes[] = $attributeName;
                    }
                } catch (\Throwable $e) {
                    // Skip methods that throw exceptions when called
                    continue;
                }
            } catch (\ReflectionException $e) {
                // Skip methods that can't be reflected
                continue;
            }
        }

        return $cache[$class] = $attributes;
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
     * Return a decimal as string.
     */
    protected function asDecimal(mixed $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }

    /**
     * Return a timestamp as Unix timestamp.
     */
    protected function asTimestamp(mixed $value): int
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    /**
     * Decode the given JSON back into an array or object.
     */
    protected function fromJson(string|array $value, bool $asObject = false): mixed
    {
        // If value is already an array, return it as-is
        if (is_array($value)) {
            return $asObject ? (object) $value : $value;
        }

        $decoded = json_decode($value, !$asObject);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Unable to decode JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Return a decimal as float.
     */
    protected function fromFloat(mixed $value): float
    {
        return (float) $value;
    }

    /**
     * Cast the given attribute to JSON.
     */
    protected function castAttributeAsJson(string $key, mixed $value): string
    {
        return json_encode($value);
    }

    /**
     * Cast the given attribute as an encrypted string.
     */
    protected function castAttributeAsEncryptedString(string $key, mixed $value): string
    {
        // For now, return the value as-is. Encryption will be implemented later.
        return (string) $value;
    }

    /**
     * Set the value of a class castable attribute.
     */
    protected function setClassCastableAttribute(string $key, mixed $value): void
    {
        // For now, just set the attribute directly
        $this->attributes[$key] = $value;
    }

    /**
     * Fill a JSON attribute.
     */
    protected function fillJsonAttribute(string $key, mixed $value): static
    {
        [$key, $path] = explode('->', $key, 2);

        $this->attributes[$key] = $this->asJson($this->getArrayAttributeWithValue(
            $path, $key, $value
        ));

        return $this;
    }

    /**
     * Get an array attribute with the given key and value set.
     */
    protected function getArrayAttributeWithValue(string $path, string $key, mixed $value): array
    {
        return tap($this->getArrayAttributeByKey($key), function (&$array) use ($path, $value) {
            Arr::set($array, str_replace('->', '.', $path), $value);
        });
    }

    /**
     * Get an array attribute or return an empty array if it is not set.
     */
    protected function getArrayAttributeByKey(string $key): array
    {
        return $this->isJsonCastable($key)
            ? $this->fromJson($this->attributes[$key] ?? '{}')
            : $this->fromJson($this->attributes[$key] ?? '[]');
    }

    /**
     * Cast the given value to JSON.
     */
    protected function asJson(mixed $value): string
    {
        return json_encode($value);
    }

    /**
     * Encode the given value as JSON.
     */
    protected function asDate(mixed $value): Carbon
    {
        return $this->asDateTime($value)->startOfDay();
    }

    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * Get the mutated value for array conversion.
     */
    protected function mutateAttributeForArray(string $key, mixed $value): mixed
    {
        $value = $this->mutateAttribute($key, $value);

        return $value instanceof Arrayable ? $value->toArray() : $value;
    }

    /**
     * Get the appendable values that are arrayable.
     */
    protected function getArrayableAppends(): array
    {
        if (!count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }
}
