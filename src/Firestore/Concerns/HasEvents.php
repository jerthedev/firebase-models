<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;

/**
 * Trait for handling model events.
 */
trait HasEvents
{
    /**
     * The event map for the model.
     *
     * Allows for object-based events for native Eloquent events.
     */
    protected array $dispatchesEvents = [];

    /**
     * User exposed observable events.
     *
     * These are extra user-defined events observers may subscribe to.
     */
    protected array $observables = [];

    /**
     * The array of booted models.
     */
    protected static array $booted = [];

    /**
     * The array of trait initializers that will be called on each new instance.
     */
    protected static array $traitInitializers = [];

    /**
     * The array of global scopes on the model.
     */
    protected static array $globalScopes = [];

    /**
     * Register an observer with the Model.
     */
    public static function observe(object|array|string $classes): void
    {
        $instance = new static();

        foreach (Arr::wrap($classes) as $class) {
            $instance->registerObserver($class);
        }
    }

    /**
     * Register a single observer with the Model.
     */
    protected function registerObserver(object|string $class): void
    {
        $observer = is_string($class) ? new $class() : $class;

        // When registering a model observer, we will spin through the possible events
        // and determine if this observer has that method. If it does, we will hook
        // it into the model's event system, making it convenient to watch these.
        foreach ($this->getObservableEvents() as $event) {
            if (method_exists($observer, $event)) {
                static::registerModelEvent($event, function ($model) use ($observer, $event) {
                    return $observer->{$event}($model);
                });
            }
        }
    }

    /**
     * Resolve the observer's class name from an object or string.
     */
    private function resolveObserverClassName(object|string $class): string
    {
        if (is_object($class)) {
            return get_class($class);
        }

        if (class_exists($class)) {
            return $class;
        }

        throw new \InvalidArgumentException("Unable to find observer: {$class}");
    }

    /**
     * Get the observable event names.
     */
    public function getObservableEvents(): array
    {
        return array_merge(
            [
                'retrieved', 'creating', 'created', 'updating', 'updated',
                'saving', 'saved', 'restoring', 'restored', 'replicating',
                'deleting', 'deleted', 'forceDeleted', 'trashed',
            ],
            $this->observables
        );
    }

    /**
     * Set the observable event names.
     */
    public function setObservableEvents(array $observables): static
    {
        $this->observables = $observables;

        return $this;
    }

    /**
     * Add an observable event name.
     */
    public function addObservableEvents(array|string $observables): void
    {
        $this->observables = array_unique(array_merge(
            $this->observables,
            is_array($observables) ? $observables : func_get_args()
        ));
    }

    /**
     * Remove an observable event name.
     */
    public function removeObservableEvents(array|string $observables): static
    {
        $this->observables = array_diff(
            $this->observables,
            is_array($observables) ? $observables : func_get_args()
        );

        return $this;
    }

    /**
     * Register a model event with the dispatcher.
     */
    protected static function registerModelEvent(string $event, \Closure|string $callback): void
    {
        if (isset(static::$dispatcher)) {
            $name = static::class;

            static::$dispatcher->listen("eloquent.{$event}: {$name}", $callback);
        }
    }

    /**
     * Fire the given event for the model.
     */
    protected function fireModelEvent(string $event, bool $halt = true): mixed
    {
        if (!isset(static::$dispatcher)) {
            return true;
        }

        // First, we will get the proper method to call on the event dispatcher, and then we
        // will attempt to fire a custom, object based event for the given event. If that
        // returns a result we can return that result, or we'll call the string events.
        $method = $halt ? 'until' : 'dispatch';

        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $method)
        );

        if ($result === false) {
            return false;
        }

        return !empty($result) ? $result : static::$dispatcher->{$method}(
            "eloquent.{$event}: ".static::class,
            $this
        );
    }

    /**
     * Fire a custom model event for the given event.
     */
    protected function fireCustomModelEvent(string $event, string $method): mixed
    {
        if (!isset($this->dispatchesEvents[$event])) {
            return null;
        }

        $result = static::$dispatcher->$method(new $this->dispatchesEvents[$event]($this));

        if (!is_null($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Filter the model event results.
     */
    protected function filterModelEventResults(mixed $result): mixed
    {
        if (is_array($result)) {
            $result = array_filter($result, function ($response) {
                return !is_null($response);
            });
        }

        return $result;
    }

    /**
     * Register a retrieved model event with the dispatcher.
     */
    public static function retrieved(\Closure|string $callback): void
    {
        static::registerModelEvent('retrieved', $callback);
    }

    /**
     * Register a saving model event with the dispatcher.
     */
    public static function saving(\Closure|string $callback): void
    {
        static::registerModelEvent('saving', $callback);
    }

    /**
     * Register a saved model event with the dispatcher.
     */
    public static function saved(\Closure|string $callback): void
    {
        static::registerModelEvent('saved', $callback);
    }

    /**
     * Register an updating model event with the dispatcher.
     */
    public static function updating(\Closure|string $callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Register an updated model event with the dispatcher.
     */
    public static function updated(\Closure|string $callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Register a creating model event with the dispatcher.
     */
    public static function creating(\Closure|string $callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a created model event with the dispatcher.
     */
    public static function created(\Closure|string $callback): void
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Register a replicating model event with the dispatcher.
     */
    public static function replicating(\Closure|string $callback): void
    {
        static::registerModelEvent('replicating', $callback);
    }

    /**
     * Register a deleting model event with the dispatcher.
     */
    public static function deleting(\Closure|string $callback): void
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Register a deleted model event with the dispatcher.
     */
    public static function deleted(\Closure|string $callback): void
    {
        static::registerModelEvent('deleted', $callback);
    }

    /**
     * Remove all of the event listeners for the model.
     */
    public static function flushEventListeners(): void
    {
        if (!isset(static::$dispatcher)) {
            return;
        }

        $instance = new static();

        foreach ($instance->getObservableEvents() as $event) {
            static::$dispatcher->forget("eloquent.{$event}: ".static::class);
        }

        foreach (array_values($instance->dispatchesEvents) as $event) {
            static::$dispatcher->forget($event);
        }
    }

    /**
     * Execute a callback without firing any model events for any model type.
     */
    public static function withoutEvents(\Closure $callback): mixed
    {
        $dispatcher = static::getEventDispatcher();

        if ($dispatcher) {
            static::setEventDispatcher(null);
        }

        try {
            return $callback();
        } finally {
            if ($dispatcher) {
                static::setEventDispatcher($dispatcher);
            }
        }
    }

    /**
     * Get the event dispatcher instance.
     */
    public static function getEventDispatcher(): ?\Illuminate\Contracts\Events\Dispatcher
    {
        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     */
    public static function setEventDispatcher(?\Illuminate\Contracts\Events\Dispatcher $dispatcher): void
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     */
    public static function unsetEventDispatcher(): void
    {
        static::$dispatcher = null;
    }

    /**
     * Execute the given callback without firing any model events for this model type.
     */
    public static function withoutEventsForModel(\Closure $callback): mixed
    {
        $events = static::getEventDispatcher();

        if (!$events) {
            return $callback();
        }

        $modelEvents = [];
        $instance = new static();

        foreach ($instance->getObservableEvents() as $event) {
            $modelEvents[] = "eloquent.{$event}: ".static::class;
        }

        foreach (array_values($instance->dispatchesEvents) as $event) {
            $modelEvents[] = $event;
        }

        $originalListeners = [];

        foreach ($modelEvents as $event) {
            $originalListeners[$event] = $events->getListeners($event);
            $events->forget($event);
        }

        try {
            return $callback();
        } finally {
            foreach ($originalListeners as $event => $listeners) {
                foreach ($listeners as $listener) {
                    $events->listen($event, $listener);
                }
            }
        }
    }
}
