<?php

namespace JTD\FirebaseModels\Tests\E2E\Models;

use JTD\FirebaseModels\Tests\E2E\BaseE2ETestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Basic E2E tests for model operations with real Firebase.
 */
#[Group('e2e')]
#[Group('models')]
class BasicModelE2ETest extends BaseE2ETestCase
{
    private TestUser $userModel;

    private string $testCollection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testCollection = $this->getTestCollection('users');
        $this->userModel = (new TestUser())->setCollection($this->testCollection);
    }

    #[Test]
    public function it_can_create_a_model_in_real_firestore(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'JOHN.DOE@EXAMPLE.COM', // Test mutator
            'active' => true,
            'role' => 'user',
            'permissions' => ['read', 'write'],
        ];

        $user = $this->userModel->create($userData);

        // Verify the model was created
        $this->assertNotNull($user->getKey());
        $this->assertTrue($user->exists);
        $this->assertTrue($user->wasRecentlyCreated);

        // Verify data was saved correctly
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john.doe@example.com', $user->email); // Mutator applied
        $this->assertTrue($user->active);
        $this->assertEquals('user', $user->role);
        $this->assertEquals(['read', 'write'], $user->permissions);

        // Verify in real Firestore
        $this->assertDocumentExists($this->testCollection, $user->getKey());
        $this->assertDocumentHasData($this->testCollection, $user->getKey(), [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'active' => true,
            'role' => 'user',
        ]);
    }

    #[Test]
    public function it_can_read_a_model_from_real_firestore(): void
    {
        // Create test data directly in Firestore
        $testData = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'active' => true,
            'role' => 'admin',
            'permissions' => ['read', 'write', 'delete'],
        ];

        $createdDoc = $this->createTestDocument('users', $testData);
        $documentId = $createdDoc['id'];

        // Read using the model
        $user = $this->userModel->find($documentId);

        $this->assertNotNull($user);
        $this->assertEquals($documentId, $user->getKey());
        $this->assertEquals('Jane Smith', $user->name);
        $this->assertEquals('jane@example.com', $user->email);
        $this->assertTrue($user->active);
        $this->assertEquals('admin', $user->role);
        $this->assertEquals(['read', 'write', 'delete'], $user->permissions);
    }

    #[Test]
    public function it_can_update_a_model_in_real_firestore(): void
    {
        // Create a user
        $user = $this->userModel->create([
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'active' => true,
            'role' => 'user',
        ]);

        $originalId = $user->getKey();

        // Update the user
        $user->name = 'Robert Wilson';
        $user->role = 'moderator';
        $user->active = false;

        $result = $user->save();

        $this->assertTrue($result);
        $this->assertEquals($originalId, $user->getKey()); // ID should not change

        // Verify in real Firestore
        $this->assertDocumentHasData($this->testCollection, $originalId, [
            'name' => 'Robert Wilson',
            'role' => 'moderator',
            'active' => false,
        ]);

        // Verify by reading fresh from database
        $freshUser = $this->userModel->find($originalId);
        $this->assertEquals('Robert Wilson', $freshUser->name);
        $this->assertEquals('moderator', $freshUser->role);
        $this->assertFalse($freshUser->active);
    }

    #[Test]
    public function it_can_delete_a_model_from_real_firestore(): void
    {
        // Create a user
        $user = $this->userModel->create([
            'name' => 'Delete Me',
            'email' => 'delete@example.com',
            'active' => true,
        ]);

        $userId = $user->getKey();

        // Verify it exists
        $this->assertDocumentExists($this->testCollection, $userId);

        // Delete the user
        $result = $user->delete();

        $this->assertTrue($result);

        // Verify it's deleted from Firestore
        $this->assertDocumentNotExists($this->testCollection, $userId);

        // Verify we can't find it anymore
        $deletedUser = $this->userModel->find($userId);
        $this->assertNull($deletedUser);
    }

    #[Test]
    public function it_can_query_models_from_real_firestore(): void
    {
        // Create multiple test users
        $users = [
            ['name' => 'Active User 1', 'email' => 'active1@example.com', 'active' => true, 'role' => 'user'],
            ['name' => 'Active User 2', 'email' => 'active2@example.com', 'active' => true, 'role' => 'admin'],
            ['name' => 'Inactive User', 'email' => 'inactive@example.com', 'active' => false, 'role' => 'user'],
        ];

        foreach ($users as $userData) {
            $this->userModel->create($userData);
        }

        // Query active users
        $activeUsers = $this->userModel->where('active', true)->get();
        $this->assertCount(2, $activeUsers);

        // Query by role
        $adminUsers = $this->userModel->where('role', 'admin')->get();
        $this->assertCount(1, $adminUsers);
        $this->assertEquals('Active User 2', $adminUsers->first()->name);

        // Query with scope
        $activeUsersViaScope = $this->userModel->active()->get();
        $this->assertCount(2, $activeUsersViaScope);
    }

    #[Test]
    public function it_handles_accessors_and_mutators_correctly(): void
    {
        $user = $this->userModel->create([
            'name' => 'test user',
            'email' => 'TEST@EXAMPLE.COM',
            'active' => true,
        ]);

        // Test mutator (email should be lowercase)
        $this->assertEquals('test@example.com', $user->email);

        // Test accessor (formatted name should be title case)
        $this->assertEquals('Test User', $user->formatted_name);
    }

    #[Test]
    public function it_handles_custom_methods_correctly(): void
    {
        $user = $this->userModel->create([
            'name' => 'Permission User',
            'email' => 'permissions@example.com',
            'active' => true,
            'permissions' => ['read'],
        ]);

        // Test permission methods
        $this->assertTrue($user->hasPermission('read'));
        $this->assertFalse($user->hasPermission('write'));

        // Add permission
        $user->addPermission('write');
        $this->assertTrue($user->hasPermission('write'));
        $this->assertEquals(['read', 'write'], $user->permissions);

        // Remove permission
        $user->removePermission('read');
        $this->assertFalse($user->hasPermission('read'));
        $this->assertEquals(['write'], $user->permissions);
    }

    #[Test]
    public function it_handles_timestamps_correctly(): void
    {
        $user = $this->userModel->create([
            'name' => 'Timestamp User',
            'email' => 'timestamp@example.com',
            'active' => true,
        ]);

        // Verify timestamps were set
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->updated_at);

        $originalUpdatedAt = $user->updated_at;

        // Wait a moment and update
        sleep(1);
        $user->name = 'Updated Timestamp User';
        $user->save();

        // Verify updated_at changed
        $this->assertTrue($user->updated_at->gt($originalUpdatedAt));
    }
}
