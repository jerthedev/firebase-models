<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use JTD\FirebaseModels\Tests\Models\TestPost;
use JTD\FirebaseModels\Firestore\FirestoreQueryBuilder;
use JTD\FirebaseModels\Facades\FirestoreDB;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Collection;

#[Group('unit')]
#[Group('core')]
#[Group('query-builder')]
class FirestoreQueryBuilderCoreTest extends UnitTestSuite
{
    #[Test]
    public function it_creates_query_builder_instances()
    {
        $query = FirestoreDB::collection('posts');
        
        expect($query)->toBeInstanceOf(FirestoreQueryBuilder::class);
        expect($query->getCollection())->toBe('posts');
    }

    #[Test]
    public function it_handles_basic_where_clauses()
    {
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'published' => true, 'views' => 100]),
            TestDataFactory::createPost(['id' => '2', 'published' => false, 'views' => 50]),
            TestDataFactory::createPost(['id' => '3', 'published' => true, 'views' => 200])
        ];

        $this->mockFirestoreQuery('posts', $testData);

        $query = FirestoreDB::collection('posts');
        
        // Test equality
        $results = $query->where('published', '=', true)->get();
        expect($results)->toHaveCount(2);

        // Test inequality
        $results = $query->where('views', '>', 75)->get();
        expect($results)->toHaveCount(2);

        // Test method chaining
        $chainedQuery = $query->where('published', true)->where('views', '>', 50);
        expect($chainedQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);
    }

    #[Test]
    public function it_handles_advanced_where_operators()
    {
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'status' => 'published', 'tags' => ['php', 'laravel']]),
            TestDataFactory::createPost(['id' => '2', 'status' => 'draft', 'tags' => ['javascript', 'react']]),
            TestDataFactory::createPost(['id' => '3', 'status' => 'archived', 'tags' => ['python', 'django']])
        ];

        $this->mockFirestoreQuery('posts', $testData);

        $query = FirestoreDB::collection('posts');

        // Test whereIn
        $results = $query->whereIn('status', ['published', 'draft'])->get();
        expect($results)->toHaveCount(2);

        // Test whereNotIn
        $results = $query->whereNotIn('status', ['archived'])->get();
        expect($results)->toHaveCount(2);

        // Test array-contains
        $results = $query->where('tags', 'array-contains', 'php')->get();
        expect($results)->toHaveCount(1);
    }

    #[Test]
    public function it_handles_ordering()
    {
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'Post A', 'views' => 100, 'created_at' => '2024-01-01']),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Post B', 'views' => 200, 'created_at' => '2024-01-02']),
            TestDataFactory::createPost(['id' => '3', 'title' => 'Post C', 'views' => 150, 'created_at' => '2024-01-03'])
        ];

        $this->mockFirestoreQuery('posts', $testData);

        $query = FirestoreDB::collection('posts');

        // Test orderBy ascending
        $results = $query->orderBy('views', 'asc')->get();
        expect($results)->toHaveCount(3);

        // Test orderBy descending
        $results = $query->orderBy('views', 'desc')->get();
        expect($results)->toHaveCount(3);

        // Test multiple orderBy
        $chainedQuery = $query->orderBy('views', 'desc')->orderBy('created_at', 'asc');
        expect($chainedQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);

        // Test latest/oldest shortcuts
        $latestQuery = $query->latest('created_at');
        expect($latestQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);

        $oldestQuery = $query->oldest('created_at');
        expect($oldestQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);
    }

    #[Test]
    public function it_handles_limiting_and_pagination()
    {
        $testData = [];
        for ($i = 1; $i <= 20; $i++) {
            $testData[] = TestDataFactory::createPost([
                'id' => "post-{$i}",
                'title' => "Post {$i}",
                'views' => $i * 10
            ]);
        }

        $this->mockFirestoreQuery('posts', $testData);

        $query = FirestoreDB::collection('posts');

        // Test limit
        $results = $query->limit(5)->get();
        expect($results)->toHaveCount(5);

        // Test take (alias for limit)
        $results = $query->take(3)->get();
        expect($results)->toHaveCount(3);

        // Test offset
        $offsetQuery = $query->offset(10)->limit(5);
        expect($offsetQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);

        // Test skip (alias for offset)
        $skipQuery = $query->skip(5)->take(10);
        expect($skipQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);
    }

    #[Test]
    public function it_handles_result_retrieval_methods()
    {
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'First Post']),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Second Post']),
            TestDataFactory::createPost(['id' => '3', 'title' => 'Third Post'])
        ];

        $this->mockFirestoreQuery('posts', $testData);

        $query = FirestoreDB::collection('posts');

        // Test get()
        $results = $query->get();
        expect($results)->toBeInstanceOf(Collection::class);
        expect($results)->toHaveCount(3);

        // Test first()
        $first = $query->first();
        expect($first)->not->toBeNull();
        expect($first['title'])->toBe('First Post');

        // Test find()
        $found = $query->find('2');
        expect($found)->not->toBeNull();
        expect($found['id'])->toBe('2');

        // Test count()
        $count = $query->count();
        expect($count)->toBe(3);

        // Test exists()
        $exists = $query->exists();
        expect($exists)->toBeTrue();

        // Test doesntExist()
        $doesntExist = $query->doesntExist();
        expect($doesntExist)->toBeFalse();
    }

    #[Test]
    public function it_handles_aggregation_methods()
    {
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'views' => 100, 'likes' => 10]),
            TestDataFactory::createPost(['id' => '2', 'views' => 200, 'likes' => 25]),
            TestDataFactory::createPost(['id' => '3', 'views' => 150, 'likes' => 15])
        ];

        $this->mockFirestoreQuery('posts', $testData);

        $query = FirestoreDB::collection('posts');

        // Test sum
        $totalViews = $query->sum('views');
        expect($totalViews)->toBe(450);

        // Test avg
        $avgViews = $query->avg('views');
        expect($avgViews)->toBe(150);

        // Test max
        $maxViews = $query->max('views');
        expect($maxViews)->toBe(200);

        // Test min
        $minViews = $query->min('views');
        expect($minViews)->toBe(100);
    }

    #[Test]
    public function it_handles_conditional_queries()
    {
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'published' => true]),
            TestDataFactory::createPost(['id' => '2', 'published' => false])
        ];

        $this->mockFirestoreQuery('posts', $testData);

        $query = FirestoreDB::collection('posts');

        // Test when() method
        $conditionalQuery = $query->when(true, function ($q) {
            return $q->where('published', true);
        });

        expect($conditionalQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);

        // Test unless() method
        $unlessQuery = $query->unless(false, function ($q) {
            return $q->where('published', true);
        });

        expect($unlessQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);
    }

    #[Test]
    public function it_handles_query_scopes()
    {
        $testData = [
            TestDataFactory::createPost(['id' => '1', 'published' => true, 'featured' => true]),
            TestDataFactory::createPost(['id' => '2', 'published' => true, 'featured' => false]),
            TestDataFactory::createPost(['id' => '3', 'published' => false, 'featured' => true])
        ];

        $this->mockFirestoreQuery('posts', $testData);

        // Test with model query builder that supports scopes
        $query = TestPost::query();

        // Test published scope (if defined in TestPost)
        if (method_exists(TestPost::class, 'scopePublished')) {
            $publishedQuery = $query->published();
            expect($publishedQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        }

        // Test method chaining with scopes
        $chainedQuery = $query->where('featured', true);
        expect($chainedQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
    }

    #[Test]
    public function it_handles_raw_firestore_operations()
    {
        $query = FirestoreDB::collection('posts');

        // Test getting raw collection reference
        $collection = $query->getCollection();
        expect($collection)->toBeString();
        expect($collection)->toBe('posts');

        // Test getting query instance
        $firestoreQuery = $query->getQuery();
        expect($firestoreQuery)->not->toBeNull();
    }

    #[Test]
    public function it_handles_query_debugging()
    {
        $query = FirestoreDB::collection('posts')
            ->where('published', true)
            ->orderBy('created_at', 'desc')
            ->limit(10);

        // Test query state inspection
        $wheres = $query->getWheres();
        expect($wheres)->toBeArray();

        $orders = $query->getOrders();
        expect($orders)->toBeArray();

        $limit = $query->getLimit();
        expect($limit)->toBe(10);
    }

    #[Test]
    public function it_handles_query_cloning()
    {
        $originalQuery = FirestoreDB::collection('posts')
            ->where('published', true)
            ->orderBy('created_at', 'desc');

        $clonedQuery = clone $originalQuery;
        $clonedQuery->where('featured', true);

        // Original query should remain unchanged
        expect($originalQuery)->not->toBe($clonedQuery);
        
        // Both should be valid query builders
        expect($originalQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);
        expect($clonedQuery)->toBeInstanceOf(FirestoreQueryBuilder::class);
    }

    #[Test]
    public function it_handles_empty_result_sets()
    {
        // Mock empty result set using a unique collection name
        $this->mockFirestoreQuery('empty_posts', []);

        $query = FirestoreDB::collection('empty_posts');

        // Test empty results
        $results = $query->get();
        expect($results)->toBeInstanceOf(Collection::class);
        expect($results)->toHaveCount(0);
        expect($results->isEmpty())->toBeTrue();

        // Test first() with empty results
        $first = $query->first();
        expect($first)->toBeNull();

        // Test count with empty results
        $count = $query->count();
        expect($count)->toBe(0);

        // Test exists with empty results
        $exists = $query->exists();
        expect($exists)->toBeFalse();
    }

    #[Test]
    public function it_handles_query_performance_optimization()
    {
        // Create large dataset for performance testing
        $largeDataset = [];
        for ($i = 1; $i <= 100; $i++) {
            $largeDataset[] = TestDataFactory::createPost([
                'id' => "post-{$i}",
                'title' => "Post {$i}",
                'published' => $i % 2 === 0,
                'views' => $i * 10
            ]);
        }

        $this->mockFirestoreQuery('posts', $largeDataset);

        $query = FirestoreDB::collection('posts');

        // Test query execution time
        $startTime = microtime(true);
        $results = $query->where('published', true)->limit(10)->get();
        $executionTime = microtime(true) - $startTime;

        expect($results)->toHaveCount(10);
        expect($executionTime)->toBeLessThan(0.1); // Should be fast with mocking
    }
}
