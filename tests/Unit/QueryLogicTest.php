<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive Query Logic Test
 *
 * Migrated from:
 * - tests/Unit/ComplexQueryLogicTest.php
 *
 * Uses new UnitTestSuite for optimized performance and memory management.
 */

// Test model for query logic testing
class QueryLogicTestModel extends FirestoreModel
{
    protected ?string $collection = 'query_logic_test';

    protected array $fillable = [
        'name', 'email', 'status', 'active', 'category_id', 'price', 'tags', 'created_at', 'views',
    ];

    protected array $casts = [
        'active' => 'boolean',
        'price' => 'float',
        'views' => 'integer',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

class QueryLogicTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for query logic operations
        $this->setTestRequirements([
            'document_count' => 100,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();
    }

    // ========================================
    // QUERY BUILDER STATE MANAGEMENT
    // ========================================

    #[Test]
    public function it_maintains_correct_state_through_method_chaining()
    {
        // Test basic method chaining
        $query = QueryLogicTestModel::where('active', true)
            ->where('price', '>', 10.00)
            ->orderBy('price', 'desc')
            ->limit(50);

        expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test continued chaining
        $query->where('category_id', '!=', 5)->offset(10);
        expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test complex method chaining
        $complexQuery = QueryLogicTestModel::select(['name', 'price'])
            ->where('active', true)
            ->whereIn('category_id', [1, 2, 3])
            ->whereBetween('price', [10.00, 100.00])
            ->whereNotNull('description')
            ->orderBy('price', 'desc')
            ->orderBy('name', 'asc')
            ->limit(50)
            ->offset(10)
            ->distinct();

        expect($complexQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Performance test for method chaining
        $executionTime = $this->benchmark(function () {
            return QueryLogicTestModel::where('active', true)
                ->whereIn('category_id', range(1, 10))
                ->whereBetween('price', [1.00, 100.00])
                ->orderBy('price', 'asc')
                ->limit(25);
        });

        expect($executionTime)->toBeLessThan(0.01); // Query building should be fast
    }

    #[Test]
    public function it_can_clone_queries_independently_and_optimize_memory()
    {
        $baseQuery = QueryLogicTestModel::where('active', true);

        // Test query cloning
        $query1 = clone $baseQuery;
        $query1->where('price', '>', 50.00);

        $query2 = clone $baseQuery;
        $query2->where('price', '<', 25.00);

        expect($query1)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        expect($query2)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        expect($query1)->not->toBe($query2);

        // Test memory efficiency with query cloning
        $this->enableMemoryMonitoring();

        $baseQuery = QueryLogicTestModel::where('active', true)
            ->whereIn('category_id', [1, 2, 3, 4, 5]);

        $initialMemory = memory_get_usage(true);

        // Create multiple cloned queries
        $queries = [];
        for ($i = 0; $i < 10; $i++) {
            $clonedQuery = clone $baseQuery;
            $clonedQuery->where('price', '>', $i * 10);
            $queries[] = $clonedQuery;
        }

        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 1MB for 10 queries)
        expect($memoryIncrease)->toBeLessThan(1024 * 1024);
    }

    // ========================================
    // QUERY VALIDATION AND WHERE CONDITIONS
    // ========================================

    #[Test]
    public function it_validates_query_parameters_and_handles_complex_where_conditions()
    {
        // Test whereBetween validation
        expect(function () {
            QueryLogicTestModel::whereBetween('price', [10.00]);
        })->toThrow(\InvalidArgumentException::class, 'whereBetween requires exactly 2 values');

        expect(function () {
            QueryLogicTestModel::whereBetween('price', [10.00, 20.00, 30.00]);
        })->toThrow(\InvalidArgumentException::class, 'whereBetween requires exactly 2 values');

        // Test empty whereIn arrays
        $query = QueryLogicTestModel::whereIn('category_id', []);
        expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test complex where conditions
        $complexQuery = QueryLogicTestModel::where('active', true)
            ->where('price', '>', 10.00)
            ->where('category_id', '!=', 5)
            ->whereIn('status', ['published', 'featured'])
            ->whereNotNull('description')
            ->orWhere('featured', true);

        expect($complexQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test null value handling
        $nullQuery = QueryLogicTestModel::where('description', null)
            ->where('category_id', '!=', null)
            ->whereNull('deleted_at')
            ->whereNotNull('created_at');

        expect($nullQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
    }

    #[Test]
    public function it_handles_different_data_types_and_special_values()
    {
        // Test boolean values
        $booleanQuery = QueryLogicTestModel::where('active', true)
            ->where('featured', false)
            ->where('published', '=', true)
            ->where('archived', '!=', false);

        expect($booleanQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test numeric values and type casting
        $numericQuery = QueryLogicTestModel::where('price', 10)
            ->where('views', 100.5)
            ->where('rating', '4.5')
            ->where('count', '>', '50');

        expect($numericQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test string values with special characters
        $stringQuery = QueryLogicTestModel::where('name', 'Product "Special" Name')
            ->where('description', "Text with 'quotes' and symbols")
            ->where('code', 'ABC-123_XYZ')
            ->where('email', 'user@domain.com');

        expect($stringQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test date and time values
        $dateQuery = QueryLogicTestModel::whereDate('created_at', '2023-01-01')
            ->whereTime('created_at', '12:30:45')
            ->whereYear('created_at', 2023)
            ->whereMonth('created_at', 6)
            ->whereDay('created_at', 15);

        expect($dateQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
    }

    // ========================================
    // CURSOR PAGINATION AND COLUMN SELECTION
    // ========================================

    #[Test]
    public function it_handles_cursor_pagination_and_column_selection()
    {
        // Test cursor pagination with startAfter
        $startAfterQuery = QueryLogicTestModel::orderBy('created_at')
            ->startAfter('document-id-123')
            ->limit(10);

        expect($startAfterQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test cursor pagination with startBefore
        $startBeforeQuery = QueryLogicTestModel::orderBy('created_at')
            ->startBefore('document-id-456')
            ->limit(10);

        expect($startBeforeQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test complex cursor pagination
        $complexCursorQuery = QueryLogicTestModel::where('active', true)
            ->whereIn('category_id', [1, 2, 3])
            ->orderBy('created_at', 'desc')
            ->orderBy('price', 'asc')
            ->startAfter('last-document-id')
            ->limit(25);

        expect($complexCursorQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test column selection
        $selectQuery = QueryLogicTestModel::select(['name', 'price']);
        expect($selectQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test distinct
        $distinctQuery = QueryLogicTestModel::distinct()->select(['category_id']);
        expect($distinctQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test combined column selection with complex conditions
        $combinedQuery = QueryLogicTestModel::select(['name', 'price', 'category_id'])
            ->where('active', true)
            ->whereIn('category_id', [1, 2])
            ->distinct()
            ->orderBy('price');

        expect($combinedQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
    }

    // ========================================
    // QUERY METHOD ALIASES AND PERFORMANCE
    // ========================================

    #[Test]
    public function it_supports_query_method_aliases_and_optimizes_performance()
    {
        // Test method aliases
        $takeQuery = QueryLogicTestModel::take(10);
        expect($takeQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        $skipQuery = QueryLogicTestModel::skip(5);
        expect($skipQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        $orderByDescQuery = QueryLogicTestModel::orderByDesc('price');
        expect($orderByDescQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        $latestQuery = QueryLogicTestModel::latest('created_at');
        $oldestQuery = QueryLogicTestModel::oldest('updated_at');

        expect($latestQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        expect($oldestQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        $randomQuery = QueryLogicTestModel::inRandomOrder();
        expect($randomQuery)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Performance test for complex query building
        $executionTime = $this->benchmark(function () {
            return QueryLogicTestModel::where('active', true)
                ->whereIn('category_id', range(1, 100))
                ->whereBetween('price', [1.00, 1000.00])
                ->whereNotNull('description')
                ->orderBy('price', 'asc')
                ->orderBy('name', 'asc')
                ->limit(50)
                ->offset(100)
                ->distinct();
        });

        expect($executionTime)->toBeLessThan(0.02); // Complex query building should be fast
    }

    #[Test]
    public function it_handles_where_between_with_complex_conditions()
    {
        $query = QueryLogicTestModel::where('active', true)
            ->whereBetween('price', [10.00, 50.00])
            ->whereNotBetween('views', [0, 100])
            ->whereBetween('created_at', ['2023-01-01', '2023-12-31']);

        expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

        // Test performance with multiple whereBetween conditions
        $executionTime = $this->benchmark(function () {
            return QueryLogicTestModel::where('active', true)
                ->whereBetween('price', [1.00, 100.00])
                ->whereBetween('views', [10, 1000])
                ->whereBetween('rating', [1.0, 5.0])
                ->orderBy('price');
        });

        expect($executionTime)->toBeLessThan(0.01);
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Create test models for query testing
        $models = $this->createTestModels(QueryLogicTestModel::class, 5);

        // Verify models were created
        expect($models)->toHaveCount(5);
        foreach ($models as $model) {
            expect($model)->toBeInstanceOf(QueryLogicTestModel::class);
        }

        // Clear test data
        $this->clearTestData();

        // Verify cleanup
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
    }
}
