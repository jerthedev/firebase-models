<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive Delete Operations Test
 * 
 * Migrated and consolidated from:
 * - tests/Unit/DeleteOperationsTest.php
 * - tests/Unit/DeleteOperationsSimpleTest.php
 * 
 * Uses new UnitTestSuite for optimized performance and memory management.
 */

// Test model for delete operations
class DeleteTestModel extends FirestoreModel
{
    protected ?string $collection = 'delete_test_models';

    // Disable caching to avoid memory issues
    protected bool $cacheEnabled = false;
    
    protected array $fillable = [
        'id', 'name', 'email', 'status', 'active', 'category'
    ];
    
    protected array $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // Test events tracking
    protected static array $eventLog = [];
    
    protected static function boot(): void
    {
        parent::boot();
        
        static::deleting(function ($model) {
            static::$eventLog[] = ['deleting', $model->name ?? 'unknown', $model->exists];
        });
        
        static::deleted(function ($model) {
            static::$eventLog[] = ['deleted', $model->name ?? 'unknown', $model->exists];
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

class DeleteOperationsTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for delete operations
        $this->setTestRequirements([
            'document_count' => 100,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();

        // Disable caching to avoid memory issues in delete operations
        \JTD\FirebaseModels\Cache\RequestCache::disable();

        // Clear event log before each test
        DeleteTestModel::clearEventLog();
    }

    // ========================================
    // SINGLE MODEL DELETION TESTS
    // ========================================

    #[Test]
    public function it_can_delete_an_existing_model()
    {
        // Create test model using TestDataFactory
        $modelData = TestDataFactory::createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'status' => 'active',
            'active' => true,
        ]);
        
        $model = $this->createTestModel(DeleteTestModel::class, $modelData);
        
        // Verify model exists
        $this->assertDocumentExists('delete_test_models', $model->id);
        expect($model->exists)->toBeTrue();
        expect($model->name)->toBe('Test User');
        
        // Measure deletion performance
        $executionTime = $this->benchmark(function () use ($model) {
            return $model->delete();
        });
        
        // Assert deletion was successful
        expect($model->delete())->toBeTrue();
        expect($model->exists)->toBeFalse();
        $this->assertDocumentNotExists('delete_test_models', $model->id);
        
        // Verify delete operation was recorded
        $this->assertOperationPerformed('delete', 'delete_test_models', $model->id);
        
        // Performance assertion
        $this->assertLessThan(0.1, $executionTime, 'Delete operation should be fast');
    }

    #[Test]
    public function it_returns_null_when_trying_to_delete_non_existing_model()
    {
        $model = new DeleteTestModel([
            'name' => 'Non-existing User',
            'email' => 'nonexistent@example.com',
        ]);
        
        // Model doesn't exist
        expect($model->exists)->toBeFalse();
        
        $result = $model->delete();
        
        expect($result)->toBeNull();
        
        // No delete operation should be recorded
        $deleteOps = $this->getOperationsByType('delete');
        expect($deleteOps)->toHaveCount(0);
    }

    #[Test]
    public function it_fires_delete_events_in_correct_order()
    {
        $modelData = TestDataFactory::createUser([
            'name' => 'Event Test',
            'email' => 'events@example.com',
        ]);
        
        $model = $this->createTestModel(DeleteTestModel::class, $modelData);
        
        // Delete the model
        $model->delete();
        
        // Check events were fired in correct order
        $events = DeleteTestModel::getEventLog();
        
        expect($events)->toHaveCount(2);
        expect($events[0])->toBe(['deleting', 'Event Test', true]);  // Before delete
        expect($events[1])->toBe(['deleted', 'Event Test', false]); // After delete
    }

    #[Test]
    public function it_can_cancel_deletion_with_event_listeners()
    {
        // Add event listener that cancels deletion
        DeleteTestModel::deleting(function ($model) {
            if ($model->name === 'Protected User') {
                return false; // Cancel deletion
            }
        });
        
        $modelData = TestDataFactory::createUser([
            'name' => 'Protected User',
            'email' => 'protected@example.com',
        ]);
        
        $model = $this->createTestModel(DeleteTestModel::class, $modelData);
        
        $result = $model->delete();
        
        expect($result)->toBeFalse();
        expect($model->exists)->toBeTrue(); // Should still exist
        
        // Should not call delete operation since deletion was cancelled
        $deleteOps = $this->getOperationsByType('delete');
        expect($deleteOps)->toHaveCount(0);
    }

    #[Test]
    public function it_properly_updates_model_state_after_deletion()
    {
        $modelData = TestDataFactory::createUser([
            'name' => 'State Test',
            'email' => 'state@example.com',
            'status' => 'active',
        ]);
        
        $model = $this->createTestModel(DeleteTestModel::class, $modelData);
        
        // Verify initial state
        expect($model->exists)->toBeTrue();
        expect($model->name)->toBe('State Test');
        
        // Delete the model
        $model->delete();
        
        // Verify state after deletion
        expect($model->exists)->toBeFalse();
        expect($model->getKey())->toBe($model->id); // Key should remain
        expect($model->name)->toBe('State Test'); // Attributes should remain
    }

    #[Test]
    public function it_maintains_attributes_after_deletion()
    {
        $modelData = TestDataFactory::createUser([
            'name' => 'Attribute Test',
            'email' => 'attr@example.com',
            'status' => 'pending',
            'active' => true,
        ]);
        
        $model = $this->createTestModel(DeleteTestModel::class, $modelData);
        
        // Store original data for comparison
        $originalData = $model->toArray();
        
        // Delete the model
        $model->delete();
        
        // Verify attributes are maintained
        expect($model->exists)->toBeFalse();
        expect($model->name)->toBe('Attribute Test');
        expect($model->email)->toBe('attr@example.com');
        expect($model->status)->toBe('pending');
        expect($model->active)->toBe(true);
        
        // Data should remain intact after deletion (except exists flag)
        $currentData = $model->toArray();
        expect($currentData['name'])->toBe($originalData['name']);
        expect($currentData['email'])->toBe($originalData['email']);
    }

    #[Test]
    public function it_handles_bulk_deletions_efficiently()
    {
        // Enable memory monitoring
        $this->enableMemoryMonitoring();
        
        // Create multiple test models
        $models = $this->createTestModels(DeleteTestModel::class, 10, [
            'status' => 'inactive',
        ]);
        
        // Verify all models exist
        $this->assertCollectionCount('delete_test_models', 10);
        
        // Measure bulk deletion performance
        $executionTime = $this->benchmark(function () use ($models) {
            foreach ($models as $model) {
                $model->delete();
            }
        });
        
        // Verify all models are deleted
        $this->assertCollectionCount('delete_test_models', 0);
        
        // Performance assertions
        $this->assertLessThan(1.0, $executionTime, 'Bulk deletion should be efficient');
        $this->assertMemoryUsageWithinThreshold(10 * 1024 * 1024); // 10MB
        
        // Verify all delete operations were recorded
        $deleteOps = $this->getOperationsByType('delete');
        expect($deleteOps)->toHaveCount(10);
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create some test data
        $this->createTestModels(DeleteTestModel::class, 5);
        
        // Verify data exists
        $this->assertCollectionCount('delete_test_models', 5);
        
        // Clear test data
        $this->clearTestData();
        
        // Verify data is cleaned up
        $this->assertCollectionCount('delete_test_models', 0);
        
        // Verify operations log is cleared
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
    }
}
