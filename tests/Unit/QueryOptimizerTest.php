<?php

namespace JTD\FirebaseModels\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use JTD\FirebaseModels\Optimization\QueryOptimizer;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('optimization')]
#[Group('query-optimizer')]
class QueryOptimizerTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        parent::setUp();

        QueryOptimizer::clearStats();
        QueryOptimizer::setEnabled(true);
        QueryOptimizer::configure([
            'track_queries' => true,
            'suggest_indexes' => true,
            'log_slow_queries' => false, // Disable for tests
            'slow_query_threshold_ms' => 100,
        ]);
    }

    protected function tearDown(): void
    {
        QueryOptimizer::clearStats();
        parent::tearDown();
    }

    #[Test]
    public function it_optimizes_simple_queries()
    {
        $wheres = [['field' => 'published', 'operator' => '=', 'value' => true]];
        $orders = [];
        $limit = null;

        $optimized = QueryOptimizer::optimizeQuery('posts', $wheres, $orders, $limit);

        expect($optimized['collection'])->toBe('posts');
        expect($optimized['wheres'])->toEqual($wheres);
        expect($optimized['orders'])->toEqual($orders);
        expect($optimized)->toHaveKey('optimizations_applied');
    }

    #[Test]
    public function it_adds_automatic_limits_for_large_collections()
    {
        $wheres = [['field' => 'active', 'operator' => '=', 'value' => true]];
        $orders = [];
        $limit = null; // No limit specified

        $optimized = QueryOptimizer::optimizeQuery('users', $wheres, $orders, $limit);

        expect($optimized['limit'])->toBe(100); // Auto-added limit
        expect($optimized['optimizations_applied'])->toBeGreaterThan(0);
    }

    #[Test]
    public function it_optimizes_filter_order()
    {
        $wheres = [
            ['field' => 'views', 'operator' => '>', 'value' => 100], // Range filter
            ['field' => 'published', 'operator' => '=', 'value' => true], // Equality filter
            ['field' => 'tags', 'operator' => 'array-contains', 'value' => 'php'], // Array filter
        ];

        $optimized = QueryOptimizer::optimizeQuery('posts', $wheres, [], null);

        // Equality filters should come first
        expect($optimized['wheres'][0]['operator'])->toBe('=');
        expect($optimized['wheres'][1]['operator'])->toBe('>');
        expect($optimized['wheres'][2]['operator'])->toBe('array-contains');
    }

    #[Test]
    public function it_tracks_query_execution_statistics()
    {
        $wheres = [['field' => 'published', 'operator' => '=', 'value' => true]];
        $queryHash = md5(serialize(['collection' => 'posts', 'wheres' => $wheres, 'orders' => [], 'limit' => null]));

        // Simulate multiple query executions
        QueryOptimizer::trackQueryExecution($queryHash, 50.0, 10, false);
        QueryOptimizer::trackQueryExecution($queryHash, 75.0, 15, true);
        QueryOptimizer::trackQueryExecution($queryHash, 60.0, 12, false);

        $stats = QueryOptimizer::getQueryStats();

        expect($stats['total_queries_tracked'])->toBe(1);
        expect($stats['total_executions'])->toBe(3);
        expect($stats['avg_execution_time_ms'])->toBeGreaterThan(0);
        expect($stats['cache_hit_rate'])->toBeGreaterThan(0);
    }

    #[Test]
    public function it_generates_index_suggestions()
    {
        $wheres = [
            ['field' => 'published', 'operator' => '=', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 100],
        ];
        $orders = [['field' => 'created_at', 'direction' => 'desc']];

        QueryOptimizer::optimizeQuery('posts', $wheres, $orders, null);

        $suggestions = QueryOptimizer::getIndexSuggestions();

        expect($suggestions)->toHaveCount(1);
        expect($suggestions[0])->toHaveKey('collection');
        expect($suggestions[0])->toHaveKey('fields');
        expect($suggestions[0])->toHaveKey('firebase_url');
        expect($suggestions[0]['collection'])->toBe('posts');
        expect($suggestions[0]['fields'])->toHaveCount(3); // published, views, created_at
    }

    #[Test]
    public function it_detects_inefficient_query_patterns()
    {
        // Query with many filters
        $manyFilters = [
            ['field' => 'published', 'operator' => '=', 'value' => true],
            ['field' => 'featured', 'operator' => '=', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 100],
            ['field' => 'likes', 'operator' => '>', 'value' => 50],
        ];

        $optimized = QueryOptimizer::optimizeQuery('posts', $manyFilters, [], null);
        expect($optimized['optimizations_applied'])->toBeGreaterThan(0);

        // Query without limit on large collection
        $optimized = QueryOptimizer::optimizeQuery('users', [], [], null);
        expect($optimized['limit'])->toBe(100); // Auto-added
    }

    #[Test]
    public function it_provides_optimization_recommendations()
    {
        // Create some slow query statistics
        $slowQueryHash = 'slow_query_hash';
        QueryOptimizer::trackQueryExecution($slowQueryHash, 1500.0, 100, false); // Slow query
        QueryOptimizer::trackQueryExecution($slowQueryHash, 1200.0, 95, false);

        // Create query with low cache hit rate
        $lowCacheHash = 'low_cache_hash';
        for ($i = 0; $i < 15; $i++) {
            QueryOptimizer::trackQueryExecution($lowCacheHash, 50.0, 10, $i < 3); // Only 3 cache hits out of 15
        }

        $recommendations = QueryOptimizer::getOptimizationRecommendations();

        expect($recommendations)->toHaveCount(2);

        $slowQueryRec = collect($recommendations)->firstWhere('type', 'slow_query');
        expect($slowQueryRec)->not->toBeNull();
        expect($slowQueryRec['avg_time_ms'])->toBeGreaterThan(100);

        $lowCacheRec = collect($recommendations)->firstWhere('type', 'low_cache_hit_rate');
        expect($lowCacheRec)->not->toBeNull();
        expect($lowCacheRec['cache_hit_rate'])->toBeLessThan(0.5);
    }

    #[Test]
    public function it_handles_array_query_optimization()
    {
        $wheres = [
            ['field' => 'tags', 'operator' => 'array-contains', 'value' => 'php'],
            ['field' => 'published', 'operator' => '=', 'value' => true],
        ];

        $optimized = QueryOptimizer::optimizeQuery('posts', $wheres, [], null);

        // Should suggest index and provide optimization advice
        $suggestions = QueryOptimizer::getIndexSuggestions();
        expect($suggestions)->toHaveCount(1);
        expect($optimized['optimizations_applied'])->toBeGreaterThan(0);
    }

    #[Test]
    public function it_calculates_index_priorities()
    {
        // High priority: many filters + ordering
        $highPriorityWheres = [
            ['field' => 'published', 'operator' => '=', 'value' => true],
            ['field' => 'featured', 'operator' => '=', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 100],
        ];
        $highPriorityOrders = [['field' => 'created_at', 'direction' => 'desc']];

        QueryOptimizer::optimizeQuery('posts', $highPriorityWheres, $highPriorityOrders, null);

        // Low priority: single filter
        $lowPriorityWheres = [['field' => 'published', 'operator' => '=', 'value' => true]];
        QueryOptimizer::optimizeQuery('posts', $lowPriorityWheres, [], null);

        $suggestions = QueryOptimizer::getIndexSuggestions();
        expect($suggestions)->toHaveCount(2);

        $highPrioritySuggestion = collect($suggestions)->firstWhere('priority', 'high');
        $lowPrioritySuggestion = collect($suggestions)->firstWhere('priority', 'low');

        expect($highPrioritySuggestion)->not->toBeNull();
        expect($lowPrioritySuggestion)->not->toBeNull();
    }

    #[Test]
    public function it_handles_query_performance_analysis()
    {
        // Simulate various query patterns
        $queries = [
            ['hash' => 'fast_query', 'times' => [10, 15, 12, 8, 20], 'results' => [5, 7, 6, 4, 8]],
            ['hash' => 'slow_query', 'times' => [150, 200, 180, 160, 190], 'results' => [100, 120, 110, 105, 115]],
            ['hash' => 'cached_query', 'times' => [5, 3, 4, 2, 6], 'results' => [10, 10, 10, 10, 10]],
        ];

        foreach ($queries as $query) {
            foreach ($query['times'] as $i => $time) {
                $fromCache = $query['hash'] === 'cached_query' && $i > 0;
                QueryOptimizer::trackQueryExecution($query['hash'], $time, $query['results'][$i], $fromCache);
            }
        }

        $stats = QueryOptimizer::getQueryStats();

        expect($stats['total_queries_tracked'])->toBe(3);
        expect($stats['total_executions'])->toBe(15);
        expect($stats['slow_queries'])->toHaveCount(1);
        expect($stats['most_frequent_queries'])->toHaveCount(3);
        expect($stats['cache_hit_rate'])->toBeGreaterThan(0);
    }

    #[Test]
    public function it_handles_configuration_changes()
    {
        $originalConfig = [
            'track_queries' => false,
            'suggest_indexes' => false,
            'slow_query_threshold_ms' => 2000,
        ];

        QueryOptimizer::configure($originalConfig);

        // With tracking disabled, no stats should be recorded
        QueryOptimizer::trackQueryExecution('test_hash', 100.0, 10, false);
        $stats = QueryOptimizer::getQueryStats();
        expect($stats['total_executions'])->toBe(0);

        // Re-enable tracking
        QueryOptimizer::configure(['track_queries' => true]);
        QueryOptimizer::trackQueryExecution('test_hash', 100.0, 10, false);
        $stats = QueryOptimizer::getQueryStats();
        expect($stats['total_executions'])->toBe(1);
    }

    #[Test]
    public function it_can_be_disabled_and_enabled()
    {
        QueryOptimizer::setEnabled(false);

        $wheres = [['field' => 'published', 'operator' => '=', 'value' => true]];
        $optimized = QueryOptimizer::optimizeQuery('posts', $wheres, [], null);

        // When disabled, should return query unchanged
        expect($optimized['wheres'])->toEqual($wheres);
        expect($optimized)->not->toHaveKey('optimizations_applied');

        QueryOptimizer::setEnabled(true);

        $optimized = QueryOptimizer::optimizeQuery('posts', $wheres, [], null);
        expect($optimized)->toHaveKey('optimizations_applied');
    }

    #[Test]
    public function it_generates_firebase_console_urls()
    {
        $wheres = [
            ['field' => 'published', 'operator' => '=', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 100],
        ];
        $orders = [['field' => 'created_at', 'direction' => 'desc']];

        QueryOptimizer::optimizeQuery('posts', $wheres, $orders, null);
        $suggestions = QueryOptimizer::getIndexSuggestions();

        expect($suggestions)->toHaveCount(1);
        expect($suggestions[0]['firebase_url'])->toContain('console.firebase.google.com');
        expect($suggestions[0]['firebase_url'])->toContain('create_composite');
        expect($suggestions[0]['firebase_url'])->toContain('posts');
    }

    #[Test]
    public function it_handles_memory_management()
    {
        // Configure low limit for testing
        QueryOptimizer::configure(['max_tracked_queries' => 5]);

        // Add more queries than the limit
        for ($i = 0; $i < 10; $i++) {
            $hash = "query_hash_{$i}";
            QueryOptimizer::trackQueryExecution($hash, 50.0, 10, false);
        }

        $stats = QueryOptimizer::getQueryStats();
        expect($stats['total_queries_tracked'])->toBeLessThanOrEqual(5);
    }

    #[Test]
    public function it_provides_detailed_performance_metrics()
    {
        // Create varied performance data
        $performanceData = [
            ['time' => 10, 'results' => 5],
            ['time' => 50, 'results' => 20],
            ['time' => 25, 'results' => 12],
            ['time' => 100, 'results' => 50],
            ['time' => 15, 'results' => 8],
        ];

        $queryHash = 'performance_test_hash';
        foreach ($performanceData as $data) {
            QueryOptimizer::trackQueryExecution($queryHash, $data['time'], $data['results'], false);
        }

        $stats = QueryOptimizer::getQueryStats();

        expect($stats['avg_execution_time_ms'])->toBe(40.0); // (10+50+25+100+15)/5
        expect($stats['total_executions'])->toBe(5);
        expect($stats['cache_hit_rate'])->toBe(0.0); // No cache hits
    }

    #[Test]
    public function it_handles_complex_query_analysis()
    {
        // Complex query with multiple optimization opportunities
        $complexWheres = [
            ['field' => 'published', 'operator' => '=', 'value' => true],
            ['field' => 'featured', 'operator' => '=', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 1000],
            ['field' => 'tags', 'operator' => 'array-contains', 'value' => 'popular'],
        ];
        $complexOrders = [
            ['field' => 'created_at', 'direction' => 'desc'],
            ['field' => 'likes', 'direction' => 'desc'],
        ];

        $optimized = QueryOptimizer::optimizeQuery('posts', $complexWheres, $complexOrders, null);

        expect($optimized['optimizations_applied'])->toBeGreaterThan(0);
        expect($optimized['limit'])->toBe(100); // Auto-added for large collection

        $suggestions = QueryOptimizer::getIndexSuggestions();
        expect($suggestions)->toHaveCount(1);
        expect($suggestions[0]['priority'])->toBe('high');
        expect($suggestions[0]['estimated_benefit'])->toBe('significant');
    }
}
