<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Enhanced accessors support for Firestore models.
 * 
 * Provides full Laravel Eloquent compatibility for attribute accessors,
 * including support for both legacy getXAttribute methods and modern
 * Attribute objects with get/set methods.
 */
trait HasAccessors
{
    /**
     * The cache of accessor methods.
     */
    protected static array $accessorCache = [];

    /**
     * The cache of attribute accessor objects.
     */
    protected static array $attributeAccessorCache = [];

    /**
     * Determine if an accessor exists for an attribute.
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
        $class = static::class;
        
        if (!isset(static::$attributeAccessorCache[$class])) {
            static::cacheAttributeAccessors($class);
        }

        return isset(static::$attributeAccessorCache[$class][$key]);
    }

    /**
     * Get the value of an attribute using its accessor.
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
        $class = static::class;
        $accessor = static::$attributeAccessorCache[$class][$key] ?? null;

        if (!$accessor) {
            return $value;
        }

        // Get the Attribute object
        $attribute = $this->$accessor();

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
     * Cache all attribute accessors for a class.
     */
    protected static function cacheAttributeAccessors(string $class): void
    {
        static::$attributeAccessorCache[$class] = [];

        $methods = get_class_methods($class);

        foreach ($methods as $method) {
            // Look for methods that return Attribute objects
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)$/', $method, $matches)) {
                try {
                    $reflection = new \ReflectionMethod($class, $method);
                    
                    // Skip if method has parameters
                    if ($reflection->getNumberOfParameters() > 0) {
                        continue;
                    }

                    // Check return type
                    $returnType = $reflection->getReturnType();
                    if ($returnType && $returnType->getName() === Attribute::class) {
                        $attributeName = Str::snake($method);
                        static::$attributeAccessorCache[$class][$attributeName] = $method;
                    }
                } catch (\ReflectionException $e) {
                    // Skip methods that can't be reflected
                    continue;
                }
            }
        }
    }

    /**
     * Get all accessor methods for a class.
     */
    public static function getAccessorMethods(string $class): array
    {
        if (!isset(static::$accessorCache[$class])) {
            static::cacheAccessorMethods($class);
        }

        return static::$accessorCache[$class];
    }

    /**
     * Cache all accessor methods for a class.
     */
    protected static function cacheAccessorMethods(string $class): void
    {
        $methods = get_class_methods($class);
        $accessors = [];

        foreach ($methods as $method) {
            // Legacy getXAttribute methods
            if (preg_match('/^get([A-Z].*)Attribute$/', $method, $matches)) {
                $attributeName = Str::snake($matches[1]);
                $accessors[$attributeName] = $method;
            }
        }

        static::$accessorCache[$class] = $accessors;
    }

    /**
     * Get all mutated attributes for this model.
     */
    public function getMutatedAttributes(): array
    {
        $class = static::class;
        
        $legacy = static::getAccessorMethods($class);
        
        if (!isset(static::$attributeAccessorCache[$class])) {
            static::cacheAttributeAccessors($class);
        }
        
        $modern = static::$attributeAccessorCache[$class];

        return array_merge(array_keys($legacy), array_keys($modern));
    }

    /**
     * Determine if the given attribute may be mass assigned.
     */
    public function hasAttributeMutator(string $key): bool
    {
        return $this->hasGetMutator($key) || $this->hasSetMutator($key);
    }

    /**
     * Get an attribute value for array conversion.
     */
    protected function mutateAttributeForArray(string $key, mixed $value): mixed
    {
        $value = $this->mutateAttribute($key, $value);

        return $value instanceof \DateTimeInterface
            ? $this->serializeDate($value)
            : $value;
    }

    /**
     * Append attributes to the model's array form.
     */
    protected function getArrayableAppends(): array
    {
        if (!isset($this->appends)) {
            return [];
        }

        return $this->appends;
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
     * Add mutated attributes to the attributes array.
     */
    protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes): array
    {
        foreach ($mutatedAttributes as $key => $value) {
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * Get the value of an attribute using its accessor for JSON serialization.
     */
    protected function getAttributeForJson(string $key): mixed
    {
        $value = $this->getAttribute($key);

        if ($value instanceof \DateTimeInterface) {
            return $this->serializeDate($value);
        }

        if ($value instanceof \Illuminate\Contracts\Support\Jsonable) {
            return json_decode($value->toJson(), true);
        }

        if ($value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }

        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Serialize a date for JSON.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * Get the format for database stored dates.
     */
    protected function getDateFormat(): string
    {
        return $this->dateFormat ?? 'Y-m-d H:i:s';
    }

    /**
     * Clear the accessor cache for a class.
     */
    public static function clearAccessorCache(?string $class = null): void
    {
        if ($class) {
            unset(static::$accessorCache[$class]);
            unset(static::$attributeAccessorCache[$class]);
        } else {
            static::$accessorCache = [];
            static::$attributeAccessorCache = [];
        }
    }

    /**
     * Get all cached accessors.
     */
    public static function getCachedAccessors(): array
    {
        return [
            'legacy' => static::$accessorCache,
            'modern' => static::$attributeAccessorCache,
        ];
    }
}
