<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Optimization\MemoryManager;
use JTD\FirebaseModels\Optimization\PerformanceTuner;
use JTD\FirebaseModels\Optimization\QueryOptimizer;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('optimization')]
#[Group('performance')]
class PerformanceOptimizationTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset all optimization systems
        QueryOptimizer::clearStats();
        MemoryManager::reset();
        PerformanceTuner::reset();

        // Configure for testing
        QueryOptimizer::configure(['log_slow_queries' => false]);
        MemoryManager::configure(['log_memory_warnings' => false]);
        PerformanceTuner::configure(['performance_monitoring' => false]);
    }

    protected function tearDown(): void
    {
        QueryOptimizer::clearStats();
        MemoryManager::reset();
        PerformanceTuner::reset();
        parent::tearDown();
    }

    #[Test]
    public function it_runs_query_optimizer_tests()
    {
        // Test basic query optimization
        $wheres = [['field' => 'published', 'operator' => '=', 'value' => true]];
        $optimized = QueryOptimizer::optimizeQuery('posts', $wheres, [], null);

        expect($optimized['collection'])->toBe('posts');
        expect($optimized['wheres'])->toEqual($wheres);
        expect($optimized)->toHaveKey('optimizations_applied');

        // Test query statistics tracking
        $queryHash = md5(serialize(['collection' => 'posts', 'wheres' => $wheres, 'orders' => [], 'limit' => null]));
        QueryOptimizer::trackQueryExecution($queryHash, 50.0, 10, false);

        $stats = QueryOptimizer::getQueryStats();
        expect($stats['total_queries_tracked'])->toBe(1);
        expect($stats['total_executions'])->toBe(1);
    }

    #[Test]
    public function it_runs_memory_manager_tests()
    {
        // Test memory monitoring
        $result = MemoryManager::monitor('test_operation', function () {
            return 'test_result';
        });

        expect($result)->toBe('test_result');

        $stats = MemoryManager::getMemoryStats();
        expect($stats['current_usage_mb'])->toBeGreaterThan(0);
        expect($stats['operations_tracked'])->toBe(1);

        // Test memory allocation tracking
        $allocationId = MemoryManager::allocate('test_context', 1024 * 1024); // 1MB
        expect($allocationId)->toBeString();

        $allocationStats = MemoryManager::getAllocationStats();
        expect($allocationStats['active_allocations'])->toBe(1);
        expect($allocationStats['active_memory_mb'])->toBe(1.0);

        $deallocated = MemoryManager::deallocate($allocationId);
        expect($deallocated)->toBeTrue();
    }

    #[Test]
    public function it_processes_collections_in_memory_efficient_chunks()
    {
        $largeCollection = collect(range(1, 100));
        $processedChunks = [];

        $results = MemoryManager::processInChunks($largeCollection, 10, function ($chunk) use (&$processedChunks) {
            $processedChunks[] = $chunk->count();

            return $chunk->sum();
        });

        expect($results)->toHaveCount(10); // 100 items / 10 per chunk
        expect($processedChunks)->toHaveCount(10);
        expect(array_sum($processedChunks))->toBe(100);
        expect(array_sum($results))->toBe(5050); // Sum of 1-100
    }

    #[Test]
    public function it_manages_resource_pools()
    {
        // Create a resource pool
        MemoryManager::createResourcePool('test_pool', 3, function () {
            return new \stdClass();
        });

        // Get resources from pool
        $resource1 = MemoryManager::getResource('test_pool');
        $resource2 = MemoryManager::getResource('test_pool');
        $resource3 = MemoryManager::getResource('test_pool');

        expect($resource1)->toBeInstanceOf(\stdClass::class);
        expect($resource2)->toBeInstanceOf(\stdClass::class);
        expect($resource3)->toBeInstanceOf(\stdClass::class);

        // Pool should be at capacity
        $this->expectException(\RuntimeException::class);
        MemoryManager::getResource('test_pool');
    }

    #[Test]
    public function it_returns_resources_to_pool()
    {
        MemoryManager::createResourcePool('return_test_pool', 2, function () {
            return new \stdClass();
        });

        $resource = MemoryManager::getResource('return_test_pool');
        $returned = MemoryManager::returnResource('return_test_pool', $resource);

        expect($returned)->toBeTrue();

        // Should be able to get resource again
        $newResource = MemoryManager::getResource('return_test_pool');
        expect($newResource)->toBeInstanceOf(\stdClass::class);
    }

    #[Test]
    public function it_triggers_memory_cleanup()
    {
        $initialMemory = memory_get_usage(true);

        // Create some allocations
        $allocations = [];
        for ($i = 0; $i < 10; $i++) {
            $allocations[] = MemoryManager::allocate("test_context_{$i}", 1024);
        }

        $beforeCleanup = MemoryManager::getAllocationStats();
        expect($beforeCleanup['active_allocations'])->toBe(10);

        // Trigger cleanup
        MemoryManager::triggerMemoryCleanup();

        // Verify cleanup occurred
        $afterCleanup = memory_get_usage(true);
        expect($afterCleanup)->toBeLessThanOrEqual($initialMemory + 1024 * 1024); // Allow some overhead
    }

    #[Test]
    public function it_runs_performance_analysis()
    {
        // Initialize performance tuner
        PerformanceTuner::initialize();

        // Create some performance data
        QueryOptimizer::trackQueryExecution('test_query', 100.0, 10, false);
        MemoryManager::monitor('test_memory_op', function () {
            return 'result';
        });

        // Run performance analysis
        $analysis = PerformanceTuner::analyzePerformance();

        expect($analysis)->toHaveKey('overall_score');
        expect($analysis)->toHaveKey('components');
        expect($analysis)->toHaveKey('recommendations');
        expect($analysis['components'])->toHaveKey('queries');
        expect($analysis['components'])->toHaveKey('memory');
        expect($analysis['components'])->toHaveKey('cache');
        expect($analysis['overall_score'])->toBeGreaterThanOrEqual(0);
        expect($analysis['overall_score'])->toBeLessThanOrEqual(100);
    }

    #[Test]
    public function it_applies_auto_optimizations()
    {
        PerformanceTuner::configure(['enable_auto_tuning' => true]);

        // Create suboptimal conditions
        QueryOptimizer::trackQueryExecution('slow_query', 1000.0, 10, false); // Slow query

        $result = PerformanceTuner::autoOptimize();

        expect($result['status'])->toBe('completed');
        expect($result)->toHaveKey('optimizations');
        expect($result)->toHaveKey('analysis');
    }

    #[Test]
    public function it_runs_performance_benchmarks()
    {
        $benchmark = PerformanceTuner::benchmark();

        expect($benchmark)->toHaveKey('operations');
        expect($benchmark)->toHaveKey('summary');
        expect($benchmark)->toHaveKey('system_info');
        expect($benchmark['operations'])->toHaveKey('simple_query');
        expect($benchmark['operations'])->toHaveKey('complex_query');
        expect($benchmark['operations'])->toHaveKey('batch_operation');
        expect($benchmark['operations'])->toHaveKey('cache_operation');
        expect($benchmark['operations'])->toHaveKey('memory_operation');

        foreach ($benchmark['operations'] as $operation) {
            expect($operation)->toHaveKey('avg_time_ms');
            expect($operation)->toHaveKey('success_rate');
            expect($operation['success_rate'])->toBeGreaterThan(0);
        }
    }

    #[Test]
    public function it_provides_performance_recommendations()
    {
        // Create conditions that should trigger recommendations
        QueryOptimizer::trackQueryExecution('slow_query_1', 800.0, 10, false);
        QueryOptimizer::trackQueryExecution('slow_query_2', 900.0, 15, false);

        $recommendations = PerformanceTuner::getRecommendations();

        expect($recommendations)->toBeArray();

        if (!empty($recommendations)) {
            $recommendation = $recommendations[0];
            expect($recommendation)->toHaveKey('category');
            expect($recommendation)->toHaveKey('priority');
            expect($recommendation)->toHaveKey('title');
            expect($recommendation)->toHaveKey('description');
            expect($recommendation)->toHaveKey('actions');
        }
    }

    #[Test]
    public function it_tracks_performance_trends()
    {
        // Create historical performance data
        for ($i = 0; $i < 5; $i++) {
            QueryOptimizer::trackQueryExecution("query_{$i}", 100 + ($i * 50), 10, false);
            PerformanceTuner::analyzePerformance();
        }

        $trends = PerformanceTuner::getPerformanceTrends(1); // Last 1 hour

        expect($trends['status'])->toBe('success');
        expect($trends)->toHaveKey('trends');
        expect($trends['trends'])->toHaveKey('overall_score');
        expect($trends['trends'])->toHaveKey('query_performance');
        expect($trends['trends'])->toHaveKey('cache_performance');
        expect($trends['trends'])->toHaveKey('memory_performance');
    }

    #[Test]
    public function it_handles_configuration_changes()
    {
        $originalConfig = [
            'enable_auto_tuning' => false,
            'performance_monitoring' => false,
            'query_optimization' => false,
        ];

        PerformanceTuner::configure($originalConfig);

        // Auto-optimization should be disabled
        $result = PerformanceTuner::autoOptimize();
        expect($result['status'])->toBe('disabled');

        // Re-enable features
        PerformanceTuner::configure([
            'enable_auto_tuning' => true,
            'performance_monitoring' => true,
        ]);

        $result = PerformanceTuner::autoOptimize();
        expect($result['status'])->toBe('completed');
    }

    #[Test]
    public function it_handles_memory_pressure_scenarios()
    {
        // Configure low memory limits for testing
        MemoryManager::configure([
            'memory_limit_mb' => 64,
            'warning_threshold_mb' => 50,
            'cleanup_threshold_mb' => 55,
            'enable_auto_cleanup' => true,
        ]);

        // Create memory pressure
        $allocations = [];
        for ($i = 0; $i < 20; $i++) {
            $allocations[] = MemoryManager::allocate("pressure_test_{$i}", 2 * 1024 * 1024); // 2MB each
        }

        $stats = MemoryManager::getMemoryStats();
        expect($stats['active_allocations'])->toBeGreaterThan(0);

        // Cleanup should have been triggered
        $allocationStats = MemoryManager::getAllocationStats();
        expect($allocationStats['active_memory_mb'])->toBeGreaterThan(0);
    }

    #[Test]
    public function it_optimizes_query_filter_order()
    {
        $unoptimizedWheres = [
            ['field' => 'views', 'operator' => '>', 'value' => 100], // Range filter
            ['field' => 'published', 'operator' => '=', 'value' => true], // Equality filter
            ['field' => 'tags', 'operator' => 'array-contains', 'value' => 'php'], // Array filter
        ];

        $optimized = QueryOptimizer::optimizeQuery('posts', $unoptimizedWheres, [], null);

        // Equality filters should come first
        expect($optimized['wheres'][0]['operator'])->toBe('=');
        expect($optimized['wheres'][1]['operator'])->toBe('>');
        expect($optimized['wheres'][2]['operator'])->toBe('array-contains');
    }

    #[Test]
    public function it_generates_comprehensive_index_suggestions()
    {
        $complexWheres = [
            ['field' => 'published', 'operator' => '=', 'value' => true],
            ['field' => 'views', 'operator' => '>', 'value' => 100],
        ];
        $complexOrders = [['field' => 'created_at', 'direction' => 'desc']];

        QueryOptimizer::optimizeQuery('posts', $complexWheres, $complexOrders, null);

        $suggestions = QueryOptimizer::getIndexSuggestions();
        expect($suggestions)->toHaveCount(1);

        $suggestion = $suggestions[0];
        expect($suggestion['collection'])->toBe('posts');
        expect($suggestion['fields'])->toHaveCount(3); // published, views, created_at
        expect($suggestion)->toHaveKey('firebase_url');
        expect($suggestion)->toHaveKey('priority');
        expect($suggestion)->toHaveKey('estimated_benefit');
    }

    #[Test]
    public function it_provides_detailed_performance_metrics()
    {
        // Create varied performance scenarios
        $scenarios = [
            ['time' => 50, 'results' => 10, 'cached' => false],
            ['time' => 25, 'results' => 5, 'cached' => true],
            ['time' => 200, 'results' => 100, 'cached' => false],
            ['time' => 10, 'results' => 3, 'cached' => true],
        ];

        foreach ($scenarios as $i => $scenario) {
            QueryOptimizer::trackQueryExecution(
                "scenario_query_{$i}",
                $scenario['time'],
                $scenario['results'],
                $scenario['cached']
            );
        }

        $stats = QueryOptimizer::getQueryStats();
        expect($stats['total_queries_tracked'])->toBe(4);
        expect($stats['total_executions'])->toBe(4);
        expect($stats['cache_hit_rate'])->toBe(0.5); // 2 out of 4 cached
        expect($stats['avg_execution_time_ms'])->toBe(71.25); // (50+25+200+10)/4
    }

    #[Test]
    public function it_handles_system_resource_constraints()
    {
        // Test with limited resources
        MemoryManager::configure([
            'memory_limit_mb' => 32,
            'max_tracked_queries' => 10,
        ]);

        QueryOptimizer::configure([
            'max_tracked_queries' => 10,
        ]);

        // Generate more data than limits allow
        for ($i = 0; $i < 20; $i++) {
            QueryOptimizer::trackQueryExecution("constraint_query_{$i}", 50.0, 10, false);
            MemoryManager::allocate("constraint_alloc_{$i}", 1024);
        }

        // Systems should handle constraints gracefully
        $queryStats = QueryOptimizer::getQueryStats();
        $memoryStats = MemoryManager::getMemoryStats();

        expect($queryStats['total_queries_tracked'])->toBeLessThanOrEqual(10);
        expect($memoryStats['active_allocations'])->toBeGreaterThan(0);
    }
}
