<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use Illuminate\Support\Carbon;

// Test model for CRUD testing
class TestProduct extends FirestoreModel
{
    protected ?string $collection = 'products';

    protected array $fillable = [
        'name', 'price', 'description', 'active', 'category_id'
    ];

    protected array $casts = [
        'price' => 'float',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

describe('FirestoreModel CRUD Operations', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
    });

    describe('Create Operations', function () {
        it('can create a new model with auto-generated ID', function () {
            $this->mockFirestoreCreate('products');
            
            $product = TestProduct::create([
                'name' => 'Test Product',
                'price' => 29.99,
                'description' => 'A test product',
                'active' => true
            ]);
            
            expect($product)->toBeFirestoreModel();
            expect($product->name)->toBe('Test Product');
            expect($product->price)->toBe(29.99);
            expect($product->active)->toBe(true);
            expect($product)->toExistInFirestore();
            expect($product)->toBeRecentlyCreated();
            
            $this->assertFirestoreOperationCalled('create', 'products');
        });

        it('can create a new model with specific ID', function () {
            $this->mockFirestoreCreate('products', 'custom-id-123');
            
            $product = new TestProduct([
                'name' => 'Custom ID Product',
                'price' => 49.99,
                'active' => true
            ]);
            $product->id = 'custom-id-123';
            $product->save();
            
            expect($product)->toBeFirestoreModel();
            expect($product->id)->toBe('custom-id-123');
            expect($product->name)->toBe('Custom ID Product');
            expect($product)->toExistInFirestore();
            expect($product)->toBeRecentlyCreated();
        });

        it('can use firstOrCreate', function () {
            // First call - should create
            $this->mockFirestoreCreate('products');
            
            $product1 = TestProduct::firstOrCreate(
                ['name' => 'Unique Product'],
                ['price' => 19.99, 'active' => true]
            );
            
            expect($product1)->toBeRecentlyCreated();
            expect($product1->name)->toBe('Unique Product');
            expect($product1->price)->toBe(19.99);
        });

        it('can use updateOrCreate', function () {
            $this->mockFirestoreCreate('products');
            
            $product = TestProduct::updateOrCreate(
                ['name' => 'Update Product'],
                ['price' => 39.99, 'description' => 'Updated description']
            );
            
            expect($product)->toBeFirestoreModel();
            expect($product->name)->toBe('Update Product');
            expect($product->price)->toBe(39.99);
            expect($product->description)->toBe('Updated description');
        });
    });

    describe('Read Operations', function () {
        beforeEach(function () {
            $this->mockFirestoreQuery('products', [
                ['id' => '1', 'name' => 'Product 1', 'price' => 10.00, 'active' => true],
                ['id' => '2', 'name' => 'Product 2', 'price' => 20.00, 'active' => false],
                ['id' => '3', 'name' => 'Product 3', 'price' => 30.00, 'active' => true],
            ]);
        });

        it('can find a model by ID', function () {
            $this->mockFirestoreGet('products', '1', [
                'id' => '1',
                'name' => 'Product 1',
                'price' => 10.00,
                'active' => true
            ]);
            
            $product = TestProduct::find('1');
            
            expect($product)->toBeFirestoreModel();
            expect($product->id)->toBe('1');
            expect($product->name)->toBe('Product 1');
            expect($product->price)->toBe(10.00);
            expect($product->active)->toBe(true);
        });

        it('can find multiple models by IDs', function () {
            $products = TestProduct::findMany(['1', '3']);
            
            expect($products)->toHaveCount(2);
            expect($products->pluck('id')->toArray())->toBe(['1', '3']);
        });

        it('can get all models', function () {
            $products = TestProduct::all();
            
            expect($products)->toHaveCount(3);
            expect($products->first())->toBeFirestoreModel();
        });

        it('can get first model', function () {
            $product = TestProduct::first();
            
            expect($product)->toBeFirestoreModel();
            expect($product->name)->toBe('Product 1');
        });

        it('can find or fail', function () {
            $this->mockFirestoreGet('products', '1', [
                'id' => '1',
                'name' => 'Product 1'
            ]);
            
            $product = TestProduct::findOrFail('1');
            expect($product)->toBeFirestoreModel();
            
            $this->mockFirestoreGet('products', 'nonexistent', null);
            
            expect(fn() => TestProduct::findOrFail('nonexistent'))
                ->toThrow(\Illuminate\Database\RecordNotFoundException::class);
        });
    });

    describe('Update Operations', function () {
        it('can update a model instance', function () {
            $this->mockFirestoreGet('products', '1', [
                'id' => '1',
                'name' => 'Product 1',
                'price' => 10.00,
                'active' => true
            ]);
            $this->mockFirestoreUpdate('products', '1');
            
            $product = TestProduct::find('1');
            $product->name = 'Updated Product 1';
            $product->price = 15.00;
            
            expect($product)->toBeDirty(['name', 'price']);
            
            $result = $product->save();
            
            expect($result)->toBeTrue();
            expect($product)->toBeClean();
            expect($product->name)->toBe('Updated Product 1');
            expect($product->price)->toBe(15.00);
            
            $this->assertFirestoreOperationCalled('update', 'products', '1');
        });

        it('can update multiple models with query', function () {
            $this->mockFirestoreUpdate('products', '1');
            $this->mockFirestoreUpdate('products', '3');
            
            $updated = TestProduct::where('active', true)
                ->update(['price' => 25.00]);
            
            expect($updated)->toBe(2);
            $this->assertFirestoreOperationCalled('update', 'products');
        });

        it('can touch a model to update timestamps', function () {
            $this->mockFirestoreGet('products', '1', [
                'id' => '1',
                'name' => 'Product 1',
                'created_at' => '2023-01-01 12:00:00',
                'updated_at' => '2023-01-01 12:00:00'
            ]);
            $this->mockFirestoreUpdate('products', '1');
            
            $product = TestProduct::find('1');
            
            $this->freezeTimeAt('2023-01-02 12:00:00');
            
            $result = $product->touch();
            
            expect($result)->toBeTrue();
            $this->assertFirestoreOperationCalled('update', 'products', '1');
        });
    });

    describe('Delete Operations', function () {
        it('can delete a model instance', function () {
            $this->mockFirestoreGet('products', '1', [
                'id' => '1',
                'name' => 'Product 1',
                'price' => 10.00
            ]);
            $this->mockFirestoreDelete('products', '1');
            
            $product = TestProduct::find('1');
            
            expect($product->exists)->toBeTrue();
            
            $result = $product->delete();
            
            expect($result)->toBeTrue();
            expect($product->exists)->toBeFalse();
            
            $this->assertFirestoreOperationCalled('delete', 'products', '1');
        });

        it('can delete multiple models with query', function () {
            $this->mockFirestoreDelete('products', '2');
            
            $deleted = TestProduct::where('active', false)->delete();
            
            expect($deleted)->toBe(1);
            $this->assertFirestoreOperationCalled('delete', 'products');
        });

        it('can delete quietly without events', function () {
            $this->mockFirestoreGet('products', '1', [
                'id' => '1',
                'name' => 'Product 1'
            ]);
            $this->mockFirestoreDelete('products', '1');
            
            $product = TestProduct::find('1');
            
            $result = $product->deleteQuietly();
            
            expect($result)->toBeTrue();
            expect($product->exists)->toBeFalse();
        });
    });

    describe('Timestamps', function () {
        it('automatically sets timestamps on create', function () {
            $this->mockFirestoreCreate('products');
            $this->freezeTimeAt('2023-01-01 12:00:00');
            
            $product = TestProduct::create([
                'name' => 'Timestamped Product',
                'price' => 19.99
            ]);
            
            expect($product->created_at)->toBeInstanceOf(Carbon::class);
            expect($product->updated_at)->toBeInstanceOf(Carbon::class);
            expect($product->created_at->format('Y-m-d H:i:s'))->toBe('2023-01-01 12:00:00');
        });

        it('updates timestamps on save', function () {
            $this->mockFirestoreGet('products', '1', [
                'id' => '1',
                'name' => 'Product 1',
                'created_at' => '2023-01-01 12:00:00',
                'updated_at' => '2023-01-01 12:00:00'
            ]);
            $this->mockFirestoreUpdate('products', '1');
            
            $product = TestProduct::find('1');
            
            $this->freezeTimeAt('2023-01-02 12:00:00');
            
            $product->name = 'Updated Product';
            $product->save();
            
            expect($product->updated_at->format('Y-m-d H:i:s'))->toBe('2023-01-02 12:00:00');
        });
    });

    describe('Model State', function () {
        it('tracks model existence correctly', function () {
            $product = new TestProduct(['name' => 'New Product']);
            
            expect($product->exists)->toBeFalse();
            expect($product->wasRecentlyCreated)->toBeFalse();
            
            $this->mockFirestoreCreate('products');
            $product->save();
            
            expect($product->exists)->toBeTrue();
            expect($product->wasRecentlyCreated)->toBeTrue();
        });

        it('tracks dirty attributes correctly', function () {
            $this->mockFirestoreGet('products', '1', [
                'id' => '1',
                'name' => 'Product 1',
                'price' => 10.00
            ]);
            
            $product = TestProduct::find('1');
            
            expect($product)->toBeClean();
            
            $product->name = 'Updated Name';
            
            expect($product)->toBeDirty();
            expect($product)->toBeDirty('name');
            expect($product)->toBeClean('price');
        });
    });
});
