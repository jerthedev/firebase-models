<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestCase;
use Illuminate\Support\Carbon;

// Test model for comprehensive update testing
class UpdateTestModel extends FirestoreModel
{
    protected ?string $collection = 'update_test_models';
    
    protected array $fillable = [
        'name', 'email', 'status', 'score', 'metadata', 'tags', 'active'
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
    
    // Test events
    protected static array $eventLog = [];
    
    protected static function boot(): void
    {
        parent::boot();
        
        static::updating(function ($model) {
            static::$eventLog[] = 'updating';
        });
        
        static::updated(function ($model) {
            static::$eventLog[] = 'updated';
        });
        
        static::saving(function ($model) {
            static::$eventLog[] = 'saving';
        });
        
        static::saved(function ($model) {
            static::$eventLog[] = 'saved';
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

describe('Update Operations Comprehensive Testing', function () {
    beforeEach(function () {
        // Use ultra-light mock for memory efficiency
        $this->enableUltraLightMock();
        $this->clearFirestoreMocks();
        UpdateTestModel::clearEventLog();
    });

    describe('Individual Model Updates', function () {
        it('can update single attributes', function () {
            // Mock existing model
            $this->mockFirestoreGet('update_test_models', 'test-1', [
                'id' => 'test-1',
                'name' => 'Original Name',
                'email' => 'original@example.com',
                'status' => 'pending',
                'score' => 85.5,
                'active' => true,
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-1');
            
            $model = UpdateTestModel::find('test-1');
            
            // Test single attribute update
            $model->name = 'Updated Name';
            
            expect($model)->toBeDirty(['name']);
            expect($model)->toBeClean(['email', 'status', 'score', 'active']);
            expect($model->getChanges())->toBeEmpty(); // Changes only populated after save
            
            $result = $model->save();
            
            expect($result)->toBeTrue();
            expect($model)->toBeClean();
            expect($model->name)->toBe('Updated Name');
            expect($model->getChanges())->toHaveKey('name');
            expect($model->getChanges()['name'])->toBe('Updated Name');
            
            $this->assertFirestoreOperationCalled('update', 'update_test_models', 'test-1');
        });

        it('can update multiple attributes', function () {
            $this->mockFirestoreGet('update_test_models', 'test-2', [
                'id' => 'test-2',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active',
                'score' => 75.0,
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-2');
            
            $model = UpdateTestModel::find('test-2');
            
            // Update multiple attributes
            $model->name = 'Updated User';
            $model->email = 'updated@example.com';
            $model->score = 90.5;
            
            expect($model)->toBeDirty(['name', 'email', 'score']);
            expect($model)->toBeClean(['status']);
            
            $result = $model->save();
            
            expect($result)->toBeTrue();
            expect($model)->toBeClean();
            expect($model->name)->toBe('Updated User');
            expect($model->email)->toBe('updated@example.com');
            expect($model->score)->toBe(90.5);
            
            $changes = $model->getChanges();
            expect($changes)->toHaveKeys(['name', 'email', 'score']);
            expect($changes)->not->toHaveKey('status');
        });

        it('can update array and object attributes', function () {
            $this->mockFirestoreGet('update_test_models', 'test-3', [
                'id' => 'test-3',
                'name' => 'Test User',
                'metadata' => ['version' => 1, 'type' => 'basic'],
                'tags' => ['tag1', 'tag2'],
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-3');
            
            $model = UpdateTestModel::find('test-3');
            
            // Update array attributes
            $model->metadata = ['version' => 2, 'type' => 'premium', 'features' => ['a', 'b']];
            $model->tags = ['tag1', 'tag3', 'tag4'];
            
            expect($model)->toBeDirty(['metadata', 'tags']);
            
            $result = $model->save();
            
            expect($result)->toBeTrue();
            expect($model->metadata)->toBe(['version' => 2, 'type' => 'premium', 'features' => ['a', 'b']]);
            expect($model->tags)->toBe(['tag1', 'tag3', 'tag4']);
        });

        it('handles type casting during updates', function () {
            $this->mockFirestoreGet('update_test_models', 'test-4', [
                'id' => 'test-4',
                'name' => 'Test User',
                'active' => true,
                'score' => 85.5,
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-4');
            
            $model = UpdateTestModel::find('test-4');
            
            // Update with type casting
            $model->active = 'false'; // String to boolean
            $model->score = '92.7'; // String to float
            
            expect($model->active)->toBe(false);
            expect($model->score)->toBe(92.7);
            
            $result = $model->save();
            
            expect($result)->toBeTrue();
            expect($model->active)->toBe(false);
            expect($model->score)->toBe(92.7);
        });
    });

    describe('Mass Update Operations', function () {
        it('can use update method for mass assignment', function () {
            $this->mockFirestoreGet('update_test_models', 'test-5', [
                'id' => 'test-5',
                'name' => 'Original',
                'email' => 'original@example.com',
                'status' => 'pending',
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-5');
            
            $model = UpdateTestModel::find('test-5');
            
            $result = $model->update([
                'name' => 'Mass Updated',
                'email' => 'mass@example.com',
                'status' => 'completed',
            ]);
            
            expect($result)->toBeTrue();
            expect($model->name)->toBe('Mass Updated');
            expect($model->email)->toBe('mass@example.com');
            expect($model->status)->toBe('completed');
            expect($model)->toBeClean();
        });

        it('respects fillable attributes in mass updates', function () {
            $this->mockFirestoreGet('update_test_models', 'test-6', [
                'id' => 'test-6',
                'name' => 'Test User',
                'admin_only' => 'original_value',
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-6');
            
            $model = UpdateTestModel::find('test-6');
            
            $result = $model->update([
                'name' => 'Updated Name',
                'admin_only' => 'hacked_value', // Should be ignored (guarded)
            ]);
            
            expect($result)->toBeTrue();
            expect($model->name)->toBe('Updated Name');
            expect($model->admin_only)->toBe('original_value'); // Should remain unchanged
        });

        it('can force update guarded attributes', function () {
            $this->mockFirestoreGet('update_test_models', 'test-7', [
                'id' => 'test-7',
                'name' => 'Test User',
                'admin_only' => 'original_value',
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-7');
            
            $model = UpdateTestModel::find('test-7');
            
            $model->forceFill([
                'name' => 'Force Updated',
                'admin_only' => 'force_updated_value',
            ]);
            
            $result = $model->save();
            
            expect($result)->toBeTrue();
            expect($model->name)->toBe('Force Updated');
            expect($model->admin_only)->toBe('force_updated_value');
        });
    });

    describe('Dirty Tracking and Change Detection', function () {
        it('accurately tracks dirty attributes', function () {
            $this->mockFirestoreGet('update_test_models', 'test-8', [
                'id' => 'test-8',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active',
                'score' => 85.0,
            ]);
            
            $model = UpdateTestModel::find('test-8');
            
            // Initially clean
            expect($model)->toBeClean();
            expect($model->getDirty())->toBeEmpty();
            expect($model->getChanges())->toBeEmpty();
            
            // Make changes
            $model->name = 'Updated Name';
            $model->score = 90.0;
            
            // Check dirty state
            expect($model)->toBeDirty();
            expect($model)->toBeDirty(['name', 'score']);
            expect($model)->toBeClean(['email', 'status']);
            
            $dirty = $model->getDirty();
            expect($dirty)->toHaveKeys(['name', 'score']);
            expect($dirty['name'])->toBe('Updated Name');
            expect($dirty['score'])->toBe(90.0);
            
            // Changes should still be empty before save
            expect($model->getChanges())->toBeEmpty();
        });

        it('tracks original values correctly', function () {
            $this->mockFirestoreGet('update_test_models', 'test-9', [
                'id' => 'test-9',
                'name' => 'Original Name',
                'email' => 'original@example.com',
            ]);
            
            $model = UpdateTestModel::find('test-9');
            
            expect($model->getOriginal('name'))->toBe('Original Name');
            expect($model->getOriginal('email'))->toBe('original@example.com');
            expect($model->getOriginal())->toHaveKeys(['id', 'name', 'email']);
            
            $model->name = 'Updated Name';
            
            // Original should remain unchanged
            expect($model->getOriginal('name'))->toBe('Original Name');
            expect($model->name)->toBe('Updated Name');
        });

        it('can check if specific attributes are dirty', function () {
            $this->mockFirestoreGet('update_test_models', 'test-10', [
                'id' => 'test-10',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active',
            ]);
            
            $model = UpdateTestModel::find('test-10');
            
            $model->name = 'Updated Name';
            
            expect($model->isDirty('name'))->toBeTrue();
            expect($model->isDirty('email'))->toBeFalse();
            expect($model->isDirty(['name']))->toBeTrue();
            expect($model->isDirty(['email']))->toBeFalse();
            expect($model->isDirty(['name', 'email']))->toBeTrue(); // Any dirty
            
            expect($model->isClean('name'))->toBeFalse();
            expect($model->isClean('email'))->toBeTrue();
            expect($model->isClean(['name']))->toBeFalse();
            expect($model->isClean(['email']))->toBeTrue();
            expect($model->isClean(['name', 'email']))->toBeFalse(); // All clean
        });
    });

    describe('Update Events and Lifecycle', function () {
        it('fires update events in correct order', function () {
            $this->mockFirestoreGet('update_test_models', 'test-11', [
                'id' => 'test-11',
                'name' => 'Test User',
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-11');

            $model = UpdateTestModel::find('test-11');
            $model->name = 'Updated Name';

            UpdateTestModel::clearEventLog();

            $result = $model->save();

            expect($result)->toBeTrue();

            $events = UpdateTestModel::getEventLog();
            expect($events)->toBe(['saving', 'updating', 'updated', 'saved']);
        });

        it('can cancel update with event listeners', function () {
            $this->mockFirestoreGet('update_test_models', 'test-12', [
                'id' => 'test-12',
                'name' => 'Test User',
            ]);

            // Add event listener that cancels update
            UpdateTestModel::updating(function ($model) {
                if ($model->name === 'Forbidden Name') {
                    return false; // Cancel update
                }
            });

            $model = UpdateTestModel::find('test-12');
            $model->name = 'Forbidden Name';

            $result = $model->save();

            expect($result)->toBeFalse();
            expect($model->name)->toBe('Forbidden Name'); // Local change remains
            expect($model)->toBeDirty(); // Still dirty since save failed
        });

        it('handles timestamps during updates', function () {
            $this->mockFirestoreGet('update_test_models', 'test-13', [
                'id' => 'test-13',
                'name' => 'Test User',
                'created_at' => '2023-01-01 10:00:00',
                'updated_at' => '2023-01-01 10:00:00',
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-13');

            $model = UpdateTestModel::find('test-13');
            $originalUpdatedAt = $model->updated_at;

            // Wait a moment to ensure timestamp difference
            sleep(1);

            $model->name = 'Updated Name';
            $result = $model->save();

            expect($result)->toBeTrue();
            expect($model->updated_at)->not->toBe($originalUpdatedAt);
            expect($model->updated_at)->toBeInstanceOf(Carbon::class);
        });
    });

    describe('Update Validation and Error Handling', function () {
        it('handles update failures gracefully', function () {
            $this->mockFirestoreGet('update_test_models', 'test-14', [
                'id' => 'test-14',
                'name' => 'Test User',
            ]);

            // Mock update failure
            $this->mockFirestoreUpdateFailure('update_test_models', 'test-14');

            $model = UpdateTestModel::find('test-14');
            $model->name = 'Updated Name';

            expect($model)->toBeDirty();

            $result = $model->save();

            expect($result)->toBeFalse();
            expect($model)->toBeDirty(); // Should remain dirty after failed save
        });

        it('validates required fields during update', function () {
            $this->mockFirestoreGet('update_test_models', 'test-15', [
                'id' => 'test-15',
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

            $model = UpdateTestModel::find('test-15');

            // Test that we can update to empty values (Firestore allows this)
            $model->name = '';
            $model->email = null;

            expect($model)->toBeDirty(['name', 'email']);
            expect($model->name)->toBe('');
            expect($model->email)->toBeNull();
        });

        it('handles concurrent update scenarios', function () {
            $this->mockFirestoreGet('update_test_models', 'test-16', [
                'id' => 'test-16',
                'name' => 'Original Name',
                'version' => 1,
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-16');

            $model1 = UpdateTestModel::find('test-16');
            $model2 = UpdateTestModel::find('test-16');

            // Both models start with same data
            expect($model1->name)->toBe('Original Name');
            expect($model2->name)->toBe('Original Name');

            // Update both models
            $model1->name = 'Updated by Model 1';
            $model2->name = 'Updated by Model 2';

            // First save should succeed
            $result1 = $model1->save();
            expect($result1)->toBeTrue();

            // Second save should also succeed (last write wins in Firestore)
            $result2 = $model2->save();
            expect($result2)->toBeTrue();

            expect($model2->name)->toBe('Updated by Model 2');
        });
    });

    describe('Touch and Timestamp Updates', function () {
        it('can touch model to update timestamps', function () {
            $this->mockFirestoreGet('update_test_models', 'test-17', [
                'id' => 'test-17',
                'name' => 'Test User',
                'updated_at' => '2023-01-01 10:00:00',
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-17');

            $model = UpdateTestModel::find('test-17');
            $originalUpdatedAt = $model->updated_at;

            sleep(1);

            $result = $model->touch();

            expect($result)->toBeTrue();
            expect($model->updated_at)->not->toBe($originalUpdatedAt);
            expect($model)->toBeClean(); // Touch doesn't make model dirty
        });

        it('skips update when no changes are made', function () {
            $this->mockFirestoreGet('update_test_models', 'test-18', [
                'id' => 'test-18',
                'name' => 'Test User',
            ]);

            $model = UpdateTestModel::find('test-18');

            // No changes made
            expect($model)->toBeClean();

            $result = $model->save();

            expect($result)->toBeTrue(); // Save succeeds but no update operation

            // Should not call Firestore update since no changes
            $this->assertFirestoreOperationNotCalled('update', 'update_test_models', 'test-18');
        });
    });

    describe('Save Options and Behavior', function () {
        it('can save quietly without events', function () {
            $this->mockFirestoreGet('update_test_models', 'test-19', [
                'id' => 'test-19',
                'name' => 'Test User',
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-19');

            $model = UpdateTestModel::find('test-19');
            $model->name = 'Updated Quietly';

            UpdateTestModel::clearEventLog();

            $result = $model->saveQuietly();

            expect($result)->toBeTrue();
            expect($model->name)->toBe('Updated Quietly');

            $events = UpdateTestModel::getEventLog();
            expect($events)->toBeEmpty(); // No events should fire
        });

        it('handles save options correctly', function () {
            $this->mockFirestoreGet('update_test_models', 'test-20', [
                'id' => 'test-20',
                'name' => 'Test User',
            ]);
            $this->mockFirestoreUpdate('update_test_models', 'test-20');

            $model = UpdateTestModel::find('test-20');
            $model->name = 'Updated with Options';

            $result = $model->save(['touch' => false]);

            expect($result)->toBeTrue();
            expect($model->name)->toBe('Updated with Options');
        });
    });
});
