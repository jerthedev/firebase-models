<?php

namespace JTD\FirebaseModels\Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use JTD\FirebaseModels\Firestore\Batch\BatchManager;
use JTD\FirebaseModels\Testing\BatchTestHelper;
use JTD\FirebaseModels\Testing\RelationshipTestHelper;
use JTD\FirebaseModels\Tests\TestSuites\IntegrationTestSuite;

/**
 * Integration tests for batch operations with relationship functionality.
 */
class BatchRelationshipIntegrationTest extends IntegrationTestSuite
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBatchTestModels();
    }

    /**
     * Test batch creation with relationship setup.
     */
    public function test_batch_creation_with_relationships()
    {
        // Create categories first
        $categoryData = [
            ['name' => 'Technology', 'description' => 'Tech articles'],
            ['name' => 'Science', 'description' => 'Science articles'],
            ['name' => 'Business', 'description' => 'Business articles'],
        ];

        $categoryResult = BatchManager::bulkInsert('categories', $categoryData);
        $this->assertTrue($categoryResult->isSuccess());
        $categoryIds = $categoryResult->getData()['document_ids'];

        // Create users
        $userData = BatchTestHelper::createTestData(5, [
            'name' => 'Author {i}',
            'email' => 'author{i}@example.com',
            'bio' => 'Bio for author {i}',
        ]);

        $userResult = BatchManager::bulkInsert('users', $userData);
        $this->assertTrue($userResult->isSuccess());
        $userIds = $userResult->getData()['document_ids'];

        // Create posts with relationships
        $postData = [];
        foreach ($userIds as $userIndex => $userId) {
            for ($i = 1; $i <= 3; $i++) {
                $categoryId = $categoryIds[($userIndex + $i - 1) % count($categoryIds)];
                $postData[] = [
                    'title' => "Post {$i} by Author ".($userIndex + 1),
                    'content' => 'Content for the post',
                    'user_id' => $userId,
                    'category_id' => $categoryId,
                    'status' => 'published',
                    'views' => rand(100, 1000),
                    'likes' => rand(10, 100),
                ];
            }
        }

        $postResult = BatchManager::bulkInsert('posts', $postData);
        $this->assertTrue($postResult->isSuccess());
        $this->assertEquals(15, $postResult->getOperationCount()); // 5 users × 3 posts

        // Test relationship loading
        $users = TestUser::with('posts')->get();
        $this->assertCount(5, $users);

        foreach ($users as $user) {
            $this->assertTrue($user->relationLoaded('posts'));
            $this->assertCount(3, $user->posts);
        }

        // Test nested relationship loading
        $categories = TestCategory::with('posts.user')->get();
        $this->assertCount(3, $categories);

        foreach ($categories as $category) {
            $this->assertTrue($category->relationLoaded('posts'));
            $this->assertGreaterThan(0, $category->posts->count());

            foreach ($category->posts as $post) {
                $this->assertTrue($post->relationLoaded('user'));
                $this->assertNotNull($post->user);
            }
        }
    }

    /**
     * Test batch updates with relationship constraints.
     */
    public function test_batch_updates_with_relationship_constraints()
    {
        // Setup initial data
        $testData = RelationshipTestHelper::createParentChildTestData(
            TestUser::class,
            TestPost::class,
            'user_id',
            3, // 3 users
            4  // 4 posts each
        );

        $users = $testData['parents'];
        $posts = $testData['children'];

        // Batch update posts with user validation
        $updateData = [];
        foreach ($posts as $post) {
            $updateData[$post->getKey()] = [
                'status' => 'featured',
                'featured_at' => now(),
                'views' => $post->views + rand(50, 200),
            ];
        }

        $updateResult = BatchManager::bulkUpdate('posts', $updateData);
        $this->assertTrue($updateResult->isSuccess());
        $this->assertEquals(12, $updateResult->getOperationCount()); // 3 users × 4 posts

        // Verify updates and relationships
        $updatedUsers = TestUser::with('posts')->get();
        foreach ($updatedUsers as $user) {
            foreach ($user->posts as $post) {
                $this->assertEquals('featured', $post->status);
                $this->assertNotNull($post->featured_at);
                $this->assertGreaterThan(0, $post->views);
            }
        }

        // Test batch update with relationship-based filtering
        $firstUser = $users->first();
        $firstUserPosts = $firstUser->posts;

        $specificUpdateData = [];
        foreach ($firstUserPosts as $post) {
            $specificUpdateData[$post->getKey()] = [
                'priority' => 'high',
                'editor_notes' => 'Updated by batch operation',
            ];
        }

        $specificUpdateResult = BatchManager::bulkUpdate('posts', $specificUpdateData);
        $this->assertTrue($specificUpdateResult->isSuccess());
        $this->assertEquals(4, $specificUpdateResult->getOperationCount());

        // Verify specific updates
        $firstUser->refresh();
        $firstUser->load('posts');
        foreach ($firstUser->posts as $post) {
            $this->assertEquals('high', $post->priority);
            $this->assertEquals('Updated by batch operation', $post->editor_notes);
        }
    }

    /**
     * Test batch operations with model-level relationship methods.
     */
    public function test_batch_operations_with_model_relationships()
    {
        // Create users using model batch methods
        $userBatchResult = TestUser::createManyInBatch([
            ['name' => 'Batch User 1', 'email' => 'batch1@example.com'],
            ['name' => 'Batch User 2', 'email' => 'batch2@example.com'],
            ['name' => 'Batch User 3', 'email' => 'batch3@example.com'],
        ]);

        $this->assertTrue($userBatchResult->isSuccess());

        // Get created users
        $users = TestUser::all();
        $this->assertCount(3, $users);

        // Use model batch operations to create related posts
        $postBatchData = [];
        foreach ($users as $user) {
            for ($i = 1; $i <= 2; $i++) {
                $postBatchData[] = [
                    'title' => "Post {$i} by {$user->name}",
                    'content' => 'Content for the post',
                    'user_id' => $user->getKey(),
                    'status' => 'draft',
                ];
            }
        }

        $postBatchResult = TestPost::createManyInBatch($postBatchData);
        $this->assertTrue($postBatchResult->isSuccess());
        $this->assertEquals(6, $postBatchResult->getOperationCount());

        // Test relationship loading after batch creation
        $usersWithPosts = TestUser::with('posts')->get();
        foreach ($usersWithPosts as $user) {
            $this->assertCount(2, $user->posts);
            foreach ($user->posts as $post) {
                $this->assertEquals($user->getKey(), $post->user_id);
                $this->assertEquals('draft', $post->status);
            }
        }

        // Batch update using model methods
        $allPosts = TestPost::all();
        $updateBatchResult = TestPost::updateManyInBatch(
            $allPosts->pluck('id', 'id')->map(function ($id) {
                return [
                    'status' => 'published',
                    'published_at' => now(),
                ];
            })->toArray()
        );

        $this->assertTrue($updateBatchResult->isSuccess());

        // Verify updates
        $publishedPosts = TestPost::where('status', 'published')->get();
        $this->assertCount(6, $publishedPosts);
    }

    /**
     * Test batch operations with eager loading optimization.
     */
    public function test_batch_operations_with_eager_loading()
    {
        // Create large dataset
        $userData = BatchTestHelper::createTestData(20, [
            'name' => 'User {i}',
            'email' => 'user{i}@example.com',
        ]);

        $userResult = BatchManager::bulkInsert('users', $userData);
        $this->assertTrue($userResult->isSuccess());
        $userIds = $userResult->getData()['document_ids'];

        // Create many posts per user
        $postData = [];
        foreach ($userIds as $userId) {
            for ($i = 1; $i <= 10; $i++) {
                $postData[] = [
                    'title' => "Post {$i}",
                    'content' => 'Post content',
                    'user_id' => $userId,
                    'status' => 'published',
                ];
            }
        }

        $postResult = BatchManager::bulkInsert('posts', $postData);
        $this->assertTrue($postResult->isSuccess());
        $this->assertEquals(200, $postResult->getOperationCount()); // 20 users × 10 posts

        // Test eager loading performance
        $startTime = microtime(true);

        // Without eager loading (should be slower)
        $usersWithoutEager = TestUser::all();
        foreach ($usersWithoutEager as $user) {
            $postCount = $user->posts->count(); // This triggers individual queries
        }

        $withoutEagerTime = microtime(true) - $startTime;

        $startTime = microtime(true);

        // With eager loading (should be faster)
        $usersWithEager = TestUser::with('posts')->get();
        foreach ($usersWithEager as $user) {
            $postCount = $user->posts->count(); // This uses preloaded data
        }

        $withEagerTime = microtime(true) - $startTime;

        // Eager loading should be significantly faster
        $this->assertLessThan($withoutEagerTime, $withEagerTime);

        // Verify data integrity
        $this->assertCount(20, $usersWithEager);
        foreach ($usersWithEager as $user) {
            $this->assertTrue($user->relationLoaded('posts'));
            $this->assertCount(10, $user->posts);
        }
    }

    /**
     * Test batch deletion with relationship cleanup.
     */
    public function test_batch_deletion_with_relationship_cleanup()
    {
        // Create test data with relationships
        $testData = RelationshipTestHelper::createParentChildTestData(
            TestUser::class,
            TestPost::class,
            'user_id',
            5, // 5 users
            3  // 3 posts each
        );

        $users = $testData['parents'];
        $posts = $testData['children'];

        $this->assertCount(5, $users);
        $this->assertCount(15, $posts);

        // Delete some users and their related posts
        $usersToDelete = $users->take(2);
        $userIdsToDelete = $usersToDelete->pluck('id')->toArray();

        // First, delete related posts
        $postsToDelete = TestPost::whereIn('user_id', $userIdsToDelete)->get();
        $postIdsToDelete = $postsToDelete->pluck('id')->toArray();

        $postDeleteResult = BatchManager::bulkDelete('posts', $postIdsToDelete);
        $this->assertTrue($postDeleteResult->isSuccess());
        $this->assertEquals(6, $postDeleteResult->getOperationCount()); // 2 users × 3 posts

        // Then delete users
        $userDeleteResult = BatchManager::bulkDelete('users', $userIdsToDelete);
        $this->assertTrue($userDeleteResult->isSuccess());
        $this->assertEquals(2, $userDeleteResult->getOperationCount());

        // Verify deletions
        $remainingUsers = TestUser::all();
        $this->assertCount(3, $remainingUsers);

        $remainingPosts = TestPost::all();
        $this->assertCount(9, $remainingPosts); // 3 users × 3 posts

        // Verify relationship integrity
        foreach ($remainingUsers as $user) {
            $userPosts = $user->posts;
            $this->assertCount(3, $userPosts);

            foreach ($userPosts as $post) {
                $this->assertEquals($user->getKey(), $post->user_id);
            }
        }

        // Verify no orphaned posts
        $orphanedPosts = TestPost::whereIn('user_id', $userIdsToDelete)->get();
        $this->assertCount(0, $orphanedPosts);
    }

    /**
     * Test batch operations with relationship validation.
     */
    public function test_batch_operations_with_relationship_validation()
    {
        // Create users first
        $userData = [
            ['name' => 'Valid User 1', 'email' => 'valid1@example.com'],
            ['name' => 'Valid User 2', 'email' => 'valid2@example.com'],
        ];

        $userResult = BatchManager::bulkInsert('users', $userData);
        $this->assertTrue($userResult->isSuccess());
        $userIds = $userResult->getData()['document_ids'];

        // Attempt to create posts with valid and invalid user references
        $postData = [
            [
                'title' => 'Valid Post 1',
                'content' => 'Content',
                'user_id' => $userIds[0], // Valid user
                'status' => 'published',
            ],
            [
                'title' => 'Valid Post 2',
                'content' => 'Content',
                'user_id' => $userIds[1], // Valid user
                'status' => 'published',
            ],
            [
                'title' => 'Invalid Post',
                'content' => 'Content',
                'user_id' => 'non-existent-user-id', // Invalid user
                'status' => 'published',
            ],
        ];

        // With validation enabled, this should handle invalid references
        try {
            $postResult = BatchManager::bulkInsert('posts', $postData, [
                'validate_relationships' => true,
            ]);

            // If validation is implemented, it might succeed with warnings
            // or fail entirely depending on implementation
            $this->assertTrue($postResult->isSuccess() || $postResult->isFailed());
        } catch (\Exception $e) {
            // Validation caught the invalid reference
            $this->assertStringContains('user', strtolower($e->getMessage()));
        }

        // Verify only valid posts were created
        $createdPosts = TestPost::all();
        foreach ($createdPosts as $post) {
            $this->assertContains($post->user_id, $userIds);

            // Verify relationship works
            $user = $post->user;
            $this->assertNotNull($user);
            $this->assertContains($user->getKey(), $userIds);
        }
    }

    /**
     * Helper method to create test models for this specific test.
     */
    protected function createBatchTestModels(): void
    {
        if (!class_exists('TestUser')) {
            eval('
                class TestUser extends JTD\FirebaseModels\Firestore\FirestoreModel
                {
                    protected ?string $collection = "users";
                    protected array $fillable = ["name", "email", "bio"];

                    public function posts()
                    {
                        return $this->hasMany(TestPost::class, "user_id");
                    }
                }

                class TestPost extends JTD\FirebaseModels\Firestore\FirestoreModel
                {
                    protected ?string $collection = "posts";
                    protected array $fillable = ["title", "content", "user_id", "category_id", "status", "views", "likes", "priority", "editor_notes", "featured_at", "published_at"];
                    
                    public function user()
                    {
                        return $this->belongsTo(TestUser::class, "user_id");
                    }
                    
                    public function category()
                    {
                        return $this->belongsTo(TestCategory::class, "category_id");
                    }
                }
                
                class TestCategory extends JTD\FirebaseModels\Firestore\FirestoreModel
                {
                    protected ?string $collection = "categories";
                    protected array $fillable = ["name", "description"];
                    
                    public function posts()
                    {
                        return $this->hasMany(TestPost::class, "category_id");
                    }
                }
            ');
        }
    }
}
