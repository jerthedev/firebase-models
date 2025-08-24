<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive Update Operations Test
 *
 * Migrated and consolidated from:
 * - tests/Unit/UpdateOperationsTest.php
 * - tests/Unit/UpdateOperationsSimpleTest.php
 *
 * Uses new UnitTestSuite for optimized performance and memory management.
 */

// Test model for update operations
class UpdateTestModel extends FirestoreModel
{
    protected ?string $collection = 'update_test_models';

    protected array $fillable = [
        'name', 'email', 'status', 'score', 'metadata', 'tags', 'active',
    ];

    protected array $casts = [
        'active' => 'boolean',
        'score' => 'float',
        'metadata' => 'array',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected array $guarded = ['admin_only'];

    // Test events tracking
    protected static array $eventLog = [];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function ($model) {
            static::$eventLog[] = ['updating', $model->name ?? 'unknown'];
        });

        static::updated(function ($model) {
            static::$eventLog[] = ['updated', $model->name ?? 'unknown'];
        });

        static::saving(function ($model) {
            static::$eventLog[] = ['saving', $model->name ?? 'unknown'];
        });

        static::saved(function ($model) {
            static::$eventLog[] = ['saved', $model->name ?? 'unknown'];
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

class UpdateOperationsTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for update operations
        $this->setTestRequirements([
            'document_count' => 100,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();

        // Clear event log before each test
        UpdateTestModel::clearEventLog();
    }

    // ========================================
    // INDIVIDUAL MODEL UPDATES
    // ========================================

    #[Test]
    public function it_can_update_single_and_multiple_attributes()
    {
        // Create test model using TestDataFactory
        $modelData = TestDataFactory::createUser([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'status' => 'pending',
            'score' => 85.5,
            'active' => true,
        ]);

        $model = $this->createTestModel(UpdateTestModel::class, $modelData);

        // Test single attribute update
        $model->name = 'Updated Name';

        expect($model)->toBeDirty(['name']);
        expect($model)->toBeClean(['email', 'status', 'score', 'active']);

        // Measure update performance
        $executionTime = $this->benchmark(function () use ($model) {
            return $model->save();
        });

        expect($model->save())->toBeTrue();
        expect($model)->toBeClean();
        expect($model->name)->toBe('Updated Name');

        // Test multiple attributes update
        $model->email = 'updated@example.com';
        $model->status = 'active';
        $model->score = 92.0;

        expect($model)->toBeDirty(['email', 'status', 'score']);
        expect($model->save())->toBeTrue();

        // Verify update operation was called
        $this->assertOperationPerformed('update', 'update_test_models', $model->id);

        // Performance assertion
        expect($executionTime)->toBeLessThan(0.1); // Should complete within 100ms
    }

    #[Test]
    public function it_handles_array_and_object_updates_with_type_casting()
    {
        $modelData = TestDataFactory::createUser([
            'name' => 'Test User',
            'metadata' => ['version' => 1, 'type' => 'basic'],
            'tags' => ['tag1', 'tag2'],
            'active' => true,
            'score' => 85.5,
        ]);

        $model = $this->createTestModel(UpdateTestModel::class, $modelData);

        // Test array updates
        $model->metadata = ['version' => 2, 'type' => 'premium', 'features' => ['advanced']];
        $model->tags = ['tag1', 'tag3', 'new-tag'];

        expect($model)->toBeDirty(['metadata', 'tags']);
        expect($model->save())->toBeTrue();

        // Test type casting during updates
        $model->active = 'false'; // String to boolean
        $model->score = '95.5'; // String to float

        expect($model->active)->toBe(false);
        expect($model->score)->toBe(95.5);
        expect($model->save())->toBeTrue();

        $this->assertOperationPerformed('update', 'update_test_models', $model->id);
    }

    // ========================================
    // MASS UPDATE OPERATIONS
    // ========================================

    #[Test]
    public function it_handles_mass_updates_and_fillable_attributes()
    {
        $modelData = TestDataFactory::createUser([
            'name' => 'Original',
            'email' => 'original@example.com',
            'status' => 'pending',
            'admin_only' => 'protected_value',
        ]);

        $model = $this->createTestModel(UpdateTestModel::class, $modelData);

        // Test mass update with fillable attributes
        $updateData = [
            'name' => 'Mass Updated',
            'email' => 'mass@example.com',
            'status' => 'active',
            'admin_only' => 'should_be_ignored', // Guarded attribute
        ];

        $result = $model->update($updateData);

        expect($result)->toBeTrue();
        expect($model->name)->toBe('Mass Updated');
        expect($model->email)->toBe('mass@example.com');
        expect($model->status)->toBe('active');
        expect($model->admin_only)->toBe('protected_value'); // Should remain unchanged

        // Test force update for guarded attributes
        $model->forceFill(['admin_only' => 'force_updated']);
        expect($model->save())->toBeTrue();
        expect($model->admin_only)->toBe('force_updated');

        $this->assertOperationPerformed('update', 'update_test_models', $model->id);
    }

    // ========================================
    // DIRTY TRACKING AND CHANGE DETECTION
    // ========================================

    #[Test]
    public function it_accurately_tracks_dirty_attributes_and_changes()
    {
        $modelData = TestDataFactory::createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'status' => 'active',
            'score' => 85.5,
        ]);

        $model = $this->createTestModel(UpdateTestModel::class, $modelData);

        // Initially clean
        expect($model)->toBeClean();
        expect($model->getDirty())->toBeEmpty();
        expect($model->getChanges())->toBeEmpty();

        // Make changes and test dirty tracking
        $model->name = 'Updated User';
        $model->score = 92.0;

        expect($model)->toBeDirty();
        expect($model)->toBeDirty(['name', 'score']);
        expect($model)->toBeClean(['email', 'status']);

        // Test specific dirty checks
        expect($model->isDirty('name'))->toBeTrue();
        expect($model->isDirty('email'))->toBeFalse();
        expect($model->isClean('name'))->toBeFalse();
        expect($model->isClean('email'))->toBeTrue();

        // Test original values
        expect($model->getOriginal('name'))->toBe('Test User');
        expect($model->getOriginal('score'))->toBe(85.5);

        // Save and verify changes are tracked
        expect($model->save())->toBeTrue();
        expect($model)->toBeClean();
        expect($model->getChanges())->toHaveKeys(['name', 'score']);
    }

    // ========================================
    // UPDATE EVENTS AND LIFECYCLE
    // ========================================

    #[Test]
    public function it_fires_update_events_and_handles_cancellation()
    {
        $modelData = TestDataFactory::createUser([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $model = $this->createTestModel(UpdateTestModel::class, $modelData);

        // Clear event log and make changes
        UpdateTestModel::clearEventLog();
        $model->name = 'Updated User';

        $result = $model->save();

        expect($result)->toBeTrue();

        // Verify events fired in correct order
        $events = UpdateTestModel::getEventLog();
        expect($events)->toContain(['saving', 'Updated User']);
        expect($events)->toContain(['updating', 'Updated User']);
        expect($events)->toContain(['updated', 'Updated User']);
        expect($events)->toContain(['saved', 'Updated User']);

        // Test event cancellation
        UpdateTestModel::updating(function ($model) {
            if ($model->name === 'Protected User') {
                return false; // Cancel update
            }
        });

        $model->name = 'Protected User';
        $result = $model->save();

        expect($result)->toBeFalse();
        expect($model->name)->toBe('Protected User'); // Local change remains

        // Should not call update operation since update was cancelled
        $updateOps = $this->getOperationsByType('update');
        expect(count($updateOps))->toBe(1); // Only the first successful update
    }

    // ========================================
    // PERFORMANCE AND MEMORY TESTS
    // ========================================

    #[Test]
    public function it_handles_touch_operations_and_optimizes_performance()
    {
        $modelData = TestDataFactory::createUser([
            'name' => 'Test User',
            'updated_at' => now()->subHour(),
        ]);

        $model = $this->createTestModel(UpdateTestModel::class, $modelData);
        $originalUpdatedAt = $model->updated_at;

        // Test touch operation
        $executionTime = $this->benchmark(function () use ($model) {
            return $model->touch();
        });

        expect($model->updated_at)->not->toBe($originalUpdatedAt);
        expect($executionTime)->toBeLessThan(0.05); // Touch should be very fast

        // Test skipping update when no changes
        $model->name = $model->name; // No actual change
        $result = $model->save();

        expect($result)->toBeTrue(); // Returns true but no operation performed

        // Test quiet save without events
        UpdateTestModel::clearEventLog();
        $model->name = 'Quiet Update';
        $model->saveQuietly();

        $events = UpdateTestModel::getEventLog();
        expect($events)->toBeEmpty(); // No events should be fired

        $this->assertOperationPerformed('update', 'update_test_models', $model->id);
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create multiple test models
        $models = $this->createTestModels(UpdateTestModel::class, 5);

        // Verify models exist
        $this->assertCollectionCount('update_test_models', 5);

        // Update some models
        foreach ($models as $index => $model) {
            $model->name = "Updated Model {$index}";
            $model->save();
        }

        // Clear test data
        $this->clearTestData();

        // Verify cleanup
        $this->assertCollectionCount('update_test_models', 0);

        // Verify operations log is cleared
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
    }
}
