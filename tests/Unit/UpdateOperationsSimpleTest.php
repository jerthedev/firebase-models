<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestCase;

// Simple test model for update testing
class SimpleUpdateTestModel extends FirestoreModel
{
    protected ?string $collection = 'simple_update_test';
    
    protected array $fillable = [
        'name', 'email', 'status', 'score', 'active'
    ];
    
    protected array $casts = [
        'active' => 'boolean',
        'score' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

describe('Update Operations - Simple Tests', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
    });

    describe('Dirty Tracking', function () {
        it('tracks dirty attributes correctly', function () {
            $model = new SimpleUpdateTestModel([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active',
                'score' => 85.5,
                'active' => true,
            ]);

            // Mark as existing and sync to make it "clean"
            $model->exists = true;
            $model->syncOriginal();
            
            expect($model->isClean())->toBeTrue();
            expect($model->getDirty())->toBeEmpty();
            expect($model->getChanges())->toBeEmpty();
            
            // Make changes
            $model->name = 'Jane Doe';
            $model->score = 92.0;

            expect($model->isDirty())->toBeTrue();
            expect($model->isDirty(['name', 'score']))->toBeTrue();
            expect($model->isClean(['email', 'status', 'active']))->toBeTrue();
            
            $dirty = $model->getDirty();
            expect($dirty)->toHaveKeys(['name', 'score']);
            expect($dirty['name'])->toBe('Jane Doe');
            expect($dirty['score'])->toBe(92.0);
            
            // Original values should remain unchanged
            expect($model->getOriginal('name'))->toBe('John Doe');
            expect($model->getOriginal('score'))->toBe(85.5);
        });

        it('can check specific dirty attributes', function () {
            $model = new SimpleUpdateTestModel([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'pending',
            ]);
            $model->exists = true;
            $model->syncOriginal();
            
            $model->name = 'Updated User';
            
            expect($model->isDirty('name'))->toBeTrue();
            expect($model->isDirty('email'))->toBeFalse();
            expect($model->isDirty('status'))->toBeFalse();
            
            expect($model->isDirty(['name']))->toBeTrue();
            expect($model->isDirty(['email']))->toBeFalse();
            expect($model->isDirty(['name', 'email']))->toBeTrue(); // Any dirty
            
            expect($model->isClean('name'))->toBeFalse();
            expect($model->isClean('email'))->toBeTrue();
            expect($model->isClean(['name']))->toBeFalse();
            expect($model->isClean(['email']))->toBeTrue();
            expect($model->isClean(['name', 'email']))->toBeFalse(); // All clean
        });

        it('handles array and object changes', function () {
            $model = new SimpleUpdateTestModel();
            $model->setAttribute('metadata', ['version' => 1, 'type' => 'basic']);
            $model->setAttribute('tags', ['tag1', 'tag2']);
            $model->syncOriginal();
            
            expect($model->isClean())->toBeTrue();

            // Update array attributes
            $model->setAttribute('metadata', ['version' => 2, 'type' => 'premium']);
            $model->setAttribute('tags', ['tag1', 'tag3']);

            expect($model->isDirty(['metadata', 'tags']))->toBeTrue();
            
            $dirty = $model->getDirty();
            expect($dirty['metadata'])->toBe(['version' => 2, 'type' => 'premium']);
            expect($dirty['tags'])->toBe(['tag1', 'tag3']);
        });
    });

    describe('Mass Assignment', function () {
        it('respects fillable attributes', function () {
            $model = new SimpleUpdateTestModel();
            
            expect($model->isFillable('name'))->toBeTrue();
            expect($model->isFillable('email'))->toBeTrue();
            expect($model->isFillable('status'))->toBeTrue();
            expect($model->isFillable('score'))->toBeTrue();
            expect($model->isFillable('active'))->toBeTrue();
        });

        it('can fill multiple attributes', function () {
            $model = new SimpleUpdateTestModel();
            
            $model->fill([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active',
                'score' => 88.5,
                'active' => true,
            ]);
            
            expect($model->name)->toBe('Test User');
            expect($model->email)->toBe('test@example.com');
            expect($model->status)->toBe('active');
            expect($model->score)->toBe(88.5);
            expect($model->active)->toBe(true);
        });

        it('can force fill protected attributes', function () {
            $model = new SimpleUpdateTestModel();
            
            $model->forceFill([
                'name' => 'Force Filled',
                'protected_field' => 'secret_value',
            ]);
            
            expect($model->name)->toBe('Force Filled');
            expect($model->getAttribute('protected_field'))->toBe('secret_value');
        });
    });

    describe('Type Casting', function () {
        it('casts attributes correctly during updates', function () {
            $model = new SimpleUpdateTestModel();
            
            // Test boolean casting
            $model->active = 'true';
            expect($model->active)->toBe(true);
            
            $model->active = 'false';
            expect($model->active)->toBe(false);
            
            $model->active = 1;
            expect($model->active)->toBe(true);
            
            $model->active = 0;
            expect($model->active)->toBe(false);
            
            // Test float casting
            $model->score = '92.7';
            expect($model->score)->toBe(92.7);
            
            $model->score = 85;
            expect($model->score)->toBe(85.0);
        });

        it('maintains cast types in dirty tracking', function () {
            $model = new SimpleUpdateTestModel([
                'active' => true,
                'score' => 85.5,
            ]);
            $model->exists = true;
            $model->syncOriginal();
            
            // Update with different types
            $model->active = 'false'; // String to boolean
            $model->score = '92.7'; // String to float
            
            expect($model->active)->toBe(false);
            expect($model->score)->toBe(92.7);
            
            $dirty = $model->getDirty();
            expect($dirty['active'])->toBe(false);
            expect($dirty['score'])->toBe(92.7);
        });
    });

    describe('Change Tracking', function () {
        it('tracks changes after sync', function () {
            $model = new SimpleUpdateTestModel([
                'name' => 'Original Name',
                'email' => 'original@example.com',
            ]);
            $model->syncOriginal();
            
            // Make changes
            $model->name = 'Updated Name';
            $model->email = 'updated@example.com';
            
            // Simulate save by syncing changes
            $model->syncChanges();
            
            $changes = $model->getChanges();
            expect($changes)->toHaveKeys(['name', 'email']);
            expect($changes['name'])->toBe('Updated Name');
            expect($changes['email'])->toBe('updated@example.com');
            
            // After sync, should be clean
            expect($model->isClean())->toBeTrue();
        });

        it('can get original values', function () {
            $model = new SimpleUpdateTestModel([
                'name' => 'Original Name',
                'email' => 'original@example.com',
                'status' => 'pending',
            ]);
            $model->syncOriginal();
            
            // Make changes
            $model->name = 'Updated Name';
            $model->email = 'updated@example.com';
            
            // Original values should remain
            expect($model->getOriginal('name'))->toBe('Original Name');
            expect($model->getOriginal('email'))->toBe('original@example.com');
            expect($model->getOriginal('status'))->toBe('pending');
            
            $original = $model->getOriginal();
            expect($original['name'])->toBe('Original Name');
            expect($original['email'])->toBe('original@example.com');
            expect($original['status'])->toBe('pending');
        });

        it('can check if attributes were changed', function () {
            $model = new SimpleUpdateTestModel([
                'name' => 'Original Name',
                'email' => 'original@example.com',
            ]);
            $model->syncOriginal();
            
            $model->name = 'Updated Name';
            $model->syncChanges();
            
            expect($model->wasChanged('name'))->toBeTrue();
            expect($model->wasChanged('email'))->toBeFalse();
            expect($model->wasChanged(['name']))->toBeTrue();
            expect($model->wasChanged(['email']))->toBeFalse();
            expect($model->wasChanged(['name', 'email']))->toBeTrue(); // Any changed
        });
    });

    describe('Model State', function () {
        it('tracks existence state', function () {
            $model = new SimpleUpdateTestModel();
            
            expect($model->exists)->toBeFalse();
            expect($model->wasRecentlyCreated)->toBeFalse();
            
            // Simulate creation
            $model->exists = true;
            $model->wasRecentlyCreated = true;
            
            expect($model->exists)->toBeTrue();
            expect($model->wasRecentlyCreated)->toBeTrue();
        });

        it('can check if model has specific attributes', function () {
            $model = new SimpleUpdateTestModel([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
            
            expect($model->hasAttribute('name'))->toBeTrue();
            expect($model->hasAttribute('email'))->toBeTrue();
            expect($model->hasAttribute('nonexistent'))->toBeFalse();
        });

        it('can get all attributes', function () {
            $model = new SimpleUpdateTestModel([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'active' => true,
            ]);
            
            $attributes = $model->getAttributes();
            expect($attributes)->toHaveKeys(['name', 'email', 'active']);
            expect($attributes['name'])->toBe('Test User');
            expect($attributes['email'])->toBe('test@example.com');
            expect($attributes['active'])->toBe(true);
        });
    });
});
