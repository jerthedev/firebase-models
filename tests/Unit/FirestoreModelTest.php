<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use Illuminate\Support\Carbon;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive FirestoreModel Test
 *
 * Migrated from:
 * - tests/Unit/FirestoreModelTest.php
 *
 * Uses new UnitTestSuite for optimized performance and memory management.
 */

// Test model for testing purposes
class TestUser extends FirestoreModel
{
    protected ?string $collection = 'users';

    protected array $fillable = [
        'id', 'name', 'email', 'active', 'age', 'metadata', 'created_at',
    ];

    protected array $casts = [
        'active' => 'boolean',
        'age' => 'integer',
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected array $hidden = [
        'password',
    ];
}

class FirestoreModelTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for model operations
        $this->setTestRequirements([
            'document_count' => 50,
            'memory_constraint' => false, // Disable strict memory constraints for model tests
            'needs_full_mockery' => false,
        ]);

        parent::setUp();
    }

    // ========================================
    // MODEL CREATION AND ATTRIBUTES
    // ========================================

    #[Test]
    public function it_can_create_models_and_manage_attributes()
    {
        // Test basic model creation
        $user = new TestUser();

        expect($user)->toBeInstanceOf(FirestoreModel::class);
        expect($user->exists)->toBeFalse();
        expect($user->wasRecentlyCreated)->toBeFalse();

        // Test model creation with attributes using TestDataFactory
        $userData = TestDataFactory::createUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'active' => true,
            'age' => 30,
        ]);

        $user = new TestUser($userData);

        expect($user->name)->toBe('John Doe');
        expect($user->email)->toBe('john@example.com');
        expect($user->active)->toBe(true);
        expect($user->age)->toBe(30);

        // Test fillable attributes protection
        $protectedUser = new TestUser([
            'name' => 'Jane Doe',
            'password' => 'secret', // Not fillable
            'admin' => true, // Not fillable
        ]);

        expect($protectedUser->name)->toBe('Jane Doe');
        expect($protectedUser->hasAttribute('password'))->toBeFalse();
        expect($protectedUser->hasAttribute('admin'))->toBeFalse();

        // Test attribute management
        $user->name = 'Updated Name';
        expect($user->name)->toBe('Updated Name');
        expect($user->hasAttribute('name'))->toBeTrue();
        expect($user->hasAttribute('nonexistent'))->toBeFalse();
    }

    #[Test]
    public function it_handles_attribute_casting_correctly()
    {
        // Test boolean casting
        $user = new TestUser(['active' => '1']);
        expect($user->active)->toBe(true);
        expect($user->hasCast('active', 'boolean'))->toBeTrue();

        // Test integer casting
        $user = new TestUser(['age' => '30']);
        expect($user->age)->toBe(30);
        expect($user->hasCast('age', 'integer'))->toBeTrue();

        // Test array casting
        $user = new TestUser(['metadata' => '{"key": "value"}']);
        expect($user->metadata)->toBe(['key' => 'value']);
        expect($user->hasCast('metadata', 'array'))->toBeTrue();

        // Test datetime casting
        $date = '2023-01-01 12:00:00';
        $user = new TestUser(['created_at' => $date]);
        expect($user->created_at)->toBeInstanceOf(Carbon::class);
        expect($user->created_at->format('Y-m-d H:i:s'))->toBe($date);

        // Performance test for casting operations
        $executionTime = $this->benchmark(function () {
            $testData = TestDataFactory::createUser([
                'active' => '1',
                'age' => '25',
                'metadata' => '{"test": "data"}',
                'created_at' => '2023-01-01 12:00:00',
            ]);

            return new TestUser($testData);
        });

        expect($executionTime)->toBeLessThan(0.01); // Casting should be very fast
    }

    // ========================================
    // DIRTY TRACKING AND MASS ASSIGNMENT
    // ========================================

    #[Test]
    public function it_tracks_dirty_attributes_and_handles_mass_assignment()
    {
        $userData = TestDataFactory::createUser(['name' => 'John Doe']);
        $user = new TestUser($userData);
        $user->syncOriginal();

        // Test clean state
        expect($user->isClean())->toBeTrue();

        // Test dirty tracking
        $user->name = 'Jane Doe';
        expect($user->isDirty(['name']))->toBeTrue();
        expect($user->isDirty('name'))->toBeTrue();
        expect($user->isClean('email'))->toBeTrue();

        // Test mass assignment protection
        expect($user->isFillable('name'))->toBeTrue();
        expect($user->isFillable('email'))->toBeTrue();
        expect($user->isFillable('password'))->toBeFalse();
        expect($user->isFillable('admin'))->toBeFalse();

        // Test force fill
        $user->forceFill(['password' => 'secret']);
        expect($user->hasAttribute('password'))->toBeTrue();
        expect($user->password)->toBe('secret');

        // Test fillable mass assignment
        $user->fill([
            'name' => 'Mass Assigned',
            'email' => 'mass@example.com',
            'password' => 'ignored', // Should be ignored
        ]);

        expect($user->name)->toBe('Mass Assigned');
        expect($user->email)->toBe('mass@example.com');
        expect($user->password)->toBe('secret'); // Should remain unchanged
    }

    // ========================================
    // TIMESTAMPS AND COLLECTION MANAGEMENT
    // ========================================

    #[Test]
    public function it_manages_timestamps_and_collections_correctly()
    {
        $user = new TestUser();

        // Test timestamp configuration
        expect($user->usesTimestamps())->toBeTrue();
        expect($user->getCreatedAtColumn())->toBe('created_at');
        expect($user->getUpdatedAtColumn())->toBe('updated_at');

        // Test disabling timestamps
        $user->timestamps = false;
        expect($user->usesTimestamps())->toBeFalse();

        // Test collection management
        expect($user->getCollection())->toBe('users');

        $user->setCollection('custom_users');
        expect($user->getCollection())->toBe('custom_users');

        // Test primary key management
        expect($user->getKeyName())->toBe('id');
        expect($user->getKeyType())->toBe('string');
        expect($user->getIncrementing())->toBeFalse();

        // Test custom primary key
        $user->setKeyName('uuid');
        $user->setKeyType('string');
        expect($user->getKeyName())->toBe('uuid');
        expect($user->getKeyType())->toBe('string');
    }

    // ========================================
    // SERIALIZATION AND COMPARISON
    // ========================================

    #[Test]
    public function it_handles_serialization_and_model_comparison()
    {
        $userData = TestDataFactory::createUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'active' => true,
        ]);

        $user = new TestUser($userData);

        // Test array conversion
        $array = $user->toArray();
        expect($array)->toHaveKeys(['name', 'email', 'active']);
        expect($array['name'])->toBe('John Doe');
        expect($array['email'])->toBe('john@example.com');
        expect($array['active'])->toBe(true);

        // Test JSON conversion
        $json = $user->toJson();
        $decoded = json_decode($json, true);
        expect($decoded['name'])->toBe('John Doe');
        expect($decoded['email'])->toBe('john@example.com');

        // Test hidden attributes
        $user->forceFill(['password' => 'secret']);
        $array = $user->toArray();
        expect($array)->not->toHaveKey('password'); // Should be hidden

        // Test model comparison
        $user1 = new TestUser(['id' => '123', 'name' => 'John']);
        $user2 = new TestUser(['id' => '123', 'name' => 'John']);
        $user3 = new TestUser(['id' => '456', 'name' => 'Jane']);

        expect($user1->is($user2))->toBeTrue();
        expect($user1->is($user3))->toBeFalse();

        // Performance test for serialization
        $executionTime = $this->benchmark(function () use ($user) {
            return $user->toArray();
        });

        expect($executionTime)->toBeLessThan(0.005); // Serialization should be fast
    }

    // ========================================
    // ARRAY ACCESS AND PERFORMANCE
    // ========================================

    #[Test]
    public function it_supports_array_access_and_optimizes_performance()
    {
        $userData = TestDataFactory::createUser(['name' => 'John Doe']);
        $user = new TestUser($userData);

        // Test ArrayAccess implementation
        expect($user['name'])->toBe('John Doe');
        expect(isset($user['name']))->toBeTrue();
        expect(isset($user['nonexistent']))->toBeFalse();

        $user['email'] = 'john@example.com';
        expect($user['email'])->toBe('john@example.com');

        unset($user['email']);
        expect(isset($user['email']))->toBeFalse();

        // Test performance with multiple operations
        $executionTime = $this->benchmark(function () {
            $testData = TestDataFactory::createUser();
            $model = new TestUser($testData);

            // Perform various operations
            $model->fill(['name' => 'Performance Test']);
            $model->toArray();
            $model->isDirty('name');
            $model->getOriginal('name');

            return $model;
        });

        expect($executionTime)->toBeLessThan(0.01); // All operations should be fast

        // Memory usage test
        $this->enableMemoryMonitoring();

        // Create multiple models
        $models = [];
        for ($i = 0; $i < 10; $i++) {
            $models[] = new TestUser(TestDataFactory::createUser());
        }

        $this->assertMemoryUsageWithinThreshold(50 * 1024 * 1024); // 50MB threshold for model tests
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create test models
        $models = $this->createTestModels(TestUser::class, 3);

        // Verify models were created
        expect($models)->toHaveCount(3);
        foreach ($models as $model) {
            expect($model)->toBeInstanceOf(TestUser::class);
        }

        // Clear test data
        $this->clearTestData();

        // Verify cleanup
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
    }
}
