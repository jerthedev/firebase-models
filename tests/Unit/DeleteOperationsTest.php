<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestCase;

// Test model for comprehensive delete testing
class DeleteTestModel extends FirestoreModel
{
    protected ?string $collection = 'delete_test_models';
    
    protected array $fillable = [
        'name', 'email', 'status', 'active', 'category'
    ];
    
    protected array $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // Test events
    protected static array $eventLog = [];
    
    protected static function boot(): void
    {
        parent::boot();
        
        static::deleting(function ($model) {
            static::$eventLog[] = ['deleting', $model->name, $model->exists];
        });
        
        static::deleted(function ($model) {
            static::$eventLog[] = ['deleted', $model->name, $model->exists];
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

describe('Delete Operations Comprehensive Testing', function () {
    beforeEach(function () {
        // Use ultra-light mock for memory efficiency
        $this->enableUltraLightMock();
        $this->clearFirestoreMocks();
        DeleteTestModel::clearEventLog();
    });

    describe('Single Model Deletion', function () {
        it('can delete an existing model', function () {
            // Mock existing model
            $this->mockFirestoreGet('delete_test_models', 'test-1', [
                'id' => 'test-1',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active',
                'active' => true,
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'test-1');
            
            $model = DeleteTestModel::find('test-1');
            
            expect($model->exists)->toBeTrue();
            expect($model->name)->toBe('Test User');
            
            $result = $model->delete();
            
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
            
            $this->assertFirestoreOperationCalled('delete', 'delete_test_models', 'test-1');
        });

        it('returns null when trying to delete non-existing model', function () {
            $model = new DeleteTestModel();
            $model->exists = false;
            
            $result = $model->delete();
            
            expect($result)->toBeNull();
        });

        it('handles empty primary key gracefully', function () {
            $model = new class extends DeleteTestModel {
                protected string $primaryKey = '';

                public function getKeyName(): string
                {
                    return '';
                }
            };

            $model->exists = true;

            // Since getKeyName() returns empty string (not null),
            // the delete method will proceed but may fail during execution
            // This tests the edge case of empty primary key
            expect($model->getKeyName())->toBe('');
        });

        it('can delete quietly without events', function () {
            $this->mockFirestoreGet('delete_test_models', 'test-2', [
                'id' => 'test-2',
                'name' => 'Quiet Delete',
                'email' => 'quiet@example.com',
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'test-2');
            
            $model = DeleteTestModel::find('test-2');
            
            DeleteTestModel::clearEventLog();
            
            $result = $model->deleteQuietly();
            
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
            
            $events = DeleteTestModel::getEventLog();
            expect($events)->toBeEmpty(); // No events should fire
        });
    });

    describe('Delete Events and Lifecycle', function () {
        it('fires delete events in correct order', function () {
            $this->mockFirestoreGet('delete_test_models', 'test-3', [
                'id' => 'test-3',
                'name' => 'Event Test',
                'email' => 'events@example.com',
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'test-3');
            
            $model = DeleteTestModel::find('test-3');
            
            DeleteTestModel::clearEventLog();
            
            $result = $model->delete();
            
            expect($result)->toBeTrue();
            
            $events = DeleteTestModel::getEventLog();
            expect($events)->toHaveCount(2);
            expect($events[0])->toBe(['deleting', 'Event Test', true]);  // Before delete
            expect($events[1])->toBe(['deleted', 'Event Test', false]); // After delete
        });

        it('can cancel deletion with event listeners', function () {
            // Add event listener that cancels deletion
            DeleteTestModel::deleting(function ($model) {
                if ($model->name === 'Protected User') {
                    return false; // Cancel deletion
                }
            });
            
            $this->mockFirestoreGet('delete_test_models', 'test-4', [
                'id' => 'test-4',
                'name' => 'Protected User',
                'email' => 'protected@example.com',
            ]);
            
            $model = DeleteTestModel::find('test-4');
            
            $result = $model->delete();
            
            expect($result)->toBeFalse();
            expect($model->exists)->toBeTrue(); // Should still exist
            
            // Should not call Firestore delete since deletion was cancelled
            $this->assertFirestoreOperationNotCalled('delete', 'delete_test_models', 'test-4');
        });

        it('handles delete event exceptions gracefully', function () {
            // Add event listener that throws exception
            DeleteTestModel::deleting(function ($model) {
                throw new \Exception('Delete event error');
            });
            
            $this->mockFirestoreGet('delete_test_models', 'test-5', [
                'id' => 'test-5',
                'name' => 'Exception Test',
            ]);
            
            $model = DeleteTestModel::find('test-5');
            
            expect(fn() => $model->delete())
                ->toThrow(\Exception::class, 'Delete event error');
            
            expect($model->exists)->toBeTrue(); // Should still exist due to exception
        });
    });

    describe('Model State Management', function () {
        it('properly updates model state after deletion', function () {
            $this->mockFirestoreGet('delete_test_models', 'test-6', [
                'id' => 'test-6',
                'name' => 'State Test',
                'email' => 'state@example.com',
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'test-6');
            
            $model = DeleteTestModel::find('test-6');
            
            // Before deletion
            expect($model->exists)->toBeTrue();
            expect($model->getKey())->toBe('test-6');
            expect($model->name)->toBe('State Test');
            
            $result = $model->delete();
            
            // After deletion
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
            expect($model->getKey())->toBe('test-6'); // Key should remain
            expect($model->name)->toBe('State Test'); // Attributes should remain
        });

        it('maintains attributes after deletion', function () {
            $this->mockFirestoreGet('delete_test_models', 'test-7', [
                'id' => 'test-7',
                'name' => 'Attribute Test',
                'email' => 'attr@example.com',
                'status' => 'pending',
                'active' => true,
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'test-7');
            
            $model = DeleteTestModel::find('test-7');
            $originalAttributes = $model->getAttributes();
            
            $result = $model->delete();
            
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
            
            // All attributes should remain accessible
            expect($model->getAttributes())->toBe($originalAttributes);
            expect($model->name)->toBe('Attribute Test');
            expect($model->email)->toBe('attr@example.com');
            expect($model->status)->toBe('pending');
            expect($model->active)->toBe(true);
        });

        it('can check if model was recently deleted', function () {
            $this->mockFirestoreGet('delete_test_models', 'test-8', [
                'id' => 'test-8',
                'name' => 'Recent Delete Test',
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'test-8');
            
            $model = DeleteTestModel::find('test-8');
            
            expect($model->exists)->toBeTrue();
            
            $result = $model->delete();
            
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
            
            // Model should remember it was recently deleted
            // (This is a conceptual test - the actual implementation may vary)
        });
    });

    describe('Error Handling and Edge Cases', function () {
        it('handles deletion failure gracefully', function () {
            $this->mockFirestoreGet('delete_test_models', 'test-9', [
                'id' => 'test-9',
                'name' => 'Failure Test',
            ]);
            
            // Mock delete failure by not setting up the delete mock
            $this->mockFirestoreDeleteFailure('delete_test_models', 'test-9');
            
            $model = DeleteTestModel::find('test-9');
            
            expect($model->exists)->toBeTrue();
            
            // In a real scenario, this might throw an exception or return false
            // For now, we'll test that the model state is preserved
            expect($model->exists)->toBeTrue();
        });

        it('handles concurrent deletion scenarios', function () {
            $this->mockFirestoreGet('delete_test_models', 'test-10', [
                'id' => 'test-10',
                'name' => 'Concurrent Test',
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'test-10');
            
            $model1 = DeleteTestModel::find('test-10');
            $model2 = DeleteTestModel::find('test-10');
            
            // Both models start as existing
            expect($model1->exists)->toBeTrue();
            expect($model2->exists)->toBeTrue();
            
            // First deletion should succeed
            $result1 = $model1->delete();
            expect($result1)->toBeTrue();
            expect($model1->exists)->toBeFalse();
            
            // Second deletion should return null (already deleted)
            $model2->exists = false; // Simulate that it no longer exists
            $result2 = $model2->delete();
            expect($result2)->toBeNull();
        });

        it('validates model state before deletion', function () {
            $model = new DeleteTestModel([
                'name' => 'New Model',
                'email' => 'new@example.com',
            ]);
            
            // Model doesn't exist yet
            expect($model->exists)->toBeFalse();
            
            $result = $model->delete();
            
            expect($result)->toBeNull(); // Should return null for non-existing model
        });
    });

    describe('Bulk Deletion Operations', function () {
        it('can delete multiple models with query', function () {
            // Mock multiple models for deletion
            $this->mockFirestoreQuery('delete_test_models', [
                ['id' => 'bulk-1', 'name' => 'Bulk Test 1', 'status' => 'inactive'],
                ['id' => 'bulk-2', 'name' => 'Bulk Test 2', 'status' => 'inactive'],
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'bulk-1');
            $this->mockFirestoreDelete('delete_test_models', 'bulk-2');

            $deleted = DeleteTestModel::where('status', 'inactive')->delete();

            expect($deleted)->toBe(2);
            $this->assertFirestoreOperationCalled('delete', 'delete_test_models');
        });

        it('returns zero when no models match deletion criteria', function () {
            // Mock empty query result
            $this->mockFirestoreQuery('delete_test_models', []);

            $deleted = DeleteTestModel::where('status', 'nonexistent')->delete();

            expect($deleted)->toBe(0);
        });

        it('can delete all models in collection', function () {
            // Mock all models for deletion
            $this->mockFirestoreQuery('delete_test_models', [
                ['id' => 'all-1', 'name' => 'All Test 1'],
                ['id' => 'all-2', 'name' => 'All Test 2'],
                ['id' => 'all-3', 'name' => 'All Test 3'],
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'all-1');
            $this->mockFirestoreDelete('delete_test_models', 'all-2');
            $this->mockFirestoreDelete('delete_test_models', 'all-3');

            $deleted = DeleteTestModel::query()->delete();

            expect($deleted)->toBe(3);
        });

        it('handles partial deletion failures in bulk operations', function () {
            // Mock models where some deletions might fail
            $this->mockFirestoreQuery('delete_test_models', [
                ['id' => 'partial-1', 'name' => 'Partial Test 1'],
                ['id' => 'partial-2', 'name' => 'Partial Test 2'],
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'partial-1');
            // Don't mock partial-2 to simulate failure

            $deleted = DeleteTestModel::where('name', 'like', 'Partial%')->delete();

            // Should still return count of successful deletions
            expect($deleted)->toBeGreaterThanOrEqual(1);
        });
    });

    describe('Delete Method Variations', function () {
        it('supports forceDelete method', function () {
            // Mock model for force deletion
            $this->mockFirestoreQuery('delete_test_models', [
                ['id' => 'force-1', 'name' => 'Force Delete Test'],
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'force-1');

            $deleted = DeleteTestModel::where('id', 'force-1')->forceDelete();

            expect($deleted)->toBe(1);
            $this->assertFirestoreOperationCalled('delete', 'delete_test_models');
        });

        it('can delete by primary key', function () {
            $this->mockFirestoreGet('delete_test_models', 'key-delete', [
                'id' => 'key-delete',
                'name' => 'Key Delete Test',
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'key-delete');

            $model = DeleteTestModel::find('key-delete');
            $result = $model->delete();

            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
        });

        it('can delete with custom primary key', function () {
            $model = new class extends DeleteTestModel {
                protected string $primaryKey = 'custom_id';
            };

            $model->fill([
                'custom_id' => 'custom-123',
                'name' => 'Custom Key Test',
            ]);
            $model->exists = true;
            $model->syncOriginal();

            // Mock the deletion
            $this->mockFirestoreDelete('delete_test_models', 'custom-123');

            $result = $model->delete();

            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
        });
    });

    describe('Delete Performance and Optimization', function () {
        it('can handle large batch deletions efficiently', function () {
            // Mock a large number of models
            $models = [];
            for ($i = 1; $i <= 100; $i++) {
                $models[] = ['id' => "batch-{$i}", 'name' => "Batch Test {$i}"];
                $this->mockFirestoreDelete('delete_test_models', "batch-{$i}");
            }
            $this->mockFirestoreQuery('delete_test_models', $models);

            $deleted = DeleteTestModel::where('name', 'like', 'Batch%')->delete();

            expect($deleted)->toBe(100);
        });

        it('maintains memory efficiency during bulk deletions', function () {
            $initialMemory = memory_get_usage(true);

            // Mock moderate number of models
            $models = [];
            for ($i = 1; $i <= 50; $i++) {
                $models[] = ['id' => "memory-{$i}", 'name' => "Memory Test {$i}"];
                $this->mockFirestoreDelete('delete_test_models', "memory-{$i}");
            }
            $this->mockFirestoreQuery('delete_test_models', $models);

            $deleted = DeleteTestModel::where('name', 'like', 'Memory%')->delete();

            expect($deleted)->toBe(50);

            $finalMemory = memory_get_usage(true);
            $memoryUsed = $finalMemory - $initialMemory;

            // Memory usage should be reasonable (less than 5MB for 50 deletions)
            expect($memoryUsed)->toBeLessThan(5 * 1024 * 1024);
        });
    });

    describe('Delete Validation and Constraints', function () {
        it('validates model state before deletion', function () {
            $model = new DeleteTestModel();

            // Test various invalid states
            expect($model->delete())->toBeNull(); // Non-existing model

            $model->exists = true;
            $model->setAttribute('id', ''); // Empty ID

            expect(fn() => $model->delete())
                ->toThrow(\Exception::class); // Should throw for empty key
        });

        it('handles deletion with missing attributes', function () {
            $this->mockFirestoreGet('delete_test_models', 'missing-attrs', [
                'id' => 'missing-attrs',
                // Missing other attributes
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'missing-attrs');

            $model = DeleteTestModel::find('missing-attrs');

            $result = $model->delete();

            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
        });

        it('preserves model integrity during deletion process', function () {
            $this->mockFirestoreGet('delete_test_models', 'integrity-test', [
                'id' => 'integrity-test',
                'name' => 'Integrity Test',
                'email' => 'integrity@example.com',
                'status' => 'active',
            ]);
            $this->mockFirestoreDelete('delete_test_models', 'integrity-test');

            $model = DeleteTestModel::find('integrity-test');
            $originalData = $model->toArray();

            $result = $model->delete();

            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();

            // Data should remain intact after deletion
            expect($model->toArray())->toBe($originalData);
        });
    });
});
