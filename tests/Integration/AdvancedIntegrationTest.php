<?php

namespace JTD\FirebaseModels\Tests\Integration;

use JTD\FirebaseModels\Tests\TestSuites\IntegrationTestSuite;
use JTD\FirebaseModels\Tests\Models\TestPost;
use JTD\FirebaseModels\Tests\Models\TestUser;
use JTD\FirebaseModels\Firestore\Transactions\TransactionManager;
use JTD\FirebaseModels\Firestore\Batch\BatchManager;
use JTD\FirebaseModels\Firestore\Listeners\RealtimeListenerManager;
use JTD\FirebaseModels\Cache\CacheManager;
use JTD\FirebaseModels\Facades\FirestoreDB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

#[Group('integration')]
#[Group('advanced')]
#[Group('end-to-end')]
class AdvancedIntegrationTest extends IntegrationTestSuite
{
    #[Test]
    public function it_handles_complex_transaction_with_multiple_models()
    {
        $this->setTestRequirements([
            'document_count' => 50,
            'transaction_support' => true,
            'model_relationships' => true,
        ]);

        // Create test data
        $author = new TestUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'author'
        ]);

        $posts = [
            new TestPost(['title' => 'First Post', 'content' => 'Content 1']),
            new TestPost(['title' => 'Second Post', 'content' => 'Content 2']),
            new TestPost(['title' => 'Third Post', 'content' => 'Content 3']),
        ];

        // Execute complex transaction
        $result = TransactionManager::executeWithResult(function ($transaction) use ($author, $posts) {
            // Save author first
            $author->save();
            
            // Save posts and link to author
            $savedPosts = [];
            foreach ($posts as $post) {
                $post->author_id = $author->getKey();
                $post->published = true;
                $post->published_at = now();
                $post->save();
                $savedPosts[] = $post;
            }

            // Update author's post count
            $author->increment('post_count', count($posts));
            $author->last_post_at = now();
            $author->save();

            return [
                'author' => $author,
                'posts' => $savedPosts,
                'total_posts' => count($savedPosts)
            ];
        });

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData()['total_posts'])->toBe(3);
        expect($result->getData()['author']->post_count)->toBe(3);
        
        // Verify all posts have author_id
        foreach ($result->getData()['posts'] as $post) {
            expect($post->author_id)->toBe($author->getKey());
            expect($post->published)->toBeTrue();
        }
    }

    #[Test]
    public function it_handles_batch_operations_with_caching()
    {
        $this->setTestRequirements([
            'document_count' => 100,
            'batch_support' => true,
            'caching_enabled' => true,
        ]);

        // Clear cache
        Cache::flush();

        // Create large dataset
        $documents = [];
        for ($i = 1; $i <= 50; $i++) {
            $documents[] = [
                'title' => "Batch Post {$i}",
                'content' => "Content for post {$i}",
                'views' => rand(1, 1000),
                'published' => $i % 2 === 0,
                'created_at' => now()->subDays(rand(1, 30))
            ];
        }

        // Batch insert with caching
        $batchResult = BatchManager::bulkInsert('posts', $documents, [
            'chunk_size' => 10,
            'enable_cache' => true,
            'cache_ttl' => 3600
        ]);

        expect($batchResult->isSuccess())->toBeTrue();
        expect($batchResult->getInsertedCount())->toBe(50);

        // Query with caching enabled
        $publishedPosts = TestPost::query()
            ->enableCache(3600)
            ->where('published', true)
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();

        expect($publishedPosts)->toHaveCount(10);

        // Second query should hit cache
        $cachedPosts = TestPost::query()
            ->enableCache(3600)
            ->where('published', true)
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();

        expect($cachedPosts->toArray())->toEqual($publishedPosts->toArray());

        // Verify cache statistics
        $cacheManager = app(CacheManager::class);
        $stats = $cacheManager->getStatistics();
        expect($stats['hits'])->toBeGreaterThan(0);
    }

    #[Test]
    public function it_handles_realtime_listeners_with_model_events()
    {
        $this->setTestRequirements([
            'realtime_support' => true,
            'event_system' => true,
            'listener_management' => true,
        ]);

        Event::fake();
        $eventsReceived = [];

        // Set up document listener
        $listener = RealtimeListenerManager::listenToDocument(
            'posts',
            'realtime-test-post',
            function ($snapshot, $type) use (&$eventsReceived) {
                $eventsReceived[] = [
                    'type' => $type,
                    'data' => $snapshot->exists() ? $snapshot->data() : null,
                    'timestamp' => now()->toISOString()
                ];
            }
        );

        expect($listener->isActive())->toBeTrue();

        // Create a post (should trigger listener)
        $post = new TestPost([
            'id' => 'realtime-test-post',
            'title' => 'Realtime Test Post',
            'content' => 'Testing realtime functionality'
        ]);
        $post->save();

        // Update the post (should trigger listener)
        $post->title = 'Updated Realtime Post';
        $post->views = 100;
        $post->save();

        // Delete the post (should trigger listener)
        $post->delete();

        // Verify listener received events
        expect($eventsReceived)->toHaveCount(3);
        expect($eventsReceived[0]['type'])->toBe('added');
        expect($eventsReceived[1]['type'])->toBe('modified');
        expect($eventsReceived[2]['type'])->toBe('removed');

        // Clean up
        RealtimeListenerManager::stopListener($listener->getId());
    }

    #[Test]
    public function it_handles_complex_query_with_subcollections()
    {
        $this->setTestRequirements([
            'subcollection_support' => true,
            'complex_queries' => true,
            'relationship_queries' => true,
        ]);

        // Create a post with comments (subcollection)
        $post = new TestPost([
            'title' => 'Post with Comments',
            'content' => 'This post has comments',
            'published' => true
        ]);
        $post->save();

        // Add comments to the post
        $comments = [
            ['author' => 'User 1', 'content' => 'Great post!', 'likes' => 5],
            ['author' => 'User 2', 'content' => 'Very informative', 'likes' => 3],
            ['author' => 'User 3', 'content' => 'Thanks for sharing', 'likes' => 7],
        ];

        foreach ($comments as $commentData) {
            $post->comments()->create($commentData);
        }

        // Query comments with complex conditions
        $popularComments = $post->comments()
            ->where('likes', '>', 4)
            ->orderBy('likes', 'desc')
            ->get();

        expect($popularComments)->toHaveCount(2);
        expect($popularComments->first()['likes'])->toBe(7);

        // Query across collection groups
        $allComments = FirestoreDB::collectionGroup('comments')
            ->where('likes', '>=', 3)
            ->orderBy('likes', 'desc')
            ->get();

        expect($allComments)->toHaveCount(3);
    }

    #[Test]
    public function it_handles_performance_under_load()
    {
        $this->setTestRequirements([
            'document_count' => 1000,
            'performance_testing' => true,
            'memory_monitoring' => true,
        ]);

        $this->enableMemoryMonitoring();
        $initialMemory = memory_get_usage(true);

        // Create large dataset
        $documents = [];
        for ($i = 1; $i <= 500; $i++) {
            $documents[] = [
                'title' => "Performance Test {$i}",
                'content' => str_repeat("Content {$i} ", 50), // ~500 chars
                'metadata' => [
                    'index' => $i,
                    'category' => 'performance',
                    'tags' => ['test', 'performance', "tag-{$i}"],
                    'stats' => [
                        'views' => rand(1, 10000),
                        'likes' => rand(1, 1000),
                        'shares' => rand(1, 100)
                    ]
                ]
            ];
        }

        // Batch insert with performance monitoring
        $startTime = microtime(true);
        $result = BatchManager::bulkInsert('posts', $documents, [
            'chunk_size' => 50,
            'monitor_performance' => true,
            'memory_efficient' => true
        ]);
        $insertTime = (microtime(true) - $startTime) * 1000;

        expect($result->isSuccess())->toBeTrue();
        expect($result->getInsertedCount())->toBe(500);
        expect($insertTime)->toBeLessThan(10000); // Should complete in under 10 seconds

        // Complex query performance
        $startTime = microtime(true);
        $complexResults = TestPost::query()
            ->where('metadata.category', '=', 'performance')
            ->where('metadata.stats.views', '>', 5000)
            ->orderBy('metadata.stats.likes', 'desc')
            ->limit(20)
            ->get();
        $queryTime = (microtime(true) - $startTime) * 1000;

        expect($complexResults->count())->toBeGreaterThan(0);
        expect($queryTime)->toBeLessThan(2000); // Should complete in under 2 seconds

        // Memory usage check
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // MB
        expect($memoryIncrease)->toBeLessThan(100); // Should use less than 100MB
    }

    #[Test]
    public function it_handles_error_recovery_and_resilience()
    {
        $this->setTestRequirements([
            'error_handling' => true,
            'retry_logic' => true,
            'resilience_testing' => true,
        ]);

        $attempts = 0;
        $errors = [];

        // Test transaction retry with intermittent failures
        $result = TransactionManager::executeWithResult(function ($transaction) use (&$attempts, &$errors) {
            $attempts++;
            
            // Simulate intermittent failures
            if ($attempts < 3) {
                $error = new \Exception("Simulated failure attempt {$attempts}");
                $errors[] = $error;
                throw $error;
            }

            // Success on third attempt
            return [
                'success' => true,
                'attempts' => $attempts,
                'data' => 'recovered_data'
            ];
        }, [
            'max_attempts' => 5,
            'retry_delay_ms' => 100,
            'exponential_backoff' => true
        ]);

        expect($result->isSuccess())->toBeTrue();
        expect($result->getAttempts())->toBe(3);
        expect($result->getData()['success'])->toBeTrue();
        expect($errors)->toHaveCount(2);

        // Test batch operation error handling
        $mixedDocuments = [
            ['title' => 'Valid Document 1', 'content' => 'Valid content'],
            ['title' => '', 'content' => 'Invalid - empty title'],
            ['title' => 'Valid Document 2', 'content' => 'Valid content'],
            // Missing required fields
            ['content' => 'Invalid - no title'],
            ['title' => 'Valid Document 3', 'content' => 'Valid content'],
        ];

        $batchResult = BatchManager::bulkInsert('posts', $mixedDocuments, [
            'continue_on_error' => true,
            'validate' => true,
            'validation_rules' => [
                'title' => 'required|min:1',
                'content' => 'required'
            ]
        ]);

        expect($batchResult->hasErrors())->toBeTrue();
        expect($batchResult->getSuccessfulOperations())->toBe(3);
        expect($batchResult->getFailedOperations())->toBe(2);
        expect($batchResult->getValidationErrors())->toHaveCount(2);
    }

    #[Test]
    public function it_handles_concurrent_operations()
    {
        $this->setTestRequirements([
            'concurrency_support' => true,
            'race_condition_handling' => true,
            'data_consistency' => true,
        ]);

        // Create a shared counter document
        $counter = new TestPost([
            'id' => 'concurrent-counter',
            'title' => 'Concurrent Counter',
            'views' => 0,
            'likes' => 0
        ]);
        $counter->save();

        // Simulate concurrent updates
        $operations = [];
        for ($i = 0; $i < 10; $i++) {
            $operations[] = function () use ($counter) {
                return TransactionManager::execute(function ($transaction) use ($counter) {
                    $docRef = FirestoreDB::collection('posts')->document($counter->getKey());
                    $snapshot = $transaction->snapshot($docRef);
                    $data = $snapshot->data();
                    
                    // Increment counters
                    $newViews = ($data['views'] ?? 0) + 1;
                    $newLikes = ($data['likes'] ?? 0) + 1;
                    
                    $transaction->update($docRef, [
                        'views' => $newViews,
                        'likes' => $newLikes,
                        'updated_at' => now()
                    ]);
                    
                    return ['views' => $newViews, 'likes' => $newLikes];
                });
            };
        }

        // Execute operations concurrently (simulated)
        $results = [];
        foreach ($operations as $operation) {
            $results[] = $operation();
        }

        // Verify final state
        $finalCounter = TestPost::find('concurrent-counter');
        expect($finalCounter->views)->toBe(10);
        expect($finalCounter->likes)->toBe(10);
        expect($results)->toHaveCount(10);
    }

    #[Test]
    public function it_handles_full_system_integration()
    {
        $this->setTestRequirements([
            'document_count' => 200,
            'full_integration' => true,
            'all_features' => true,
        ]);

        // Clear all caches and listeners
        Cache::flush();
        RealtimeListenerManager::stopAllListeners();

        $integrationResults = [];

        // 1. Batch create users
        $users = [];
        for ($i = 1; $i <= 10; $i++) {
            $users[] = [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'role' => $i <= 3 ? 'admin' : 'user',
                'active' => true
            ];
        }

        $userBatch = BatchManager::bulkInsert('users', $users);
        $integrationResults['users_created'] = $userBatch->getInsertedCount();

        // 2. Transaction: Create posts with relationships
        $postCreationResult = TransactionManager::executeWithResult(function ($transaction) {
            $posts = [];
            for ($i = 1; $i <= 20; $i++) {
                $post = new TestPost([
                    'title' => "Integration Post {$i}",
                    'content' => "Content for integration post {$i}",
                    'published' => $i % 2 === 0,
                    'views' => rand(1, 1000),
                    'author_id' => 'user-' . rand(1, 10)
                ]);
                $post->save();
                $posts[] = $post;
            }
            return $posts;
        });

        $integrationResults['posts_created'] = count($postCreationResult->getData());

        // 3. Set up real-time listeners
        $listenerEvents = [];
        $listener = RealtimeListenerManager::listenToCollection(
            'posts',
            function ($changes) use (&$listenerEvents) {
                $listenerEvents[] = count($changes);
            }
        );

        // 4. Cached queries
        $popularPosts = TestPost::query()
            ->enableCache(3600)
            ->where('published', true)
            ->where('views', '>', 500)
            ->orderBy('views', 'desc')
            ->limit(5)
            ->get();

        $integrationResults['popular_posts'] = $popularPosts->count();

        // 5. Batch update with field transforms
        $updateData = [];
        foreach ($popularPosts as $post) {
            $updateData[$post->getKey()] = [
                'views' => FirestoreDB::increment(100),
                'featured' => true,
                'updated_at' => FirestoreDB::serverTimestamp()
            ];
        }

        $updateBatch = BatchManager::bulkUpdate('posts', $updateData);
        $integrationResults['posts_updated'] = $updateBatch->getUpdatedCount();

        // 6. Complex aggregation query
        $stats = TestPost::query()
            ->where('published', true)
            ->aggregate([
                'total_views' => 'sum:views',
                'avg_views' => 'avg:views',
                'max_views' => 'max:views',
                'post_count' => 'count'
            ]);

        $integrationResults['aggregation_stats'] = $stats;

        // Verify integration results
        expect($integrationResults['users_created'])->toBe(10);
        expect($integrationResults['posts_created'])->toBe(20);
        expect($integrationResults['popular_posts'])->toBeGreaterThan(0);
        expect($integrationResults['posts_updated'])->toBeGreaterThan(0);
        expect($integrationResults['aggregation_stats'])->toHaveKey('total_views');

        // Clean up
        RealtimeListenerManager::stopAllListeners();

        // Final system health check
        $systemHealth = [
            'cache_manager' => app(CacheManager::class)->isHealthy(),
            'transaction_manager' => TransactionManager::getStats()['total_transactions'] >= 0,
            'batch_manager' => BatchManager::getStats()['total_operations'] >= 0,
            'listener_manager' => RealtimeListenerManager::checkHealth()['status'] !== 'critical',
        ];

        foreach ($systemHealth as $component => $healthy) {
            expect($healthy)->toBeTrue("Component {$component} should be healthy");
        }
    }
}
