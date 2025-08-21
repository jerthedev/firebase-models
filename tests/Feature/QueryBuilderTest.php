<?php

namespace JTD\FirebaseModels\Tests\Feature\Restructured;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\FeatureTestSuite;
use JTD\FirebaseModels\Tests\Utilities\TestDataFactory;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

/**
 * Comprehensive Query Builder Feature Test
 * 
 * Consolidated from:
 * - tests/Feature/FirestoreQueryBuilderTest.php
 * - tests/Feature/FirestoreQueryBuilderEnhancedTest.php
 * 
 * Uses new FeatureTestSuite for comprehensive query builder testing scenarios.
 */

// Test model for comprehensive query builder testing
class QueryBuilderTestPost extends FirestoreModel
{
    protected ?string $collection = 'query_builder_test_posts';
    
    protected array $fillable = [
        'title', 'content', 'published', 'author_id', 'views', 'category_id', 'tags', 'price'
    ];

    protected array $casts = [
        'published' => 'boolean',
        'views' => 'integer',
        'price' => 'float',
        'tags' => 'array',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

class QueryBuilderTest extends FeatureTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for comprehensive query builder testing
        $this->setTestRequirements([
            'document_count' => 300,
            'memory_constraint' => false,
            'needs_full_mockery' => true,
        ]);

        parent::setUp();
    }

    // ========================================
    // BASIC QUERY OPERATIONS
    // ========================================

    #[Test]
    public function it_performs_basic_query_operations()
    {
        // Create comprehensive test data
        $testPosts = $this->createFeatureTestData('posts', 10);
        $this->mockComplexQueryScenario('query_builder_test_posts', $testPosts);

        // Test get all records
        $allPosts = QueryBuilderTestPost::all();
        
        expect($allPosts)->toBeInstanceOf(Collection::class);
        expect($allPosts)->toHaveCount(10);
        expect($allPosts->first())->toBeFirestoreModel();

        // Test find by ID
        $specificPost = [
            'id' => 'specific-post-123',
            'title' => 'Specific Post',
            'content' => 'Specific content',
            'published' => true,
            'views' => 150
        ];
        
        $this->mockFirestoreGet('query_builder_test_posts', 'specific-post-123', $specificPost);
        
        $post = QueryBuilderTestPost::find('specific-post-123');
        
        expect($post)->toBeFirestoreModel();
        expect($post->id)->toBe('specific-post-123');
        expect($post->title)->toBe('Specific Post');
        expect($post->published)->toBe(true);

        // Test find returns null for missing record
        $this->mockFirestoreGet('query_builder_test_posts', 'nonexistent', null);
        
        $missingPost = QueryBuilderTestPost::find('nonexistent');
        expect($missingPost)->toBeNull();

        // Test findOrFail
        $foundPost = QueryBuilderTestPost::findOrFail('specific-post-123');
        expect($foundPost)->toBeFirestoreModel();
        
        expect(fn() => QueryBuilderTestPost::findOrFail('nonexistent'))
            ->toThrow(\Illuminate\Database\RecordNotFoundException::class);

        // Test first record
        $firstPost = QueryBuilderTestPost::first();
        expect($firstPost)->toBeFirestoreModel();
        expect($firstPost->title)->toBeString();
    }

    #[Test]
    public function it_handles_counting_and_existence_checks()
    {
        // Create test data
        $testData = $this->createRealisticDataset('query_builder_test_posts', 25);
        $this->mockComplexQueryScenario('query_builder_test_posts', $testData);

        // Test count
        $totalCount = QueryBuilderTestPost::count();
        expect($totalCount)->toBe(25);

        // Test exists
        $hasRecords = QueryBuilderTestPost::exists();
        expect($hasRecords)->toBeTrue();

        // Test empty collection
        $this->mockComplexQueryScenario('query_builder_test_posts', []);
        
        $emptyCount = QueryBuilderTestPost::count();
        expect($emptyCount)->toBe(0);
        
        $hasNoRecords = QueryBuilderTestPost::exists();
        expect($hasNoRecords)->toBeFalse();
    }

    // ========================================
    // WHERE CLAUSE OPERATIONS
    // ========================================

    #[Test]
    public function it_handles_basic_where_clauses()
    {
        // Create test data with specific attributes
        $testPosts = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'First Post', 'published' => true, 'views' => 100]),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Second Post', 'published' => false, 'views' => 50]),
            TestDataFactory::createPost(['id' => '3', 'title' => 'Third Post', 'published' => true, 'views' => 200]),
            TestDataFactory::createPost(['id' => '4', 'title' => 'Fourth Post', 'published' => true, 'views' => 75]),
        ];
        
        $this->mockComplexQueryScenario('query_builder_test_posts', $testPosts);

        // Test basic where clause
        $publishedPosts = QueryBuilderTestPost::where('published', true)->get();
        
        expect($publishedPosts)->toHaveCount(3);
        $this->assertFirestoreQueryExecuted('query_builder_test_posts', [
            ['field' => 'published', 'operator' => '==', 'value' => true]
        ]);

        // Test where with operator
        $popularPosts = QueryBuilderTestPost::where('views', '>', 75)->get();
        expect($popularPosts)->toHaveCount(3);

        // Test multiple where clauses
        $specificPosts = QueryBuilderTestPost::where('published', true)
            ->where('views', '>', 75)
            ->get();
        expect($specificPosts)->toHaveCount(2);

        // Test where with different operators
        $exactViews = QueryBuilderTestPost::where('views', '=', 100)->get();
        expect($exactViews)->toHaveCount(1);
        
        $notPublished = QueryBuilderTestPost::where('published', '!=', true)->get();
        expect($notPublished)->toHaveCount(1);
        
        $highViews = QueryBuilderTestPost::where('views', '>=', 100)->get();
        expect($highViews)->toHaveCount(2);
        
        $lowViews = QueryBuilderTestPost::where('views', '<=', 75)->get();
        expect($lowViews)->toHaveCount(2);
    }

    #[Test]
    public function it_handles_advanced_where_clauses()
    {
        // Create test data for advanced queries
        $advancedTestData = [
            TestDataFactory::createPost(['id' => '1', 'category_id' => 1, 'price' => 10.00, 'tags' => ['tech', 'news']]),
            TestDataFactory::createPost(['id' => '2', 'category_id' => 2, 'price' => 20.00, 'tags' => ['sports', 'news']]),
            TestDataFactory::createPost(['id' => '3', 'category_id' => 1, 'price' => 30.00, 'tags' => ['tech', 'review']]),
            TestDataFactory::createPost(['id' => '4', 'category_id' => 3, 'price' => 40.00, 'tags' => ['lifestyle']]),
            TestDataFactory::createPost(['id' => '5', 'category_id' => 2, 'price' => 50.00, 'tags' => ['sports', 'review']]),
        ];
        
        $this->mockComplexQueryScenario('query_builder_test_posts', $advancedTestData);

        // Test whereIn
        $categoryPosts = QueryBuilderTestPost::whereIn('category_id', [1, 2])->get();
        expect($categoryPosts)->toHaveCount(4);

        // Test whereNotIn
        $excludedPosts = QueryBuilderTestPost::whereNotIn('category_id', [1, 2])->get();
        expect($excludedPosts)->toHaveCount(1);
        expect($excludedPosts->first()->category_id)->toBe(3);

        // Test whereBetween
        $pricedPosts = QueryBuilderTestPost::whereBetween('price', [20.00, 40.00])->get();
        expect($pricedPosts)->toHaveCount(3);

        // Test whereNotBetween
        $extremePrices = QueryBuilderTestPost::whereNotBetween('price', [20.00, 40.00])->get();
        expect($extremePrices)->toHaveCount(2);

        // Test whereNull and whereNotNull
        $nullTestData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'Post A', 'content' => null]),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Post B', 'content' => 'Has content']),
        ];
        
        $this->mockComplexQueryScenario('query_builder_test_posts', $nullTestData);
        
        $nullContent = QueryBuilderTestPost::whereNull('content')->get();
        expect($nullContent)->toHaveCount(1);
        expect($nullContent->first()->title)->toBe('Post A');
        
        $hasContent = QueryBuilderTestPost::whereNotNull('content')->get();
        expect($hasContent)->toHaveCount(1);
        expect($hasContent->first()->title)->toBe('Post B');
    }

    #[Test]
    public function it_handles_date_and_time_queries()
    {
        // Create test data with specific dates
        $dateTestData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'Post A', 'created_at' => '2023-01-01 10:00:00']),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Post B', 'created_at' => '2023-01-02 15:30:00']),
            TestDataFactory::createPost(['id' => '3', 'title' => 'Post C', 'created_at' => '2023-02-01 09:15:00']),
            TestDataFactory::createPost(['id' => '4', 'title' => 'Post D', 'created_at' => '2024-01-01 12:00:00']),
        ];
        
        $this->mockComplexQueryScenario('query_builder_test_posts', $dateTestData);

        // Test whereDate
        $specificDate = QueryBuilderTestPost::whereDate('created_at', '2023-01-01')->get();
        expect($specificDate)->toHaveCount(1);
        expect($specificDate->first()->title)->toBe('Post A');

        // Test whereYear
        $year2023 = QueryBuilderTestPost::whereYear('created_at', 2023)->get();
        expect($year2023)->toHaveCount(3);

        // Test whereMonth
        $january = QueryBuilderTestPost::whereMonth('created_at', 1)->get();
        expect($january)->toHaveCount(3);

        // Test whereDay
        $firstDay = QueryBuilderTestPost::whereDay('created_at', 1)->get();
        expect($firstDay)->toHaveCount(3);

        // Test whereTime
        $morningPosts = QueryBuilderTestPost::whereTime('created_at', '10:00:00')->get();
        expect($morningPosts)->toHaveCount(1);
    }

    // ========================================
    // ORDERING AND LIMITING
    // ========================================

    #[Test]
    public function it_handles_ordering_and_limiting()
    {
        // Create test data for ordering
        $orderTestData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'Alpha Post', 'views' => 300, 'created_at' => '2023-01-03']),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Beta Post', 'views' => 100, 'created_at' => '2023-01-01']),
            TestDataFactory::createPost(['id' => '3', 'title' => 'Gamma Post', 'views' => 200, 'created_at' => '2023-01-02']),
        ];
        
        $this->mockComplexQueryScenario('query_builder_test_posts', $orderTestData);

        // Test orderBy ascending
        $orderedAsc = QueryBuilderTestPost::orderBy('views', 'asc')->get();
        expect($orderedAsc->pluck('views')->toArray())->toBe([100, 200, 300]);

        // Test orderBy descending
        $orderedDesc = QueryBuilderTestPost::orderBy('views', 'desc')->get();
        expect($orderedDesc->pluck('views')->toArray())->toBe([300, 200, 100]);

        // Test orderByDesc alias
        $orderedDescAlias = QueryBuilderTestPost::orderByDesc('views')->get();
        expect($orderedDescAlias->pluck('views')->toArray())->toBe([300, 200, 100]);

        // Test latest and oldest
        $latest = QueryBuilderTestPost::latest('created_at')->get();
        expect($latest->first()->title)->toBe('Alpha Post');
        
        $oldest = QueryBuilderTestPost::oldest('created_at')->get();
        expect($oldest->first()->title)->toBe('Beta Post');

        // Test limit
        $limited = QueryBuilderTestPost::limit(2)->get();
        expect($limited)->toHaveCount(2);

        // Test take alias
        $taken = QueryBuilderTestPost::take(1)->get();
        expect($taken)->toHaveCount(1);

        // Test offset and skip
        $offset = QueryBuilderTestPost::offset(1)->limit(2)->get();
        expect($offset)->toHaveCount(2);
        
        $skipped = QueryBuilderTestPost::skip(1)->take(2)->get();
        expect($skipped)->toHaveCount(2);
    }

    // ========================================
    // AGGREGATION OPERATIONS
    // ========================================

    #[Test]
    public function it_handles_aggregation_operations()
    {
        // Create test data for aggregation
        $aggregationData = [
            TestDataFactory::createPost(['id' => '1', 'views' => 100, 'price' => 10.50]),
            TestDataFactory::createPost(['id' => '2', 'views' => 200, 'price' => 25.75]),
            TestDataFactory::createPost(['id' => '3', 'views' => 150, 'price' => 15.25]),
            TestDataFactory::createPost(['id' => '4', 'views' => 300, 'price' => 30.00]),
        ];
        
        $this->mockComplexQueryScenario('query_builder_test_posts', $aggregationData);

        // Test count
        $count = QueryBuilderTestPost::count();
        expect($count)->toBe(4);

        // Test max
        $maxViews = QueryBuilderTestPost::max('views');
        expect($maxViews)->toBe(300);

        // Test min
        $minViews = QueryBuilderTestPost::min('views');
        expect($minViews)->toBe(100);

        // Test sum
        $totalViews = QueryBuilderTestPost::sum('views');
        expect($totalViews)->toBe(750);

        // Test average
        $avgViews = QueryBuilderTestPost::avg('views');
        expect($avgViews)->toBe(187.5);

        // Test aggregation with conditions
        $highViewsCount = QueryBuilderTestPost::where('views', '>', 150)->count();
        expect($highViewsCount)->toBe(2);
    }

    // ========================================
    // COMPLEX QUERY SCENARIOS
    // ========================================

    #[Test]
    public function it_handles_complex_query_combinations()
    {
        // Create comprehensive test dataset
        $complexData = $this->createRealisticDataset('query_builder_test_posts', 50);
        $this->mockComplexQueryScenario('query_builder_test_posts', $complexData);

        // Test complex query scenario
        $scenarioMetrics = $this->performFeatureScenario('complex_query_combination', function () {
            // Complex query with multiple conditions
            $complexQuery = QueryBuilderTestPost::where('active', true)
                ->whereIn('category', ['electronics', 'books'])
                ->whereBetween('value', [50, 500])
                ->whereNotNull('description')
                ->orderBy('created_at', 'desc')
                ->orderBy('value', 'asc')
                ->limit(10)
                ->offset(5);
            
            $results = $complexQuery->get();
            
            // Test query builder state
            expect($complexQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
            
            return $results;
        });
        
        expect($scenarioMetrics['result'])->toBeInstanceOf(Collection::class);
        $this->assertFeaturePerformance($scenarioMetrics, 1.0, 5 * 1024 * 1024);
    }

    #[Test]
    public function it_handles_pluck_and_value_operations()
    {
        // Create test data for pluck operations
        $pluckData = [
            TestDataFactory::createPost(['id' => '1', 'title' => 'First Title', 'views' => 100]),
            TestDataFactory::createPost(['id' => '2', 'title' => 'Second Title', 'views' => 200]),
            TestDataFactory::createPost(['id' => '3', 'title' => 'Third Title', 'views' => 300]),
        ];
        
        $this->mockComplexQueryScenario('query_builder_test_posts', $pluckData);

        // Test pluck single column
        $titles = QueryBuilderTestPost::pluck('title');
        expect($titles)->toBeInstanceOf(Collection::class);
        expect($titles->toArray())->toBe(['First Title', 'Second Title', 'Third Title']);

        // Test pluck with key
        $titlesWithKeys = QueryBuilderTestPost::pluck('title', 'id');
        expect($titlesWithKeys->toArray())->toBe([
            '1' => 'First Title',
            '2' => 'Second Title',
            '3' => 'Third Title'
        ]);

        // Test value (first value of column)
        $firstTitle = QueryBuilderTestPost::value('title');
        expect($firstTitle)->toBe('First Title');
    }

    #[Test]
    public function it_handles_query_builder_cloning_and_reuse()
    {
        // Create test data
        $testData = $this->createFeatureTestData('posts', 20);
        $this->mockComplexQueryScenario('query_builder_test_posts', $testData);

        // Create base query
        $baseQuery = QueryBuilderTestPost::where('published', true)
            ->orderBy('created_at', 'desc');

        // Clone and extend queries
        $recentPosts = clone $baseQuery;
        $recentPosts->limit(5);

        $popularPosts = clone $baseQuery;
        $popularPosts->where('views', '>', 100);

        // Verify queries are independent
        expect($recentPosts)->not->toBe($popularPosts);
        expect($baseQuery)->not->toBe($recentPosts);
        expect($baseQuery)->not->toBe($popularPosts);

        // Execute queries
        $recentResults = $recentPosts->get();
        $popularResults = $popularPosts->get();
        $baseResults = $baseQuery->get();

        expect($recentResults)->toBeInstanceOf(Collection::class);
        expect($popularResults)->toBeInstanceOf(Collection::class);
        expect($baseResults)->toBeInstanceOf(Collection::class);
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create test models for query testing
        $models = $this->createTestModels(QueryBuilderTestPost::class, 5);
        
        // Verify models were created
        expect($models)->toHaveCount(5);
        foreach ($models as $model) {
            expect($model)->toBeInstanceOf(QueryBuilderTestPost::class);
        }
        
        // Clear test data
        $this->cleanupFeatureData();
        
        // Verify cleanup
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
    }
}
