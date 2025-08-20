<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use Illuminate\Support\Facades\Event;

// Simple test model for event testing
class SimpleEventProduct extends FirestoreModel
{
    protected ?string $collection = 'simple_event_products';
    
    protected array $fillable = ['name', 'price'];
    
    protected array $casts = [
        'price' => 'float',
    ];
}

describe('FirestoreModel Events - Simple Tests', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        Event::fake();
        SimpleEventProduct::flushEventListeners();
    });

    it('fires all events during model creation', function () {
        $events = [];
        
        // Register all event listeners
        SimpleEventProduct::saving(function ($model) use (&$events) {
            $events[] = 'saving';
        });
        
        SimpleEventProduct::creating(function ($model) use (&$events) {
            $events[] = 'creating';
        });
        
        SimpleEventProduct::created(function ($model) use (&$events) {
            $events[] = 'created';
        });
        
        SimpleEventProduct::saved(function ($model) use (&$events) {
            $events[] = 'saved';
        });
        
        $this->mockFirestoreCreate('simple_event_products');
        
        $product = SimpleEventProduct::create(['name' => 'Event Test', 'price' => 10.00]);
        
        expect($events)->toBe(['saving', 'creating', 'created', 'saved']);
        expect($product->exists)->toBeTrue();
        expect($product->wasRecentlyCreated)->toBeTrue();
    });

    it('fires retrieved event when model is loaded', function () {
        $events = [];
        
        SimpleEventProduct::retrieved(function ($model) use (&$events) {
            $events[] = ['retrieved', $model->name];
        });
        
        $this->mockFirestoreGet('simple_event_products', '1', [
            'id' => '1',
            'name' => 'Retrieved Product',
            'price' => 15.00
        ]);
        
        $product = SimpleEventProduct::find('1');
        
        expect($events)->toContain(['retrieved', 'Retrieved Product']);
        expect($product->name)->toBe('Retrieved Product');
    });

    it('can cancel operations with event listeners', function () {
        // Cancel creation
        SimpleEventProduct::creating(function ($model) {
            if ($model->name === 'Forbidden') {
                return false;
            }
        });
        
        $this->mockFirestoreCreate('simple_event_products');
        
        $product = new SimpleEventProduct(['name' => 'Forbidden', 'price' => 20.00]);
        $result = $product->save();
        
        expect($result)->toBeFalse();
        expect($product->exists)->toBeFalse();
        
        // Allow creation
        $product2 = new SimpleEventProduct(['name' => 'Allowed', 'price' => 25.00]);
        $result2 = $product2->save();
        
        expect($result2)->toBeTrue();
        expect($product2->exists)->toBeTrue();
    });

    it('supports event listener registration and removal', function () {
        $events = [];
        
        // Register listener
        $listener = function ($model) use (&$events) {
            $events[] = 'listener_called';
        };
        
        SimpleEventProduct::creating($listener);
        
        $this->mockFirestoreCreate('simple_event_products');
        
        SimpleEventProduct::create(['name' => 'Test 1']);
        expect($events)->toContain('listener_called');
        
        // Clear events and flush listeners
        $events = [];
        SimpleEventProduct::flushEventListeners();
        
        SimpleEventProduct::create(['name' => 'Test 2']);
        expect($events)->toBeEmpty();
    });

    it('supports withoutEvents functionality', function () {
        $events = [];
        
        SimpleEventProduct::creating(function ($model) use (&$events) {
            $events[] = 'creating';
        });
        
        SimpleEventProduct::created(function ($model) use (&$events) {
            $events[] = 'created';
        });
        
        $this->mockFirestoreCreate('simple_event_products');
        
        // Create with events
        SimpleEventProduct::create(['name' => 'With Events']);
        expect($events)->toContain('creating');
        expect($events)->toContain('created');
        
        // Clear events
        $events = [];
        
        // Create without events
        SimpleEventProduct::withoutEvents(function () {
            SimpleEventProduct::create(['name' => 'Without Events']);
        });
        
        expect($events)->toBeEmpty();
    });

    it('provides access to model data in events', function () {
        $eventData = [];
        
        SimpleEventProduct::creating(function ($model) use (&$eventData) {
            $eventData['creating'] = [
                'name' => $model->name,
                'price' => $model->price,
                'exists' => $model->exists,
                'dirty' => $model->isDirty(),
            ];
        });
        
        SimpleEventProduct::created(function ($model) use (&$eventData) {
            $eventData['created'] = [
                'name' => $model->name,
                'price' => $model->price,
                'exists' => $model->exists,
                'dirty' => $model->isDirty(),
            ];
        });
        
        $this->mockFirestoreCreate('simple_event_products');
        
        SimpleEventProduct::create(['name' => 'Data Test', 'price' => 30.00]);
        
        expect($eventData['creating'])->toBe([
            'name' => 'Data Test',
            'price' => 30.00,
            'exists' => false,
            'dirty' => true,
        ]);
        
        expect($eventData['created'])->toBe([
            'name' => 'Data Test',
            'price' => 30.00,
            'exists' => true,
            'dirty' => true, // Model is still dirty during created event
        ]);
    });

    it('supports multiple event listeners for the same event', function () {
        $listener1Called = false;
        $listener2Called = false;
        $listener3Called = false;
        
        SimpleEventProduct::creating(function ($model) use (&$listener1Called) {
            $listener1Called = true;
        });
        
        SimpleEventProduct::creating(function ($model) use (&$listener2Called) {
            $listener2Called = true;
        });
        
        SimpleEventProduct::creating(function ($model) use (&$listener3Called) {
            $listener3Called = true;
        });
        
        $this->mockFirestoreCreate('simple_event_products');
        
        SimpleEventProduct::create(['name' => 'Multi Listener Test']);
        
        expect($listener1Called)->toBeTrue();
        expect($listener2Called)->toBeTrue();
        expect($listener3Called)->toBeTrue();
    });

    it('maintains event order during operations', function () {
        $eventOrder = [];
        
        SimpleEventProduct::saving(function ($model) use (&$eventOrder) {
            $eventOrder[] = 'saving';
        });
        
        SimpleEventProduct::creating(function ($model) use (&$eventOrder) {
            $eventOrder[] = 'creating';
        });
        
        SimpleEventProduct::created(function ($model) use (&$eventOrder) {
            $eventOrder[] = 'created';
        });
        
        SimpleEventProduct::saved(function ($model) use (&$eventOrder) {
            $eventOrder[] = 'saved';
        });
        
        $this->mockFirestoreCreate('simple_event_products');
        
        SimpleEventProduct::create(['name' => 'Order Test']);
        
        expect($eventOrder)->toBe(['saving', 'creating', 'created', 'saved']);
    });
});
