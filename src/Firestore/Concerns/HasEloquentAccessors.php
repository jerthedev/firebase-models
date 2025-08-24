<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * Complete Eloquent-style accessors and mutators for Firestore models.
 *
 * This trait provides full Laravel Eloquent compatibility for attribute
 * manipulation, supporting both legacy and modern accessor/mutator patterns.
 */
trait HasEloquentAccessors
{
    use HasAccessors, HasMutators {
        HasMutators::hasAttributeMutator insteadof HasAccessors;
    }

    /**
     * The attributes that should be appended to the model's array form.
     */
    protected array $appends = [];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in arrays.
     */
    protected array $visible = [];

    /**
     * Create an Attribute object for modern accessor/mutator definition.
     */
    public static function make(?callable $get = null, ?callable $set = null): Attribute
    {
        return new Attribute($get, $set);
    }

    /**
     * Get an attribute from the model with full accessor support.
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
            array_key_exists($key, $this->casts ?? []) ||
            $this->hasGetMutator($key) ||
            $this->hasAttributeAccessor($key)) {
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
        // given with the key in the pair.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if ($value !== null && $this->isDateAttribute($key)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Set a given attribute on the model with full mutator support.
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

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Convert the model's attributes to an array with accessor support.
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
            $attributes,
            $mutatedAttributes = $this->getMutatedAttributesForArray()
        );

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->addCastAttributesToArray(
            $attributes,
            $mutatedAttributes
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
     * Set the accessors to append to the model's array form.
     */
    public function setAppends(array $appends): static
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Add an accessor to append to the model's array form.
     */
    public function append(string|array $attributes): static
    {
        $this->appends = array_unique(
            array_merge($this->appends, is_string($attributes) ? [$attributes] : $attributes)
        );

        return $this;
    }

    /**
     * Get the mutated attributes for array conversion.
     */
    protected function getMutatedAttributesForArray(): array
    {
        $mutatedAttributes = [];

        foreach ($this->getMutatedAttributes() as $key) {
            if (array_key_exists($key, $this->attributes)) {
                $mutatedAttributes[$key] = $this->mutateAttributeForArray($key, $this->attributes[$key]);
            }
        }

        return $mutatedAttributes;
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
            // if the cast is a custom class cast.
            $attributes[$key] = $this->castAttribute(
                $key,
                $attributes[$key]
            );

            // If the attribute cast was a date or datetime cast then we will serialize
            // the date for the array. This will convert the dates to strings based on
            // the date format specified for these Eloquent models on the attributes.
            if ($attributes[$key] &&
                ($value === 'date' || $value === 'datetime')) {
                $attributes[$key] = $this->serializeDate($attributes[$key]);
            }

            if ($attributes[$key] && $this->isCustomDateTimeCast($value)) {
                $attributes[$key] = $attributes[$key]->format(explode(':', $value, 2)[1]);
            }

            if ($attributes[$key] instanceof \DateTimeInterface &&
                $this->isImmutableCustomDateTimeCast($value)) {
                $attributes[$key] = $attributes[$key]->toImmutable();
            }
        }

        return $attributes;
    }

    /**
     * Get the relation value for the given key.
     */
    protected function getRelationValue(string $key): mixed
    {
        // For now, return null as relations are not implemented yet
        return null;
    }

    /**
     * Determine if a get mutator exists for an attribute (compatibility method).
     */
    public function hasGetMutatorMethod(string $key): bool
    {
        return $this->hasGetMutator($key);
    }

    /**
     * Determine if a set mutator exists for an attribute (compatibility method).
     */
    public function hasSetMutatorMethod(string $key): bool
    {
        return $this->hasSetMutator($key);
    }

    /**
     * Get all mutated attributes for this model (unified implementation).
     */
    public function getMutatedAttributes(): array
    {
        $class = static::class;

        // Get legacy accessors
        $legacyAccessors = static::getAccessorMethods($class);

        // Get legacy mutators
        $legacyMutators = static::getMutatorMethods($class);

        // Get modern attribute accessors/mutators
        if (!isset(static::$attributeAccessorCache[$class])) {
            static::cacheAttributeAccessors($class);
        }

        if (!isset(static::$attributeMutatorCache[$class])) {
            static::cacheAttributeMutators($class);
        }

        $modernAccessors = static::$attributeAccessorCache[$class] ?? [];
        $modernMutators = static::$attributeMutatorCache[$class] ?? [];

        return array_unique(array_merge(
            array_keys($legacyAccessors),
            array_keys($legacyMutators),
            array_keys($modernAccessors),
            array_keys($modernMutators)
        ));
    }
}
