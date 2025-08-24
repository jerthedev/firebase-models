<?php

namespace JTD\FirebaseModels\Tests\Feature\Restructured;

use Illuminate\Support\Facades\Event;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\FeatureTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive Model Events Feature Test
 *
 * Consolidated from:
 * - tests/Feature/FirestoreModelEventsTest.php
 * - tests/Feature/FirestoreModelEventsSimpleTest.php
 *
 * Uses new FeatureTestSuite for comprehensive event testing scenarios.
 */

// Test model for comprehensive event testing
class EventTestProduct extends FirestoreModel
{
    protected ?string $collection = 'event_test_products';

    protected array $fillable = [
        'name', 'price', 'description', 'active', 'category_id',
    ];

    protected array $casts = [
        'price' => 'float',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Static event log for testing
    protected static array $eventLog = [];

    protected static function boot(): void
    {
        parent::boot();

        // Register event listeners for testing
        static::saving(function ($model) {
            static::$eventLog[] = ['saving', $model->name ?? 'unknown'];
        });

        static::creating(function ($model) {
            static::$eventLog[] = ['creating', $model->name ?? 'unknown'];
        });

        static::created(function ($model) {
            static::$eventLog[] = ['created', $model->name ?? 'unknown'];
        });

        static::saved(function ($model) {
            static::$eventLog[] = ['saved', $model->name ?? 'unknown'];
        });

        static::updating(function ($model) {
            static::$eventLog[] = ['updating', $model->name ?? 'unknown'];
        });

        static::updated(function ($model) {
            static::$eventLog[] = ['updated', $model->name ?? 'unknown'];
        });

        static::deleting(function ($model) {
            static::$eventLog[] = ['deleting', $model->name ?? 'unknown'];
        });

        static::deleted(function ($model) {
            static::$eventLog[] = ['deleted', $model->name ?? 'unknown'];
        });

        static::retrieved(function ($model) {
            static::$eventLog[] = ['retrieved', $model->name ?? 'unknown'];
        });
    }

    public static function clearEventLog(): void
    {
        static::$eventLog = [];
    }

    public static function getEventLog(): array
    {
        return static::$eventLog;
    }
}

// Test observer for advanced event testing
class EventTestProductObserver
{
    public array $events = [];

    public function creating($model)
    {
        $this->events[] = ['creating', $model->name];

        // Example validation in observer
        if ($model->name === 'Invalid Product') {
            return false;
        }
    }

    public function created($model)
    {
        $this->events[] = ['created', $model->name];
    }

    public function updating($model)
    {
        $this->events[] = ['updating', $model->name];
    }

    public function updated($model)
    {
        $this->events[] = ['updated', $model->name];
    }

    public function saving($model)
    {
        $this->events[] = ['saving', $model->name];
    }

    public function saved($model)
    {
        $this->events[] = ['saved', $model->name];
    }

    public function deleting($model)
    {
        $this->events[] = ['deleting', $model->name];
    }

    public function deleted($model)
    {
        $this->events[] = ['deleted', $model->name];
    }

    public function retrieved($model)
    {
        $this->events[] = ['retrieved', $model->name];
    }

    public function clearEvents(): void
    {
        $this->events = [];
    }
}

class ModelEventsTest extends FeatureTestSuite
{
    protected EventTestProductObserver $observer;

    protected function setUp(): void
    {
        // Configure test requirements for comprehensive event testing
        $this->setTestRequirements([
            'document_count' => 100,
            'memory_constraint' => false,
            'needs_full_mockery' => true,
        ]);

        parent::setUp();

        // Clear Laravel event fake and reset model events
        Event::fake();
        EventTestProduct::flushEventListeners();
        EventTestProduct::clearEventLog();

        // Set up observer
        $this->observer = new EventTestProductObserver();
    }

    // ========================================
    // BASIC EVENT REGISTRATION AND FIRING
    // ========================================

    #[Test]
    public function it_registers_and_fires_basic_model_events()
    {
        $events = [];

        // Register individual event listeners
        EventTestProduct::creating(function ($model) use (&$events) {
            $events[] = 'creating';
        });

        EventTestProduct::created(function ($model) use (&$events) {
            $events[] = 'created';
        });

        EventTestProduct::saving(function ($model) use (&$events) {
            $events[] = 'saving';
        });

        EventTestProduct::saved(function ($model) use (&$events) {
            $events[] = 'saved';
        });

        // Create a product and verify events fire in correct order
        $productData = TestDataFactory::createProduct([
            'name' => 'Event Test Product',
            'price' => 29.99,
            'active' => true,
        ]);

        $this->mockFirestoreCreate('event_test_products');

        $product = EventTestProduct::create($productData);

        expect($events)->toBe(['saving', 'creating', 'created', 'saved']);
        expect($product)->toBeFirestoreModel();
        expect($product->name)->toBe('Event Test Product');
        expect($product)->toExistInFirestore();
        expect($product)->toBeRecentlyCreated();
    }

    #[Test]
    public function it_fires_all_events_during_complete_lifecycle()
    {
        // Clear the static event log
        EventTestProduct::clearEventLog();

        // Create a product
        $productData = TestDataFactory::createProduct([
            'name' => 'Lifecycle Product',
            'price' => 19.99,
        ]);

        $this->mockFirestoreCreate('event_test_products');

        $product = EventTestProduct::create($productData);

        // Verify creation events
        $creationEvents = EventTestProduct::getEventLog();
        expect($creationEvents)->toContain(['saving', 'Lifecycle Product']);
        expect($creationEvents)->toContain(['creating', 'Lifecycle Product']);
        expect($creationEvents)->toContain(['created', 'Lifecycle Product']);
        expect($creationEvents)->toContain(['saved', 'Lifecycle Product']);

        // Clear log and test retrieval
        EventTestProduct::clearEventLog();

        $this->mockFirestoreGet('event_test_products', $product->id, [
            'id' => $product->id,
            'name' => 'Lifecycle Product',
            'price' => 19.99,
        ]);

        $retrievedProduct = EventTestProduct::find($product->id);

        $retrievalEvents = EventTestProduct::getEventLog();
        expect($retrievalEvents)->toContain(['retrieved', 'Lifecycle Product']);

        // Clear log and test update
        EventTestProduct::clearEventLog();

        $this->mockFirestoreUpdate('event_test_products', $product->id);

        $retrievedProduct->name = 'Updated Lifecycle Product';
        $retrievedProduct->save();

        $updateEvents = EventTestProduct::getEventLog();
        expect($updateEvents)->toContain(['saving', 'Updated Lifecycle Product']);
        expect($updateEvents)->toContain(['updating', 'Updated Lifecycle Product']);
        expect($updateEvents)->toContain(['updated', 'Updated Lifecycle Product']);
        expect($updateEvents)->toContain(['saved', 'Updated Lifecycle Product']);

        // Clear log and test deletion
        EventTestProduct::clearEventLog();

        $this->mockFirestoreDelete('event_test_products', $product->id);

        $retrievedProduct->delete();

        $deleteEvents = EventTestProduct::getEventLog();
        expect($deleteEvents)->toContain(['deleting', 'Updated Lifecycle Product']);
        expect($deleteEvents)->toContain(['deleted', 'Updated Lifecycle Product']);
    }

    // ========================================
    // EVENT CANCELLATION AND VALIDATION
    // ========================================

    #[Test]
    public function it_can_cancel_operations_with_event_listeners()
    {
        // Test creation cancellation
        EventTestProduct::creating(function ($model) {
            if ($model->name === 'Forbidden Product') {
                return false; // Cancel creation
            }
        });

        $this->mockFirestoreCreate('event_test_products');

        $forbiddenProduct = new EventTestProduct([
            'name' => 'Forbidden Product',
            'price' => 50.00,
        ]);

        $result = $forbiddenProduct->save();

        expect($result)->toBeFalse();
        expect($forbiddenProduct->exists)->toBeFalse();
        expect($forbiddenProduct->wasRecentlyCreated)->toBeFalse();

        // Test that allowed products still work
        $allowedProduct = new EventTestProduct([
            'name' => 'Allowed Product',
            'price' => 25.00,
        ]);

        $allowedResult = $allowedProduct->save();

        expect($allowedResult)->toBeTrue();
        expect($allowedProduct->exists)->toBeTrue();
        expect($allowedProduct->wasRecentlyCreated)->toBeTrue();

        // Test update cancellation
        EventTestProduct::updating(function ($model) {
            if ($model->name === 'No Updates Allowed') {
                return false; // Cancel update
            }
        });

        $this->mockFirestoreGet('event_test_products', 'update-test', [
            'id' => 'update-test',
            'name' => 'Original Name',
            'price' => 30.00,
        ]);

        $updateProduct = EventTestProduct::find('update-test');
        $updateProduct->name = 'No Updates Allowed';

        $updateResult = $updateProduct->save();

        expect($updateResult)->toBeFalse();
        expect($updateProduct->name)->toBe('No Updates Allowed'); // Local change remains
    }

    #[Test]
    public function it_supports_event_validation_and_modification()
    {
        // Test event-based validation and modification
        EventTestProduct::saving(function ($model) {
            // Auto-format name
            $model->name = ucwords(strtolower($model->name));

            // Validate price
            if ($model->price < 0) {
                $model->price = 0;
            }

            // Set default description if empty
            if (empty($model->description)) {
                $model->description = "Auto-generated description for {$model->name}";
            }
        });

        $this->mockFirestoreCreate('event_test_products');

        $product = EventTestProduct::create([
            'name' => 'test PRODUCT name',
            'price' => -10.00, // Invalid price
            // No description provided
        ]);

        expect($product->name)->toBe('Test Product Name'); // Auto-formatted
        expect($product->price)->toBe(0.0); // Auto-corrected
        expect($product->description)->toBe('Auto-generated description for Test Product Name'); // Auto-generated
    }

    // ========================================
    // OBSERVER PATTERN TESTING
    // ========================================

    #[Test]
    public function it_works_with_model_observers()
    {
        // Register the observer
        EventTestProduct::observe($this->observer);

        // Test creation with observer
        $productData = TestDataFactory::createProduct([
            'name' => 'Observer Test Product',
            'price' => 35.00,
        ]);

        $this->mockFirestoreCreate('event_test_products');

        $product = EventTestProduct::create($productData);

        // Verify observer events were fired
        expect($this->observer->events)->toContain(['saving', 'Observer Test Product']);
        expect($this->observer->events)->toContain(['creating', 'Observer Test Product']);
        expect($this->observer->events)->toContain(['created', 'Observer Test Product']);
        expect($this->observer->events)->toContain(['saved', 'Observer Test Product']);

        // Test observer validation
        $this->observer->clearEvents();

        $invalidProduct = new EventTestProduct([
            'name' => 'Invalid Product', // This will be rejected by observer
            'price' => 40.00,
        ]);

        $result = $invalidProduct->save();

        expect($result)->toBeFalse();
        expect($this->observer->events)->toContain(['saving', 'Invalid Product']);
        expect($this->observer->events)->toContain(['creating', 'Invalid Product']);
        expect($this->observer->events)->not->toContain(['created', 'Invalid Product']);
    }

    // ========================================
    // PERFORMANCE AND MEMORY TESTING
    // ========================================

    #[Test]
    public function it_handles_events_efficiently_with_multiple_models()
    {
        // Test event performance with multiple models
        $scenarioMetrics = $this->performFeatureScenario('multiple_model_events', function () {
            $products = [];

            // Create multiple products with events
            for ($i = 0; $i < 20; $i++) {
                $productData = TestDataFactory::createProduct([
                    'name' => "Bulk Event Product {$i}",
                    'price' => 10.00 + $i,
                    'active' => $i % 2 === 0,
                ]);

                $this->mockFirestoreCreate('event_test_products');
                $products[] = EventTestProduct::create($productData);
            }

            // Update all products (triggering update events)
            foreach ($products as $index => $product) {
                $this->mockFirestoreUpdate('event_test_products', $product->id);
                $product->update(['description' => "Updated description {$index}"]);
            }

            return count($products);
        });

        expect($scenarioMetrics['result'])->toBe(20);
        $this->assertFeaturePerformance($scenarioMetrics, 2.0, 10 * 1024 * 1024);

        // Verify events were fired for all models
        $eventLog = EventTestProduct::getEventLog();
        expect($eventLog)->not->toBeEmpty();

        // Count creation events
        $creationEvents = array_filter($eventLog, fn ($event) => $event[0] === 'created');
        expect(count($creationEvents))->toBe(20);

        // Count update events
        $updateEvents = array_filter($eventLog, fn ($event) => $event[0] === 'updated');
        expect(count($updateEvents))->toBe(20);
    }

    #[Test]
    public function it_handles_event_listener_registration_and_removal()
    {
        $events = [];

        // Register a listener
        $listener = function ($model) use (&$events) {
            $events[] = 'custom_listener';
        };

        EventTestProduct::creating($listener);

        // Test that listener fires
        $this->mockFirestoreCreate('event_test_products');

        EventTestProduct::create(['name' => 'Listener Test', 'price' => 15.00]);

        expect($events)->toContain('custom_listener');

        // Clear events and flush listeners
        $events = [];
        EventTestProduct::flushEventListeners();

        // Test that listener no longer fires
        EventTestProduct::create(['name' => 'No Listener Test', 'price' => 20.00]);

        expect($events)->not->toContain('custom_listener');
    }

    #[Test]
    public function it_maintains_event_order_and_consistency()
    {
        $eventOrder = [];

        // Register listeners that track order
        EventTestProduct::saving(function ($model) use (&$eventOrder) {
            $eventOrder[] = 'saving';
        });

        EventTestProduct::creating(function ($model) use (&$eventOrder) {
            $eventOrder[] = 'creating';
        });

        EventTestProduct::created(function ($model) use (&$eventOrder) {
            $eventOrder[] = 'created';
        });

        EventTestProduct::saved(function ($model) use (&$eventOrder) {
            $eventOrder[] = 'saved';
        });

        $this->mockFirestoreCreate('event_test_products');

        EventTestProduct::create(['name' => 'Order Test', 'price' => 25.00]);

        // Verify correct event order
        expect($eventOrder)->toBe(['saving', 'creating', 'created', 'saved']);
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create test models with events
        $models = $this->createTestModels(EventTestProduct::class, 3);

        // Verify models were created and events fired
        expect($models)->toHaveCount(3);
        foreach ($models as $model) {
            expect($model)->toBeInstanceOf(EventTestProduct::class);
        }

        $eventLog = EventTestProduct::getEventLog();
        expect($eventLog)->not->toBeEmpty();

        // Clear test data
        $this->cleanupFeatureData();
        EventTestProduct::clearEventLog();

        // Verify cleanup
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();

        $clearedEventLog = EventTestProduct::getEventLog();
        expect($clearedEventLog)->toBeEmpty();
    }
}
