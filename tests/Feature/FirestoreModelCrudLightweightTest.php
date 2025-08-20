<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestCase;

// Lightweight test model for CRUD testing
class LightweightTestProduct extends FirestoreModel
{
    protected ?string $collection = 'lightweight_test_products';
    
    protected array $fillable = ['name', 'price', 'description', 'published'];
    
    protected array $casts = [
        'price' => 'float',
        'published' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

describe('FirestoreModel CRUD - Lightweight Tests', function () {
    beforeEach(function () {
        // Enable lightweight mock to avoid memory issues
        $this->enableLightweightMock();
        $this->clearFirestoreMocks();
        LightweightTestProduct::flushEventListeners();
    });

    describe('Model Creation', function () {
        it('can create a new model instance', function () {
            $product = new LightweightTestProduct([
                'name' => 'Test Product',
                'price' => 19.99,
                'description' => 'A test product',
                'published' => true
            ]);
            
            expect($product->name)->toBe('Test Product');
            expect($product->price)->toBe(19.99);
            expect($product->published)->toBeTrue();
            expect($product->exists)->toBeFalse();
        });

        it('can save a new model to Firestore', function () {
            $product = new LightweightTestProduct([
                'name' => 'New Product',
                'price' => 29.99,
                'published' => false
            ]);
            
            $result = $product->save();
            
            expect($result)->toBeTrue();
            expect($product->exists)->toBeTrue();
            expect($product->wasRecentlyCreated)->toBeTrue();
            expect($product->id)->toBeString();
            
            $this->assertFirestoreOperationCalled('set', 'lightweight_test_products');
        });

        it('can create a model using the create method', function () {
            $product = LightweightTestProduct::create([
                'name' => 'Created Product',
                'price' => 39.99,
                'description' => 'Created via create method',
                'published' => true
            ]);
            
            expect($product)->toBeInstanceOf(LightweightTestProduct::class);
            expect($product->name)->toBe('Created Product');
            expect($product->exists)->toBeTrue();
            expect($product->wasRecentlyCreated)->toBeTrue();
            
            $this->assertFirestoreOperationCalled('set', 'lightweight_test_products');
        });
    });

    describe('Model Retrieval', function () {
        it('can find a model by ID', function () {
            $testData = [
                'id' => 'product-123',
                'name' => 'Found Product',
                'price' => 49.99,
                'published' => true
            ];
            
            $this->mockFirestoreGet('lightweight_test_products', 'product-123', $testData);
            
            $product = LightweightTestProduct::find('product-123');
            
            expect($product)->toBeInstanceOf(LightweightTestProduct::class);
            expect($product->id)->toBe('product-123');
            expect($product->name)->toBe('Found Product');
            expect($product->price)->toBe(49.99);
            expect($product->exists)->toBeTrue();
        });

        it('returns null when model is not found', function () {
            $this->mockFirestoreGet('lightweight_test_products', 'missing-product', null);
            
            $product = LightweightTestProduct::find('missing-product');
            
            expect($product)->toBeNull();
        });

        it('can get all models', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product 1', 'price' => 10.00],
                ['id' => '2', 'name' => 'Product 2', 'price' => 20.00],
                ['id' => '3', 'name' => 'Product 3', 'price' => 30.00],
            ];
            
            $this->mockFirestoreQuery('lightweight_test_products', $testData);
            
            $products = LightweightTestProduct::all();
            
            expect($products)->toHaveCount(3);
            expect($products->first())->toBeInstanceOf(LightweightTestProduct::class);
            expect($products->first()->name)->toBe('Product 1');
        });
    });

    describe('Model Updates', function () {
        it('can update a model', function () {
            $testData = [
                'id' => 'product-456',
                'name' => 'Original Name',
                'price' => 59.99,
                'published' => false
            ];
            
            $this->mockFirestoreGet('lightweight_test_products', 'product-456', $testData);
            
            $product = LightweightTestProduct::find('product-456');
            $product->name = 'Updated Name';
            $product->published = true;
            
            expect($product->isDirty())->toBeTrue();
            expect($product->isDirty('name'))->toBeTrue();
            expect($product->isDirty('published'))->toBeTrue();
            
            $result = $product->save();
            
            expect($result)->toBeTrue();
            expect($product->name)->toBe('Updated Name');
            expect($product->published)->toBeTrue();
            
            $this->assertFirestoreOperationCalled('update', 'lightweight_test_products', 'product-456');
        });

        it('can update using the update method', function () {
            $testData = [
                'id' => 'product-789',
                'name' => 'Update Test',
                'price' => 69.99
            ];
            
            $this->mockFirestoreGet('lightweight_test_products', 'product-789', $testData);
            
            $product = LightweightTestProduct::find('product-789');
            $result = $product->update(['name' => 'Mass Updated', 'price' => 79.99]);
            
            expect($result)->toBeTrue();
            expect($product->name)->toBe('Mass Updated');
            expect($product->price)->toBe(79.99);
            
            $this->assertFirestoreOperationCalled('update', 'lightweight_test_products', 'product-789');
        });
    });

    describe('Model Deletion', function () {
        it('can delete a model', function () {
            $testData = [
                'id' => 'product-delete',
                'name' => 'To Be Deleted',
                'price' => 99.99
            ];
            
            $this->mockFirestoreGet('lightweight_test_products', 'product-delete', $testData);
            
            $product = LightweightTestProduct::find('product-delete');
            
            expect($product->exists)->toBeTrue();
            
            $result = $product->delete();
            
            expect($result)->toBeTrue();
            expect($product->exists)->toBeFalse();
            
            $this->assertFirestoreOperationCalled('delete', 'lightweight_test_products', 'product-delete');
        });
    });

    describe('Query Operations', function () {
        it('can query with where clauses', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product 1', 'published' => true, 'price' => 10.00],
                ['id' => '2', 'name' => 'Product 2', 'published' => false, 'price' => 20.00],
                ['id' => '3', 'name' => 'Product 3', 'published' => true, 'price' => 30.00],
            ];
            
            $this->mockFirestoreQuery('lightweight_test_products', $testData);
            
            $products = LightweightTestProduct::where('published', true)->get();
            
            expect($products)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            
            $this->assertFirestoreQueryExecuted('lightweight_test_products');
        });

        it('can query with ordering', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'price' => 30.00],
                ['id' => '2', 'name' => 'Product B', 'price' => 10.00],
                ['id' => '3', 'name' => 'Product C', 'price' => 20.00],
            ];
            
            $this->mockFirestoreQuery('lightweight_test_products', $testData);
            
            $products = LightweightTestProduct::orderBy('price', 'desc')->get();
            
            expect($products)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            
            $this->assertFirestoreQueryExecuted('lightweight_test_products');
        });

        it('can query with limits', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product 1'],
                ['id' => '2', 'name' => 'Product 2'],
                ['id' => '3', 'name' => 'Product 3'],
                ['id' => '4', 'name' => 'Product 4'],
                ['id' => '5', 'name' => 'Product 5'],
            ];
            
            $this->mockFirestoreQuery('lightweight_test_products', $testData);
            
            $products = LightweightTestProduct::limit(3)->get();
            
            expect($products)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            
            $this->assertFirestoreQueryExecuted('lightweight_test_products');
        });

        it('can count models', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product 1'],
                ['id' => '2', 'name' => 'Product 2'],
                ['id' => '3', 'name' => 'Product 3'],
            ];
            
            $this->mockFirestoreQuery('lightweight_test_products', $testData);
            
            $count = LightweightTestProduct::count();
            
            expect($count)->toBe(3);
            
            $this->assertFirestoreQueryExecuted('lightweight_test_products');
        });
    });

    describe('Model Attributes', function () {
        it('handles attribute casting correctly', function () {
            $product = new LightweightTestProduct([
                'name' => 'Cast Test',
                'price' => '49.99', // String that should be cast to float
                'published' => 'true' // String that should be cast to boolean
            ]);
            
            expect($product->price)->toBe(49.99);
            expect($product->price)->toBeFloat();
            expect($product->published)->toBeTrue();
            expect($product->published)->toBeBool();
        });

        it('tracks dirty attributes correctly', function () {
            $product = new LightweightTestProduct([
                'name' => 'Dirty Test',
                'price' => 29.99
            ]);
            
            expect($product->isDirty())->toBeTrue();
            expect($product->isDirty('name'))->toBeTrue();
            expect($product->isDirty('price'))->toBeTrue();
            expect($product->isDirty('description'))->toBeFalse();
            
            // Simulate saving
            $product->save();
            
            expect($product->isDirty())->toBeFalse();
            
            // Make changes
            $product->name = 'Changed Name';
            
            expect($product->isDirty())->toBeTrue();
            expect($product->isDirty('name'))->toBeTrue();
            expect($product->isDirty('price'))->toBeFalse();
        });
    });

    describe('Mass Assignment', function () {
        it('respects fillable attributes', function () {
            $product = new LightweightTestProduct([
                'name' => 'Fillable Test',
                'price' => 19.99,
                'description' => 'Test description',
                'published' => true,
                'secret_field' => 'should not be set' // Not in fillable
            ]);
            
            expect($product->name)->toBe('Fillable Test');
            expect($product->price)->toBe(19.99);
            expect($product->description)->toBe('Test description');
            expect($product->published)->toBeTrue();
            expect($product->getAttribute('secret_field'))->toBeNull();
        });
    });
});
