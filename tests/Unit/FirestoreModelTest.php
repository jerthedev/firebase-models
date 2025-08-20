<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use Illuminate\Support\Carbon;

// Test model for testing purposes
class TestUser extends FirestoreModel
{
    protected ?string $collection = 'users';
    
    protected array $fillable = [
        'name', 'email', 'active', 'age'
    ];

    protected array $casts = [
        'active' => 'boolean',
        'age' => 'integer',
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];
    
    protected array $hidden = [
        'password'
    ];
}

describe('FirestoreModel', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
    });

    describe('Model Creation', function () {
        it('can create a new model instance', function () {
            $user = new TestUser();
            
            expect($user)->toBeFirestoreModel();
            expect($user->exists)->toBeFalse();
            expect($user->wasRecentlyCreated)->toBeFalse();
        });

        it('can create a model with attributes', function () {
            $user = new TestUser([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'active' => true,
                'age' => 30
            ]);
            
            expect($user->name)->toBe('John Doe');
            expect($user->email)->toBe('john@example.com');
            expect($user->active)->toBe(true);
            expect($user->age)->toBe(30);
        });

        it('respects fillable attributes', function () {
            $user = new TestUser([
                'name' => 'John Doe',
                'password' => 'secret', // Not fillable
                'admin' => true // Not fillable
            ]);
            
            expect($user->name)->toBe('John Doe');
            expect($user->hasAttribute('password'))->toBeFalse();
            expect($user->hasAttribute('admin'))->toBeFalse();
        });
    });

    describe('Attribute Casting', function () {
        it('casts boolean attributes correctly', function () {
            $user = new TestUser(['active' => '1']);
            
            expect($user->active)->toBe(true);
            expect($user)->toHaveCast('active', 'boolean');
        });

        it('casts integer attributes correctly', function () {
            $user = new TestUser(['age' => '30']);
            
            expect($user->age)->toBe(30);
            expect($user)->toHaveCast('age', 'integer');
        });

        it('casts array attributes correctly', function () {
            $user = new TestUser(['metadata' => '{"key": "value"}']);
            
            expect($user->metadata)->toBe(['key' => 'value']);
            expect($user)->toHaveCast('metadata', 'array');
        });

        it('casts datetime attributes correctly', function () {
            $date = '2023-01-01 12:00:00';
            $user = new TestUser(['created_at' => $date]);
            
            expect($user->created_at)->toBeInstanceOf(Carbon::class);
            expect($user->created_at->format('Y-m-d H:i:s'))->toBe($date);
        });
    });

    describe('Attribute Management', function () {
        it('can get and set attributes', function () {
            $user = new TestUser();
            
            $user->name = 'Jane Doe';
            expect($user->name)->toBe('Jane Doe');
            
            $user->setAttribute('email', 'jane@example.com');
            expect($user->getAttribute('email'))->toBe('jane@example.com');
        });

        it('tracks dirty attributes', function () {
            $user = new TestUser(['name' => 'John Doe']);
            $user->syncOriginal();
            
            expect($user)->toBeClean();
            
            $user->name = 'Jane Doe';
            expect($user)->toBeDirty();
            expect($user)->toBeDirty('name');
            expect($user)->toBeClean('email');
        });

        it('can check if attributes exist', function () {
            $user = new TestUser(['name' => 'John Doe']);
            
            expect($user->hasAttribute('name'))->toBeTrue();
            expect($user->hasAttribute('nonexistent'))->toBeFalse();
        });
    });

    describe('Mass Assignment Protection', function () {
        it('allows fillable attributes', function () {
            $user = new TestUser();
            
            expect($user->isFillable('name'))->toBeTrue();
            expect($user->isFillable('email'))->toBeTrue();
        });

        it('protects against non-fillable attributes', function () {
            $user = new TestUser();
            
            expect($user->isFillable('password'))->toBeFalse();
            expect($user->isFillable('admin'))->toBeFalse();
        });

        it('can force fill attributes', function () {
            $user = new TestUser();
            $user->forceFill(['password' => 'secret']);
            
            expect($user->hasAttribute('password'))->toBeTrue();
            expect($user->password)->toBe('secret');
        });
    });

    describe('Timestamps', function () {
        it('uses timestamps by default', function () {
            $user = new TestUser();
            
            expect($user->usesTimestamps())->toBeTrue();
        });

        it('can disable timestamps', function () {
            $user = new TestUser();
            $user->timestamps = false;
            
            expect($user->usesTimestamps())->toBeFalse();
        });

        it('has correct timestamp column names', function () {
            $user = new TestUser();
            
            expect($user->getCreatedAtColumn())->toBe('created_at');
            expect($user->getUpdatedAtColumn())->toBe('updated_at');
        });
    });

    describe('Collection Management', function () {
        it('uses correct collection name', function () {
            $user = new TestUser();
            
            expect($user->getCollection())->toBe('users');
        });

        it('can set collection name', function () {
            $user = new TestUser();
            $user->setCollection('custom_users');
            
            expect($user->getCollection())->toBe('custom_users');
        });
    });

    describe('Primary Key Management', function () {
        it('has correct default primary key', function () {
            $user = new TestUser();
            
            expect($user->getKeyName())->toBe('id');
            expect($user->getKeyType())->toBe('string');
            expect($user->getIncrementing())->toBeFalse();
        });

        it('can set custom primary key', function () {
            $user = new TestUser();
            $user->setKeyName('uuid');
            $user->setKeyType('string');
            
            expect($user->getKeyName())->toBe('uuid');
            expect($user->getKeyType())->toBe('string');
        });
    });

    describe('Serialization', function () {
        it('can convert to array', function () {
            $user = new TestUser([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'active' => true
            ]);
            
            $array = $user->toArray();
            
            expect($array)->toBeArray();
            expect($array['name'])->toBe('John Doe');
            expect($array['email'])->toBe('john@example.com');
            expect($array['active'])->toBe(true);
        });

        it('can convert to JSON', function () {
            $user = new TestUser([
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ]);
            
            $json = $user->toJson();
            $decoded = json_decode($json, true);
            
            expect($decoded['name'])->toBe('John Doe');
            expect($decoded['email'])->toBe('john@example.com');
        });

        it('respects hidden attributes', function () {
            $user = new TestUser([
                'name' => 'John Doe',
                'password' => 'secret'
            ]);
            $user->forceFill(['password' => 'secret']);
            
            $array = $user->toArray();
            
            expect($array)->not->toHaveKey('password');
            expect($array)->toHaveKey('name');
        });
    });

    describe('Model Comparison', function () {
        it('can compare models for equality', function () {
            $user1 = new TestUser(['id' => '123', 'name' => 'John']);
            $user2 = new TestUser(['id' => '123', 'name' => 'John']);
            $user3 = new TestUser(['id' => '456', 'name' => 'Jane']);
            
            expect($user1->is($user2))->toBeTrue();
            expect($user1->isNot($user3))->toBeTrue();
        });
    });

    describe('ArrayAccess Implementation', function () {
        it('supports array access syntax', function () {
            $user = new TestUser(['name' => 'John Doe']);
            
            // Test offsetGet
            expect($user['name'])->toBe('John Doe');
            
            // Test offsetSet
            $user['email'] = 'john@example.com';
            expect($user['email'])->toBe('john@example.com');
            
            // Test offsetExists
            expect(isset($user['name']))->toBeTrue();
            expect(isset($user['nonexistent']))->toBeFalse();
            
            // Test offsetUnset
            unset($user['name']);
            expect(isset($user['name']))->toBeFalse();
        });
    });
});
