<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use Illuminate\Support\Facades\Event;

// Test model for event testing
class TestEventProduct extends FirestoreModel
{
    protected ?string $collection = 'event_products';
    
    protected array $fillable = [
        'name', 'price', 'description', 'active'
    ];
    
    protected array $casts = [
        'price' => 'float',
        'active' => 'boolean',
    ];
}

// Test observer for event testing
class TestProductObserver
{
    public array $events = [];
    
    public function creating($model)
    {
        $this->events[] = ['creating', $model->name];
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
}

describe('FirestoreModel Events', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        Event::fake();
        
        // Reset any existing event listeners
        TestEventProduct::flushEventListeners();
    });

    describe('Model Event Registration', function () {
        it('can register event listeners', function () {
            $events = [];
            
            TestEventProduct::creating(function ($model) use (&$events) {
                $events[] = 'creating';
            });
            
            TestEventProduct::created(function ($model) use (&$events) {
                $events[] = 'created';
            });
            
            $this->mockFirestoreCreate('event_products');
            
            TestEventProduct::create(['name' => 'Test Product']);
            
            expect($events)->toBe(['creating', 'created']);
        });

        it('can register multiple listeners for same event', function () {
            $events = [];
            
            TestEventProduct::creating(function ($model) use (&$events) {
                $events[] = 'listener1';
            });
            
            TestEventProduct::creating(function ($model) use (&$events) {
                $events[] = 'listener2';
            });
            
            $this->mockFirestoreCreate('event_products');
            
            TestEventProduct::create(['name' => 'Test Product']);
            
            expect($events)->toContain('listener1');
            expect($events)->toContain('listener2');
        });
    });

    describe('Creating Events', function () {
        it('fires creating and created events on model creation', function () {
            $events = [];
            
            TestEventProduct::creating(function ($model) use (&$events) {
                $events[] = ['creating', $model->name, $model->exists];
            });
            
            TestEventProduct::created(function ($model) use (&$events) {
                $events[] = ['created', $model->name, $model->exists];
            });
            
            $this->mockFirestoreCreate('event_products');
            
            $product = TestEventProduct::create(['name' => 'New Product']);
            
            expect($events)->toHaveCount(2);
            expect($events[0])->toBe(['creating', 'New Product', false]);
            expect($events[1])->toBe(['created', 'New Product', true]);
        });

        it('can cancel creation by returning false from creating event', function () {
            TestEventProduct::creating(function ($model) {
                return false; // Cancel creation
            });
            
            $this->mockFirestoreCreate('event_products');
            
            $product = new TestEventProduct(['name' => 'Cancelled Product']);
            $result = $product->save();
            
            expect($result)->toBeFalse();
            expect($product->exists)->toBeFalse();
        });
    });

    describe('Updating Events', function () {
        it('fires updating and updated events on model update', function () {
            $events = [];
            
            TestEventProduct::updating(function ($model) use (&$events) {
                $events[] = ['updating', $model->name];
            });
            
            TestEventProduct::updated(function ($model) use (&$events) {
                $events[] = ['updated', $model->name];
            });
            
            $this->mockFirestoreGet('event_products', '1', [
                'id' => '1',
                'name' => 'Original Product',
                'price' => 10.00
            ]);
            $this->mockFirestoreUpdate('event_products', '1');
            
            $product = TestEventProduct::find('1');
            $product->name = 'Updated Product';
            $product->save();
            
            expect($events)->toHaveCount(2);
            expect($events[0])->toBe(['updating', 'Updated Product']);
            expect($events[1])->toBe(['updated', 'Updated Product']);
        });

        it('can cancel update by returning false from updating event', function () {
            TestEventProduct::updating(function ($model) {
                return false; // Cancel update
            });
            
            $this->mockFirestoreGet('event_products', '1', [
                'id' => '1',
                'name' => 'Original Product',
                'price' => 10.00
            ]);
            
            $product = TestEventProduct::find('1');
            $product->name = 'Should Not Update';
            $result = $product->save();
            
            expect($result)->toBeFalse();
        });
    });

    describe('Deleting Events', function () {
        it('fires deleting and deleted events on model deletion', function () {
            $events = [];
            
            TestEventProduct::deleting(function ($model) use (&$events) {
                $events[] = ['deleting', $model->name, $model->exists];
            });
            
            TestEventProduct::deleted(function ($model) use (&$events) {
                $events[] = ['deleted', $model->name, $model->exists];
            });
            
            $this->mockFirestoreGet('event_products', '1', [
                'id' => '1',
                'name' => 'Delete Me',
                'price' => 10.00
            ]);
            $this->mockFirestoreDelete('event_products', '1');
            
            $product = TestEventProduct::find('1');
            $product->delete();
            
            expect($events)->toHaveCount(2);
            expect($events[0])->toBe(['deleting', 'Delete Me', true]);
            expect($events[1])->toBe(['deleted', 'Delete Me', false]);
        });

        it('can cancel deletion by returning false from deleting event', function () {
            TestEventProduct::deleting(function ($model) {
                return false; // Cancel deletion
            });
            
            $this->mockFirestoreGet('event_products', '1', [
                'id' => '1',
                'name' => 'Protected Product',
                'price' => 10.00
            ]);
            
            $product = TestEventProduct::find('1');
            $result = $product->delete();
            
            expect($result)->toBeFalse();
            expect($product->exists)->toBeTrue();
        });
    });

    describe('Saving and Saved Events', function () {
        it('fires saving and saved events on any save operation', function () {
            $events = [];
            
            TestEventProduct::saving(function ($model) use (&$events) {
                $events[] = ['saving', $model->name];
            });
            
            TestEventProduct::saved(function ($model) use (&$events) {
                $events[] = ['saved', $model->name];
            });
            
            $this->mockFirestoreCreate('event_products');
            
            TestEventProduct::create(['name' => 'Save Test']);
            
            expect($events)->toContain(['saving', 'Save Test']);
            expect($events)->toContain(['saved', 'Save Test']);
        });

        it('can cancel save by returning false from saving event', function () {
            TestEventProduct::saving(function ($model) {
                return false; // Cancel save
            });
            
            $product = new TestEventProduct(['name' => 'No Save']);
            $result = $product->save();
            
            expect($result)->toBeFalse();
            expect($product->exists)->toBeFalse();
        });
    });

    describe('Retrieved Events', function () {
        it('fires retrieved event when model is loaded from database', function () {
            $events = [];
            
            TestEventProduct::retrieved(function ($model) use (&$events) {
                $events[] = ['retrieved', $model->name];
            });
            
            $this->mockFirestoreGet('event_products', '1', [
                'id' => '1',
                'name' => 'Retrieved Product',
                'price' => 10.00
            ]);
            
            $product = TestEventProduct::find('1');
            
            expect($events)->toContain(['retrieved', 'Retrieved Product']);
        });
    });

    describe('Observer Pattern', function () {
        it('can register observers', function () {
            $observer = new TestProductObserver();
            TestEventProduct::observe($observer);
            
            $this->mockFirestoreCreate('event_products');
            
            TestEventProduct::create(['name' => 'Observer Test']);
            
            expect($observer->events)->toContain(['creating', 'Observer Test']);
            expect($observer->events)->toContain(['created', 'Observer Test']);
            expect($observer->events)->toContain(['saving', 'Observer Test']);
            expect($observer->events)->toContain(['saved', 'Observer Test']);
        });
    });

    describe('Event Control', function () {
        it('can disable events with withoutEvents', function () {
            $events = [];
            
            TestEventProduct::creating(function ($model) use (&$events) {
                $events[] = 'creating';
            });
            
            $this->mockFirestoreCreate('event_products');
            
            TestEventProduct::withoutEvents(function () {
                TestEventProduct::create(['name' => 'Silent Product']);
            });
            
            expect($events)->toBeEmpty();
        });

        it('can save quietly without events', function () {
            $events = [];
            
            TestEventProduct::creating(function ($model) use (&$events) {
                $events[] = 'creating';
            });
            
            $this->mockFirestoreCreate('event_products');
            
            $product = new TestEventProduct(['name' => 'Quiet Product']);
            $product->saveQuietly();
            
            expect($events)->toBeEmpty();
        });

        it('can delete quietly without events', function () {
            $events = [];
            
            TestEventProduct::deleting(function ($model) use (&$events) {
                $events[] = 'deleting';
            });
            
            $this->mockFirestoreGet('event_products', '1', [
                'id' => '1',
                'name' => 'Quiet Delete',
                'price' => 10.00
            ]);
            $this->mockFirestoreDelete('event_products', '1');
            
            $product = TestEventProduct::find('1');
            $product->deleteQuietly();
            
            expect($events)->toBeEmpty();
        });
    });
});
