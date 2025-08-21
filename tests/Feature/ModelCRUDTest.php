<?php

namespace JTD\FirebaseModels\Tests\Feature\Restructured;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\FeatureTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Carbon;

/**
 * Comprehensive Model CRUD Operations Feature Test
 * 
 * Consolidated from:
 * - tests/Feature/FirestoreModelCRUDTest.php
 * - tests/Feature/FirestoreModelCrudLightweightTest.php
 * 
 * Uses new FeatureTestSuite for comprehensive end-to-end CRUD testing.
 */

// Test model for comprehensive CRUD testing
class CRUDTestProduct extends FirestoreModel
{
    protected ?string $collection = 'crud_test_products';

    protected array $fillable = [
        'name', 'price', 'description', 'active', 'category_id', 'published', 'tags'
    ];

    protected array $casts = [
        'price' => 'float',
        'active' => 'boolean',
        'published' => 'boolean',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    protected array $hidden = [
        'secret_field'
    ];
}

class ModelCRUDTest extends FeatureTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for comprehensive CRUD testing
        $this->setTestRequirements([
            'document_count' => 200,
            'memory_constraint' => false,
            'needs_full_mockery' => true,
        ]);

        parent::setUp();
        
        // Clear any existing event listeners
        CRUDTestProduct::flushEventListeners();
    }

    // ========================================
    // CREATE OPERATIONS
    // ========================================

    #[Test]
    public function it_can_create_models_with_various_methods()
    {
        // Test basic model creation with auto-generated ID
        $productData = TestDataFactory::createProduct([
            'name' => 'Test Product',
            'price' => 29.99,
            'description' => 'A comprehensive test product',
            'active' => true,
            'published' => true
        ]);

        $this->mockFirestoreCreate('crud_test_products');
        
        $product = CRUDTestProduct::create($productData);
        
        expect($product)->toBeFirestoreModel();
        expect($product->name)->toBe('Test Product');
        expect($product->price)->toBe(29.99);
        expect($product->active)->toBe(true);
        expect($product->published)->toBe(true);
        expect($product)->toExistInFirestore();
        expect($product)->toBeRecentlyCreated();
        
        $this->assertFirestoreOperationCalled('create', 'crud_test_products');

        // Test model creation with specific ID
        $customProductData = TestDataFactory::createProduct([
            'name' => 'Custom ID Product',
            'price' => 49.99,
            'active' => true
        ]);

        $this->mockFirestoreCreate('crud_test_products', 'custom-id-123');
        
        $customProduct = new CRUDTestProduct($customProductData);
        $customProduct->id = 'custom-id-123';
        $result = $customProduct->save();
        
        expect($result)->toBeTrue();
        expect($customProduct->id)->toBe('custom-id-123');
        expect($customProduct->name)->toBe('Custom ID Product');
        expect($customProduct)->toExistInFirestore();
        expect($customProduct)->toBeRecentlyCreated();

        // Test new model instance creation
        $newProduct = new CRUDTestProduct([
            'name' => 'New Instance Product',
            'price' => 19.99,
            'published' => false
        ]);
        
        expect($newProduct->name)->toBe('New Instance Product');
        expect($newProduct->price)->toBe(19.99);
        expect($newProduct->published)->toBeFalse();
        expect($newProduct->exists)->toBeFalse();
        expect($newProduct->wasRecentlyCreated)->toBeFalse();
    }

    #[Test]
    public function it_handles_advanced_creation_methods()
    {
        // Test firstOrCreate
        $this->mockFirestoreQuery('crud_test_products', []);
        $this->mockFirestoreCreate('crud_test_products');
        
        $product1 = CRUDTestProduct::firstOrCreate(
            ['name' => 'Unique Product'],
            ['price' => 19.99, 'active' => true, 'published' => true]
        );
        
        expect($product1)->toBeRecentlyCreated();
        expect($product1->name)->toBe('Unique Product');
        expect($product1->price)->toBe(19.99);
        expect($product1->active)->toBe(true);

        // Test updateOrCreate
        $this->mockFirestoreQuery('crud_test_products', []);
        $this->mockFirestoreCreate('crud_test_products');
        
        $product2 = CRUDTestProduct::updateOrCreate(
            ['name' => 'Update Product'],
            ['price' => 39.99, 'description' => 'Updated description', 'published' => true]
        );
        
        expect($product2)->toBeFirestoreModel();
        expect($product2->name)->toBe('Update Product');
        expect($product2->price)->toBe(39.99);
        expect($product2->description)->toBe('Updated description');
        expect($product2->published)->toBe(true);
    }

    // ========================================
    // READ OPERATIONS
    // ========================================

    #[Test]
    public function it_can_retrieve_models_with_various_methods()
    {
        // Create test data
        $testProducts = $this->createFeatureTestData('products', 5);
        $this->mockComplexQueryScenario('crud_test_products', $testProducts);

        // Test find by ID
        $specificProduct = [
            'id' => 'product-123',
            'name' => 'Found Product',
            'price' => 49.99,
            'active' => true,
            'published' => true
        ];
        
        $this->mockFirestoreGet('crud_test_products', 'product-123', $specificProduct);
        
        $product = CRUDTestProduct::find('product-123');
        
        expect($product)->toBeInstanceOf(CRUDTestProduct::class);
        expect($product->id)->toBe('product-123');
        expect($product->name)->toBe('Found Product');
        expect($product->price)->toBe(49.99);
        expect($product->exists)->toBeTrue();

        // Test find returns null for missing product
        $this->mockFirestoreGet('crud_test_products', 'missing-product', null);
        
        $missingProduct = CRUDTestProduct::find('missing-product');
        expect($missingProduct)->toBeNull();

        // Test get all models
        $allProducts = CRUDTestProduct::all();
        
        expect($allProducts)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($allProducts)->toHaveCount(5);
        expect($allProducts->first())->toBeInstanceOf(CRUDTestProduct::class);

        // Test first model
        $firstProduct = CRUDTestProduct::first();
        expect($firstProduct)->toBeInstanceOf(CRUDTestProduct::class);
        expect($firstProduct->name)->toBeString();

        // Test where queries
        $activeProducts = CRUDTestProduct::where('active', true)->get();
        expect($activeProducts)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    }

    #[Test]
    public function it_handles_query_operations_and_filtering()
    {
        // Create realistic test dataset
        $testData = $this->createRealisticDataset('crud_test_products', 20);
        $this->mockComplexQueryScenario('crud_test_products', $testData);

        // Test complex where conditions
        $filteredProducts = CRUDTestProduct::where('active', true)
            ->where('price', '>', 25.00)
            ->orderBy('price', 'desc')
            ->limit(5)
            ->get();
        
        expect($filteredProducts)->toBeInstanceOf(\Illuminate\Support\Collection::class);

        // Test count operations
        $totalCount = CRUDTestProduct::count();
        expect($totalCount)->toBe(20);

        // Test exists check
        $hasProducts = CRUDTestProduct::exists();
        expect($hasProducts)->toBeTrue();

        // Test pluck operation
        $productNames = CRUDTestProduct::pluck('name');
        expect($productNames)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    }

    // ========================================
    // UPDATE OPERATIONS
    // ========================================

    #[Test]
    public function it_can_update_models_comprehensively()
    {
        // Create and retrieve a product for updating
        $originalData = [
            'id' => 'update-test-1',
            'name' => 'Original Product',
            'price' => 25.00,
            'description' => 'Original description',
            'active' => true,
            'published' => false
        ];
        
        $this->mockFirestoreGet('crud_test_products', 'update-test-1', $originalData);
        $this->mockFirestoreUpdate('crud_test_products', 'update-test-1');
        
        $product = CRUDTestProduct::find('update-test-1');
        
        // Test individual attribute updates
        expect($product)->toBeClean();
        
        $product->name = 'Updated Product Name';
        $product->price = 35.00;
        $product->published = true;
        
        expect($product)->toBeDirty();
        expect($product)->toBeDirty(['name', 'price', 'published']);
        expect($product)->toBeClean(['description', 'active']);
        
        $result = $product->save();
        
        expect($result)->toBeTrue();
        expect($product)->toBeClean();
        expect($product->name)->toBe('Updated Product Name');
        expect($product->price)->toBe(35.00);
        expect($product->published)->toBe(true);
        
        $this->assertFirestoreOperationCalled('update', 'crud_test_products', 'update-test-1');

        // Test mass update
        $massUpdateData = [
            'description' => 'Mass updated description',
            'active' => false,
            'tags' => ['updated', 'mass-update']
        ];
        
        $updateResult = $product->update($massUpdateData);
        
        expect($updateResult)->toBeTrue();
        expect($product->description)->toBe('Mass updated description');
        expect($product->active)->toBe(false);
        expect($product->tags)->toBe(['updated', 'mass-update']);
    }

    #[Test]
    public function it_handles_dirty_tracking_and_change_detection()
    {
        $productData = [
            'id' => 'dirty-test-1',
            'name' => 'Dirty Test Product',
            'price' => 20.00,
            'active' => true
        ];
        
        $this->mockFirestoreGet('crud_test_products', 'dirty-test-1', $productData);
        
        $product = CRUDTestProduct::find('dirty-test-1');
        
        // Initially clean
        expect($product)->toBeClean();
        expect($product->getDirty())->toBeEmpty();
        expect($product->getChanges())->toBeEmpty();
        
        // Make changes and test dirty tracking
        $product->name = 'Updated Dirty Test';
        $product->price = 25.00;
        
        expect($product)->toBeDirty();
        expect($product->isDirty('name'))->toBeTrue();
        expect($product->isDirty('price'))->toBeTrue();
        expect($product->isDirty('active'))->toBeFalse();
        expect($product->isClean('active'))->toBeTrue();
        
        // Test original values
        expect($product->getOriginal('name'))->toBe('Dirty Test Product');
        expect($product->getOriginal('price'))->toBe(20.00);
        
        // Save and verify changes are tracked
        $this->mockFirestoreUpdate('crud_test_products', 'dirty-test-1');
        
        $saveResult = $product->save();
        
        expect($saveResult)->toBeTrue();
        expect($product)->toBeClean();
        expect($product->getChanges())->toHaveKeys(['name', 'price']);
    }

    // ========================================
    // DELETE OPERATIONS
    // ========================================

    #[Test]
    public function it_can_delete_models_comprehensively()
    {
        // Test single model deletion
        $deleteData = [
            'id' => 'delete-test-1',
            'name' => 'Product to Delete',
            'price' => 15.00,
            'active' => true
        ];
        
        $this->mockFirestoreGet('crud_test_products', 'delete-test-1', $deleteData);
        $this->mockFirestoreDelete('crud_test_products', 'delete-test-1');
        
        $product = CRUDTestProduct::find('delete-test-1');
        
        expect($product->exists)->toBeTrue();
        
        $deleteResult = $product->delete();
        
        expect($deleteResult)->toBeTrue();
        expect($product->exists)->toBeFalse();
        
        $this->assertFirestoreOperationCalled('delete', 'crud_test_products', 'delete-test-1');

        // Test query-based deletion
        $this->mockFirestoreDelete('crud_test_products');
        
        $deletedCount = CRUDTestProduct::where('active', false)->delete();
        
        expect($deletedCount)->toBeGreaterThanOrEqual(0);
        $this->assertFirestoreOperationCalled('delete', 'crud_test_products');
    }

    // ========================================
    // MODEL STATE AND ATTRIBUTES
    // ========================================

    #[Test]
    public function it_manages_model_state_correctly()
    {
        // Test new model state
        $newProduct = new CRUDTestProduct(['name' => 'New State Product']);
        
        expect($newProduct->exists)->toBeFalse();
        expect($newProduct->wasRecentlyCreated)->toBeFalse();
        
        // Test after save state
        $this->mockFirestoreCreate('crud_test_products');
        $newProduct->save();
        
        expect($newProduct->exists)->toBeTrue();
        expect($newProduct->wasRecentlyCreated)->toBeTrue();

        // Test model timestamps
        expect($newProduct->usesTimestamps())->toBeTrue();
        expect($newProduct->getCreatedAtColumn())->toBe('created_at');
        expect($newProduct->getUpdatedAtColumn())->toBe('updated_at');
    }

    #[Test]
    public function it_handles_mass_assignment_and_fillable_attributes()
    {
        // Test fillable attributes
        $product = new CRUDTestProduct([
            'name' => 'Fillable Test',
            'price' => 19.99,
            'description' => 'Test description',
            'active' => true,
            'published' => true,
            'secret_field' => 'should not be set' // Not in fillable
        ]);
        
        expect($product->name)->toBe('Fillable Test');
        expect($product->price)->toBe(19.99);
        expect($product->description)->toBe('Test description');
        expect($product->active)->toBe(true);
        expect($product->published)->toBe(true);
        expect($product->getAttribute('secret_field'))->toBeNull();

        // Test force fill for protected attributes
        $product->forceFill(['secret_field' => 'force filled']);
        expect($product->getAttribute('secret_field'))->toBe('force filled');

        // Test fillable checks
        expect($product->isFillable('name'))->toBeTrue();
        expect($product->isFillable('price'))->toBeTrue();
        expect($product->isFillable('secret_field'))->toBeFalse();
    }

    #[Test]
    public function it_handles_attribute_casting_and_serialization()
    {
        // Test attribute casting
        $product = new CRUDTestProduct([
            'price' => '29.99', // String to float
            'active' => '1',    // String to boolean
            'published' => 'true', // String to boolean
            'tags' => '["tag1", "tag2"]' // JSON string to array
        ]);
        
        expect($product->price)->toBe(29.99);
        expect($product->active)->toBe(true);
        expect($product->published)->toBe(true);
        expect($product->tags)->toBe(['tag1', 'tag2']);

        // Test serialization
        $array = $product->toArray();
        expect($array)->toHaveKeys(['name', 'price', 'active', 'published', 'tags']);
        expect($array)->not->toHaveKey('secret_field'); // Should be hidden
        
        $json = $product->toJson();
        $decoded = json_decode($json, true);
        expect($decoded['price'])->toBe(29.99);
        expect($decoded['active'])->toBe(true);
    }

    #[Test]
    public function it_performs_comprehensive_crud_scenario()
    {
        // Perform a complete CRUD scenario
        $scenarioMetrics = $this->performFeatureScenario('comprehensive_crud', function () {
            // Create multiple products
            $products = [];
            for ($i = 0; $i < 5; $i++) {
                $productData = TestDataFactory::createProduct([
                    'name' => "Scenario Product {$i}",
                    'price' => 10.00 + ($i * 5),
                    'active' => $i % 2 === 0
                ]);
                
                $this->mockFirestoreCreate('crud_test_products');
                $products[] = CRUDTestProduct::create($productData);
            }
            
            // Read and update products
            foreach ($products as $index => $product) {
                $this->mockFirestoreUpdate('crud_test_products', $product->id);
                $product->update(['description' => "Updated description {$index}"]);
            }
            
            // Delete some products
            foreach (array_slice($products, 0, 2) as $product) {
                $this->mockFirestoreDelete('crud_test_products', $product->id);
                $product->delete();
            }
            
            return count($products);
        });
        
        expect($scenarioMetrics['result'])->toBe(5);
        $this->assertFeaturePerformance($scenarioMetrics, 3.0, 15 * 1024 * 1024);
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create test models
        $models = $this->createTestModels(CRUDTestProduct::class, 3);
        
        // Verify models were created
        expect($models)->toHaveCount(3);
        foreach ($models as $model) {
            expect($model)->toBeInstanceOf(CRUDTestProduct::class);
        }
        
        // Clear test data
        $this->cleanupFeatureData();
        
        // Verify cleanup
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
    }
}
