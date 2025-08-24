<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * Enhanced mutators support for Firestore models.
 *
 * Provides full Laravel Eloquent compatibility for attribute mutators,
 * including support for both legacy setXAttribute methods and modern
 * Attribute objects with get/set methods.
 */
trait HasMutators
{
    /**
     * The cache of mutator methods.
     */
    protected static array $mutatorCache = [];

    /**
     * The cache of attribute mutator objects.
     */
    protected static array $attributeMutatorCache = [];

    /**
     * Determine if a mutator exists for an attribute.
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
     * Determine if an attribute mutator exists for an attribute.
     */
    public function hasAttributeMutator(string $key): bool
    {
        $class = static::class;

        if (!isset(static::$attributeMutatorCache[$class])) {
            static::cacheAttributeMutators($class);
        }

        return isset(static::$attributeMutatorCache[$class][$key]);
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
        $class = static::class;
        $mutator = static::$attributeMutatorCache[$class][$key] ?? null;

        if (!$mutator) {
            $this->attributes[$key] = $value;

            return;
        }

        // Get the Attribute object
        $attribute = $this->$mutator();

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
     * Cache all attribute mutators for a class.
     */
    protected static function cacheAttributeMutators(string $class): void
    {
        static::$attributeMutatorCache[$class] = [];

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
                        static::$attributeMutatorCache[$class][$attributeName] = $method;
                    }
                } catch (\ReflectionException $e) {
                    // Skip methods that can't be reflected
                    continue;
                }
            }
        }
    }

    /**
     * Get all mutator methods for a class.
     */
    public static function getMutatorMethods(string $class): array
    {
        if (!isset(static::$mutatorCache[$class])) {
            static::cacheMutatorMethods($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Cache all mutator methods for a class.
     */
    protected static function cacheMutatorMethods(string $class): void
    {
        $methods = get_class_methods($class);
        $mutators = [];

        foreach ($methods as $method) {
            // Legacy setXAttribute methods
            if (preg_match('/^set([A-Z].*)Attribute$/', $method, $matches)) {
                $attributeName = Str::snake($matches[1]);
                $mutators[$attributeName] = $method;
            }
        }

        static::$mutatorCache[$class] = $mutators;
    }

    /**
     * Get all mutated attributes for this model.
     */
    public function getMutatedAttributes(): array
    {
        $class = static::class;

        $legacy = static::getMutatorMethods($class);

        if (!isset(static::$attributeMutatorCache[$class])) {
            static::cacheAttributeMutators($class);
        }

        $modern = static::$attributeMutatorCache[$class];

        return array_merge(array_keys($legacy), array_keys($modern));
    }

    /**
     * Determine if the given attribute has a mutator.
     */
    public function hasAttributeMutatorMethod(string $key): bool
    {
        return $this->hasSetMutator($key);
    }

    /**
     * Get the mutated value for a given attribute.
     */
    protected function getMutatedAttributeValue(string $key, mixed $value): mixed
    {
        // This is used when we need to get the mutated value without setting it
        if ($this->hasLegacySetMutator($key)) {
            // For legacy mutators, we can't easily get the mutated value without side effects
            // So we'll return the original value
            return $value;
        }

        if ($this->hasAttributeMutator($key)) {
            $class = static::class;
            $mutator = static::$attributeMutatorCache[$class][$key] ?? null;

            if ($mutator) {
                $attribute = $this->$mutator();

                if ($attribute instanceof Attribute) {
                    $set = $attribute->set;

                    if ($set instanceof \Closure) {
                        return $set($value, $this->attributes);
                    }
                }
            }
        }

        return $value;
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

        foreach ($this->getMutatedAttributes() as $key) {
            if ($this->mutatedAttributeHasChanged($key)) {
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
            unset(static::$attributeMutatorCache[$class]);
        } else {
            static::$mutatorCache = [];
            static::$attributeMutatorCache = [];
        }
    }

    /**
     * Get all cached mutators.
     */
    public static function getCachedMutators(): array
    {
        return [
            'legacy' => static::$mutatorCache,
            'modern' => static::$attributeMutatorCache,
        ];
    }

    /**
     * Reset all mutated attributes to their original values.
     */
    public function resetMutatedAttributes(): static
    {
        foreach ($this->getMutatedAttributes() as $key) {
            if (array_key_exists($key, $this->original)) {
                $this->attributes[$key] = $this->original[$key];
            } else {
                unset($this->attributes[$key]);
            }
        }

        return $this;
    }
}
