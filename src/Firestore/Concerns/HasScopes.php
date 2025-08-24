<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Illuminate\Support\Str;
use JTD\FirebaseModels\Firestore\Scopes\ScopeInterface;

/**
 * Provides query scope functionality for Firestore models.
 *
 * Supports both local scopes (scopeXxx methods) and global scopes
 * for reusable query logic, following Laravel Eloquent patterns.
 */
trait HasScopes
{
    /**
     * The array of global scopes on the model.
     */
    protected static array $globalScopes = [];

    /**
     * Register a new global scope on the model.
     */
    public static function addGlobalScope(ScopeInterface|string|\Closure $scope, ScopeInterface|\Closure|null $implementation = null): void
    {
        if (is_string($scope) && $implementation !== null) {
            static::$globalScopes[static::class][$scope] = $implementation;
        } elseif ($scope instanceof \Closure) {
            static::$globalScopes[static::class][spl_object_hash($scope)] = $scope;
        } elseif ($scope instanceof ScopeInterface) {
            static::$globalScopes[static::class][get_class($scope)] = $scope;
        }
    }

    /**
     * Determine if a model has a global scope.
     */
    public static function hasGlobalScope(ScopeInterface|string $scope): bool
    {
        return !is_null(static::getGlobalScope($scope));
    }

    /**
     * Get a global scope registered with the model.
     */
    public static function getGlobalScope(ScopeInterface|string $scope): ScopeInterface|\Closure|null
    {
        if (is_string($scope)) {
            return static::$globalScopes[static::class][$scope] ?? null;
        }

        return static::$globalScopes[static::class][get_class($scope)] ?? null;
    }

    /**
     * Get the global scopes for this model.
     */
    public function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    /**
     * Remove a registered global scope.
     */
    public static function removeGlobalScope(ScopeInterface|string $scope): void
    {
        if (is_string($scope)) {
            unset(static::$globalScopes[static::class][$scope]);
        } else {
            unset(static::$globalScopes[static::class][get_class($scope)]);
        }
    }

    /**
     * Remove all or passed registered global scopes.
     */
    public static function removeGlobalScopes(?array $scopes = null): void
    {
        if (is_array($scopes)) {
            foreach ($scopes as $scope) {
                static::removeGlobalScope($scope);
            }
        } else {
            static::$globalScopes[static::class] = [];
        }
    }

    /**
     * Get the local scopes for the model.
     */
    public function getLocalScopes(): array
    {
        $methods = get_class_methods($this);
        $scopes = [];

        foreach ($methods as $method) {
            if (str_starts_with($method, 'scope') && $method !== 'scope') {
                $scopeName = Str::camel(substr($method, 5));
                $scopes[$scopeName] = $method;
            }
        }

        return $scopes;
    }

    /**
     * Determine if the model has a local scope.
     */
    public function hasLocalScope(string $scope): bool
    {
        $method = 'scope'.Str::studly($scope);

        return method_exists($this, $method);
    }

    /**
     * Call a local scope on the model.
     */
    public function callScope(string $scope, array $parameters = []): mixed
    {
        $method = 'scope'.Str::studly($scope);

        if (!method_exists($this, $method)) {
            throw new \BadMethodCallException("Scope [{$scope}] does not exist on model [".static::class.']');
        }

        return $this->$method(...$parameters);
    }

    /**
     * Apply all global scopes to the given query builder.
     */
    public function applyGlobalScopes($builder): void
    {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            if ($scope instanceof ScopeInterface) {
                $scope->apply($builder, $this);
            } elseif ($scope instanceof \Closure) {
                $scope($builder, $this);
            }
        }
    }

    /**
     * Remove all global scopes from the given query builder.
     */
    public function removeGlobalScopesFromBuilder($builder): void
    {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            if ($scope instanceof ScopeInterface) {
                $scope->remove($builder, $this);
            }
            // Note: Closure scopes cannot be removed as they don't have a remove method
        }
    }

    /**
     * Create a new query builder with global scopes applied.
     */
    public function newQueryWithoutScopes(): mixed
    {
        return $this->newModelQuery();
    }

    /**
     * Create a new query builder without a given scope.
     */
    public function newQueryWithoutScope(ScopeInterface|string $scope): mixed
    {
        $builder = $this->newQuery();

        if ($scope instanceof ScopeInterface) {
            $scope->remove($builder, $this);
        } elseif (is_string($scope) && $this->hasGlobalScope($scope)) {
            $globalScope = $this->getGlobalScope($scope);
            if ($globalScope instanceof ScopeInterface) {
                $globalScope->remove($builder, $this);
            }
        }

        return $builder;
    }

    /**
     * Create a new query builder without any global scopes.
     */
    public function newQueryWithoutGlobalScopes(): mixed
    {
        // Create a new query builder without applying global scopes
        return $this->newFirestoreQueryBuilder(
            $this->newBaseQueryBuilder()
        )->setModel($this);
    }

    /**
     * Boot the scopes trait for a model.
     */
    public static function bootHasScopes(): void
    {
        // Initialize global scopes array for this model if not set
        if (!isset(static::$globalScopes[static::class])) {
            static::$globalScopes[static::class] = [];
        }
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     */
    public static function clearBootedScopes(): void
    {
        static::$globalScopes = [];

        // Also clear the booted models so they will be re-booted
        if (property_exists(static::class, 'booted')) {
            $reflection = new \ReflectionClass(static::class);
            $bootedProperty = $reflection->getProperty('booted');
            $bootedProperty->setAccessible(true);
            $booted = $bootedProperty->getValue();
            unset($booted[static::class]);
            $bootedProperty->setValue($booted);
        }
    }

    /**
     * Get all registered global scopes.
     */
    public static function getAllGlobalScopes(): array
    {
        return static::$globalScopes;
    }

    /**
     * Dynamically handle calls to local scopes.
     */
    public function scopeCall(string $method, array $parameters): mixed
    {
        if ($this->hasLocalScope($method)) {
            return $this->callScope($method, $parameters);
        }

        throw new \BadMethodCallException("Scope [{$method}] does not exist on model [".static::class.']');
    }

    /**
     * Register a global scope using a closure.
     */
    public static function globalScope(string $identifier, \Closure $scope): void
    {
        static::addGlobalScope($identifier, $scope);
    }

    /**
     * Get a list of all local scope names.
     */
    public function getLocalScopeNames(): array
    {
        return array_keys($this->getLocalScopes());
    }

    /**
     * Determine if any global scopes are registered.
     */
    public function hasGlobalScopes(): bool
    {
        return !empty($this->getGlobalScopes());
    }

    /**
     * Determine if any local scopes are defined.
     */
    public function hasLocalScopes(): bool
    {
        return !empty($this->getLocalScopes());
    }
}
