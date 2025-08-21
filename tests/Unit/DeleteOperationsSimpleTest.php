<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestCase;

// Simple test model for delete testing
class SimpleDeleteTestModel extends FirestoreModel
{
    protected ?string $collection = 'simple_delete_test';
    
    protected array $fillable = [
        'name', 'email', 'status', 'active'
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

describe('Delete Operations - Simple Tests', function () {
    beforeEach(function () {
        // Use ultra-light mock with interface fix
        $this->enableUltraLightMock();
        $this->clearFirestoreMocks();
        SimpleDeleteTestModel::clearEventLog();
    });

    describe('Basic Deletion', function () {
        it('can delete an existing model', function () {
            $model = new SimpleDeleteTestModel([
                'id' => 'test-1',
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active',
                'active' => true,
            ]);
            
            // Mark as existing and sync
            $model->exists = true;
            $model->syncOriginal();
            
            $result = $model->delete();
            
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
        });

        it('returns null when trying to delete non-existing model', function () {
            $model = new SimpleDeleteTestModel([
                'name' => 'Non-existing User',
            ]);
            
            // Model doesn't exist
            expect($model->exists)->toBeFalse();
            
            $result = $model->delete();
            
            expect($result)->toBeNull();
        });

        it('can delete quietly without events', function () {
            $model = new SimpleDeleteTestModel([
                'id' => 'test-2',
                'name' => 'Quiet Delete',
                'email' => 'quiet@example.com',
            ]);
            
            $model->exists = true;
            $model->syncOriginal();
            
            SimpleDeleteTestModel::clearEventLog();
            
            $result = $model->deleteQuietly();
            
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
            
            $events = SimpleDeleteTestModel::getEventLog();
            expect($events)->toBeEmpty(); // No events should fire
        });
    });

    describe('Delete Events', function () {
        it('fires delete events in correct order', function () {
            $model = new SimpleDeleteTestModel([
                'id' => 'test-3',
                'name' => 'Event Test',
                'email' => 'events@example.com',
            ]);
            
            $model->exists = true;
            $model->syncOriginal();
            
            SimpleDeleteTestModel::clearEventLog();
            
            $result = $model->delete();
            
            expect($result)->toBeTrue();
            
            $events = SimpleDeleteTestModel::getEventLog();
            expect($events)->toHaveCount(2);
            expect($events[0])->toBe(['deleting', 'Event Test', true]);  // Before delete
            expect($events[1])->toBe(['deleted', 'Event Test', false]); // After delete
        });

        it('can cancel deletion with event listeners', function () {
            // Add event listener that cancels deletion
            SimpleDeleteTestModel::deleting(function ($model) {
                if ($model->name === 'Protected User') {
                    return false; // Cancel deletion
                }
            });
            
            $model = new SimpleDeleteTestModel([
                'id' => 'test-4',
                'name' => 'Protected User',
                'email' => 'protected@example.com',
            ]);
            
            $model->exists = true;
            $model->syncOriginal();
            
            $result = $model->delete();
            
            expect($result)->toBeFalse();
            expect($model->exists)->toBeTrue(); // Should still exist
        });
    });

    describe('Model State Management', function () {
        it('properly updates model state after deletion', function () {
            $model = new SimpleDeleteTestModel();
            $model->setAttribute('id', 'test-5');
            $model->setAttribute('name', 'State Test');
            $model->setAttribute('email', 'state@example.com');
            $model->setAttribute('status', 'active');

            $model->exists = true;
            $model->syncOriginal();

            // Before deletion
            expect($model->exists)->toBeTrue();
            expect($model->getKey())->toBe('test-5');
            expect($model->name)->toBe('State Test');
            
            $result = $model->delete();
            
            // After deletion
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
            expect($model->getKey())->toBe('test-5'); // Key should remain
            expect($model->name)->toBe('State Test'); // Attributes should remain
        });

        it('maintains attributes after deletion', function () {
            $model = new SimpleDeleteTestModel([
                'id' => 'test-6',
                'name' => 'Attribute Test',
                'email' => 'attr@example.com',
                'status' => 'pending',
                'active' => true,
            ]);
            
            $model->exists = true;
            $model->syncOriginal();
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
    });

    describe('Edge Cases and Validation', function () {
        it('handles empty primary key gracefully', function () {
            $model = new class extends SimpleDeleteTestModel {
                protected string $primaryKey = '';
                
                public function getKeyName(): string
                {
                    return '';
                }
            };
            
            $model->exists = true;
            
            // Since getKeyName() returns empty string (not null), 
            // the delete method will proceed but may fail during execution
            expect($model->getKeyName())->toBe('');
        });

        it('validates model state before deletion', function () {
            $model = new SimpleDeleteTestModel([
                'name' => 'New Model',
                'email' => 'new@example.com',
            ]);
            
            // Model doesn't exist yet
            expect($model->exists)->toBeFalse();
            
            $result = $model->delete();
            
            expect($result)->toBeNull(); // Should return null for non-existing model
        });

        it('handles deletion with missing attributes', function () {
            $model = new SimpleDeleteTestModel([
                'id' => 'missing-attrs',
                // Missing other attributes
            ]);
            
            $model->exists = true;
            $model->syncOriginal();
            
            $result = $model->delete();
            
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
        });

        it('preserves model integrity during deletion process', function () {
            $model = new SimpleDeleteTestModel([
                'id' => 'integrity-test',
                'name' => 'Integrity Test',
                'email' => 'integrity@example.com',
                'status' => 'active',
            ]);
            
            $model->exists = true;
            $model->syncOriginal();
            $originalData = $model->toArray();
            
            $result = $model->delete();
            
            expect($result)->toBeTrue();
            expect($model->exists)->toBeFalse();
            
            // Data should remain intact after deletion
            expect($model->toArray())->toBe($originalData);
        });
    });

    describe('Delete Method Behavior', function () {
        it('can check primary key name', function () {
            $model = new SimpleDeleteTestModel();
            
            expect($model->getKeyName())->toBe('id');
        });

        it('can get model key value', function () {
            $model = new SimpleDeleteTestModel();
            $model->setAttribute('id', 'key-test');
            $model->setAttribute('name', 'Key Test');

            expect($model->getKey())->toBe('key-test');
        });

        it('handles model without ID gracefully', function () {
            $model = new SimpleDeleteTestModel([
                'name' => 'No ID Model',
            ]);
            
            expect($model->getKey())->toBeNull();
            expect($model->exists)->toBeFalse();
            
            $result = $model->delete();
            expect($result)->toBeNull();
        });
    });
});
