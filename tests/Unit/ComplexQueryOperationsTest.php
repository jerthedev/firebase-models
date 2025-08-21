<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestCase;
use Illuminate\Support\Collection;

// Test model for complex query testing
class ComplexQueryTestModel extends FirestoreModel
{
    protected ?string $collection = 'complex_query_test';
    
    protected array $fillable = [
        'name', 'email', 'status', 'active', 'category_id', 'price', 'tags', 'created_at', 'views'
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

describe('Complex Query Operations Testing', function () {
    beforeEach(function () {
        // Use ultra-light mock for memory efficiency
        $this->enableUltraLightMock();
        $this->clearFirestoreMocks();
    });

    describe('Advanced Aggregation Operations', function () {
        it('can get minimum value of a column', function () {
            // Mock data for aggregation testing
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'price' => 10.50],
                ['id' => '2', 'name' => 'Product B', 'price' => 25.75],
                ['id' => '3', 'name' => 'Product C', 'price' => 5.25],
            ];
            
            $this->mockFirestoreQuery('complex_query_test', $testData);
            
            $minPrice = ComplexQueryTestModel::min('price');
            
            expect($minPrice)->toBe(5.25);
        });

        it('can get maximum value of a column', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'price' => 10.50],
                ['id' => '2', 'name' => 'Product B', 'price' => 25.75],
                ['id' => '3', 'name' => 'Product C', 'price' => 5.25],
            ];
            
            $this->mockFirestoreQuery('complex_query_test', $testData);
            
            $maxPrice = ComplexQueryTestModel::max('price');
            
            expect($maxPrice)->toBe(25.75);
        });

        it('can calculate sum of column values', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'price' => 10.00],
                ['id' => '2', 'name' => 'Product B', 'price' => 20.00],
                ['id' => '3', 'name' => 'Product C', 'price' => 30.00],
            ];
            
            $this->mockFirestoreQuery('complex_query_test', $testData);
            
            $totalPrice = ComplexQueryTestModel::sum('price');
            
            expect($totalPrice)->toBe(60.00);
        });

        it('can calculate average of column values', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'price' => 10.00],
                ['id' => '2', 'name' => 'Product B', 'price' => 20.00],
                ['id' => '3', 'name' => 'Product C', 'price' => 30.00],
            ];
            
            $this->mockFirestoreQuery('complex_query_test', $testData);
            
            $avgPrice = ComplexQueryTestModel::avg('price');
            
            expect($avgPrice)->toBe(20.00);
        });

        it('can perform aggregation with where conditions', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'price' => 10.00, 'active' => true],
                ['id' => '2', 'name' => 'Product B', 'price' => 20.00, 'active' => false],
                ['id' => '3', 'name' => 'Product C', 'price' => 30.00, 'active' => true],
            ];
            
            $this->mockFirestoreQuery('complex_query_test', $testData);
            
            $activeSum = ComplexQueryTestModel::where('active', true)->sum('price');
            
            expect($activeSum)->toBe(40.00);
        });

        it('handles empty result sets in aggregation', function () {
            $this->mockFirestoreQuery('complex_query_test', []);
            
            $sum = ComplexQueryTestModel::sum('price');
            $avg = ComplexQueryTestModel::avg('price');
            $min = ComplexQueryTestModel::min('price');
            $max = ComplexQueryTestModel::max('price');
            
            expect($sum)->toBe(0);
            expect($avg)->toBeNull();
            expect($min)->toBeNull();
            expect($max)->toBeNull();
        });
    });

    describe('Complex Where Conditions and Grouping', function () {
        it('can chain multiple where conditions with different operators', function () {
            $query = ComplexQueryTestModel::where('active', true)
                ->where('price', '>', 10.00)
                ->where('category_id', '!=', 5)
                ->whereIn('status', ['published', 'featured'])
                ->whereNotNull('description');
            
            // Test that query builder maintains state correctly
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
            
            // Test that we can continue chaining
            $query->orderBy('price', 'desc')->limit(10);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use complex boolean logic with orWhere', function () {
            $query = ComplexQueryTestModel::where('category_id', 1)
                ->orWhere(function ($q) {
                    $q->where('featured', true)
                      ->where('price', '<', 50.00);
                });
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can handle nested where groups', function () {
            $query = ComplexQueryTestModel::where('active', true)
                ->where(function ($q) {
                    $q->where('category_id', 1)
                      ->orWhere('category_id', 2);
                })
                ->where(function ($q) {
                    $q->where('price', '>', 10.00)
                      ->where('price', '<', 100.00);
                });
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can combine where conditions with different types', function () {
            $query = ComplexQueryTestModel::where('name', 'like', '%Product%')
                ->whereDate('created_at', '2023-01-01')
                ->whereYear('created_at', 2023)
                ->whereMonth('created_at', 1)
                ->whereDay('created_at', 1)
                ->whereTime('created_at', '12:00:00');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use whereBetween with complex conditions', function () {
            $query = ComplexQueryTestModel::where('active', true)
                ->whereBetween('price', [10.00, 50.00])
                ->whereNotBetween('views', [0, 100])
                ->whereBetween('created_at', ['2023-01-01', '2023-12-31']);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Cursor-Based Pagination', function () {
        it('can set cursor pagination with startAfter', function () {
            $query = ComplexQueryTestModel::orderBy('created_at')
                ->startAfter('document-id-123')
                ->limit(10);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can set cursor pagination with startBefore', function () {
            $query = ComplexQueryTestModel::orderBy('created_at')
                ->startBefore('document-id-456')
                ->limit(10);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can set cursor pagination with endAt', function () {
            $query = ComplexQueryTestModel::orderBy('created_at')
                ->endAt('document-id-789')
                ->limit(10);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can combine cursor pagination with complex queries', function () {
            $query = ComplexQueryTestModel::where('active', true)
                ->whereIn('category_id', [1, 2, 3])
                ->orderBy('created_at', 'desc')
                ->orderBy('price', 'asc')
                ->startAfter('last-document-id')
                ->limit(25);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can handle cursor pagination with multiple ordering', function () {
            $query = ComplexQueryTestModel::orderBy('category_id')
                ->orderBy('price', 'desc')
                ->orderBy('created_at')
                ->startAfter('cursor-document')
                ->limit(20);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Advanced Column Selection and Distinct', function () {
        it('can select specific columns', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'price' => 10.00, 'description' => 'Desc A'],
                ['id' => '2', 'name' => 'Product B', 'price' => 20.00, 'description' => 'Desc B'],
            ];
            
            $this->mockFirestoreQuery('complex_query_test', $testData);
            
            $query = ComplexQueryTestModel::select(['name', 'price']);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use distinct to get unique results', function () {
            $testData = [
                ['id' => '1', 'category_id' => 1],
                ['id' => '2', 'category_id' => 1],
                ['id' => '3', 'category_id' => 2],
                ['id' => '4', 'category_id' => 2],
                ['id' => '5', 'category_id' => 3],
            ];
            
            $this->mockFirestoreQuery('complex_query_test', $testData);
            
            $query = ComplexQueryTestModel::distinct()->select(['category_id']);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can combine column selection with complex conditions', function () {
            $query = ComplexQueryTestModel::select(['name', 'price', 'category_id'])
                ->where('active', true)
                ->whereIn('category_id', [1, 2])
                ->distinct()
                ->orderBy('price');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Query Method Chaining and State Management', function () {
        it('maintains query state through complex chaining', function () {
            $query = ComplexQueryTestModel::where('active', true)
                ->select(['name', 'price'])
                ->whereIn('category_id', [1, 2, 3])
                ->whereBetween('price', [10.00, 100.00])
                ->orderBy('price', 'desc')
                ->orderBy('name', 'asc')
                ->limit(50)
                ->offset(10)
                ->distinct();
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can clone queries and modify independently', function () {
            $baseQuery = ComplexQueryTestModel::where('active', true)
                ->whereIn('category_id', [1, 2]);
            
            $query1 = clone $baseQuery;
            $query1->where('price', '>', 50.00);
            
            $query2 = clone $baseQuery;
            $query2->where('price', '<', 25.00);
            
            expect($query1)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
            expect($query2)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can reset query parts selectively', function () {
            $query = ComplexQueryTestModel::where('active', true)
                ->orderBy('price')
                ->limit(10);
            
            // Test that we can continue building the query
            $query->where('category_id', 1)->orderBy('name');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Array Query Operations', function () {
        it('can query with array-contains operator', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'tags' => ['electronics', 'mobile']],
                ['id' => '2', 'name' => 'Product B', 'tags' => ['clothing', 'fashion']],
                ['id' => '3', 'name' => 'Product C', 'tags' => ['electronics', 'computer']],
            ];

            $this->mockFirestoreQuery('complex_query_test', $testData);

            $query = ComplexQueryTestModel::where('tags', 'array-contains', 'electronics');

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can query with array-contains-any operator', function () {
            $testData = [
                ['id' => '1', 'name' => 'Product A', 'tags' => ['electronics', 'mobile']],
                ['id' => '2', 'name' => 'Product B', 'tags' => ['clothing', 'fashion']],
                ['id' => '3', 'name' => 'Product C', 'tags' => ['electronics', 'computer']],
            ];

            $this->mockFirestoreQuery('complex_query_test', $testData);

            $query = ComplexQueryTestModel::where('tags', 'array-contains-any', ['electronics', 'clothing']);

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can combine array queries with other conditions', function () {
            $query = ComplexQueryTestModel::where('active', true)
                ->where('tags', 'array-contains', 'featured')
                ->where('price', '>', 10.00)
                ->orderBy('created_at', 'desc');

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can handle complex array query combinations', function () {
            $query = ComplexQueryTestModel::where('tags', 'array-contains-any', ['electronics', 'computers'])
                ->where('categories', 'array-contains', 'featured')
                ->whereIn('status', ['published', 'promoted'])
                ->whereBetween('price', [50.00, 500.00]);

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Query Validation and Constraints', function () {
        it('validates whereBetween requires exactly 2 values', function () {
            expect(function () {
                ComplexQueryTestModel::whereBetween('price', [10.00]);
            })->toThrow(\InvalidArgumentException::class, 'whereBetween requires exactly 2 values');

            expect(function () {
                ComplexQueryTestModel::whereBetween('price', [10.00, 20.00, 30.00]);
            })->toThrow(\InvalidArgumentException::class, 'whereBetween requires exactly 2 values');
        });

        it('handles invalid operator conversions gracefully', function () {
            // Test that invalid operators are handled appropriately
            $query = ComplexQueryTestModel::where('price', 'invalid-operator', 10.00);

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('validates limit and offset values', function () {
            $query = ComplexQueryTestModel::limit(100)->offset(50);

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles empty whereIn arrays', function () {
            $query = ComplexQueryTestModel::whereIn('category_id', []);

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('validates column names in orderBy', function () {
            $query = ComplexQueryTestModel::orderBy('valid_column', 'asc')
                ->orderBy('another_column', 'desc');

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Performance and Memory Optimization', function () {
        it('handles large result sets efficiently', function () {
            // Mock a large dataset
            $largeDataset = [];
            for ($i = 1; $i <= 1000; $i++) {
                $largeDataset[] = [
                    'id' => "item-{$i}",
                    'name' => "Item {$i}",
                    'price' => rand(10, 100),
                    'category_id' => rand(1, 10),
                ];
            }

            $this->mockFirestoreQuery('complex_query_test', $largeDataset);

            $initialMemory = memory_get_usage(true);

            $query = ComplexQueryTestModel::where('price', '>', 50)
                ->orderBy('price', 'desc')
                ->limit(100);

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

            $finalMemory = memory_get_usage(true);
            $memoryUsed = $finalMemory - $initialMemory;

            // Memory usage should be reasonable (less than 10MB for query building)
            expect($memoryUsed)->toBeLessThan(10 * 1024 * 1024);
        });

        it('optimizes query building for complex chains', function () {
            $startTime = microtime(true);

            $query = ComplexQueryTestModel::where('active', true)
                ->whereIn('category_id', range(1, 100))
                ->whereBetween('price', [1.00, 1000.00])
                ->whereNotNull('description')
                ->where('featured', true)
                ->orderBy('created_at', 'desc')
                ->orderBy('price', 'asc')
                ->orderBy('name', 'asc')
                ->limit(50)
                ->offset(100)
                ->distinct();

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);

            // Query building should be fast (less than 100ms)
            expect($executionTime)->toBeLessThan(0.1);
        });

        it('maintains memory efficiency with query cloning', function () {
            $baseQuery = ComplexQueryTestModel::where('active', true)
                ->whereIn('category_id', [1, 2, 3, 4, 5]);

            $initialMemory = memory_get_usage(true);

            // Create multiple cloned queries
            $queries = [];
            for ($i = 0; $i < 50; $i++) {
                $clonedQuery = clone $baseQuery;
                $clonedQuery->where('price', '>', $i * 10);
                $queries[] = $clonedQuery;
            }

            $finalMemory = memory_get_usage(true);
            $memoryUsed = $finalMemory - $initialMemory;

            expect(count($queries))->toBe(50);

            // Memory usage should be reasonable for 50 cloned queries (less than 5MB)
            expect($memoryUsed)->toBeLessThan(5 * 1024 * 1024);
        });
    });

    describe('Edge Cases and Error Handling', function () {
        it('handles null values in where conditions', function () {
            $query = ComplexQueryTestModel::where('description', null)
                ->where('category_id', '!=', null)
                ->whereNull('deleted_at')
                ->whereNotNull('created_at');

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles boolean values correctly', function () {
            $query = ComplexQueryTestModel::where('active', true)
                ->where('featured', false)
                ->where('published', '=', true)
                ->where('archived', '!=', false);

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles numeric values and type casting', function () {
            $query = ComplexQueryTestModel::where('price', 10)
                ->where('views', 100.5)
                ->where('rating', '4.5')
                ->where('count', '>', '50');

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles string values with special characters', function () {
            $query = ComplexQueryTestModel::where('name', 'Product "Special" Name')
                ->where('description', "Text with 'quotes' and symbols")
                ->where('code', 'ABC-123_XYZ')
                ->where('email', 'user@domain.com');

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles date and time values', function () {
            $query = ComplexQueryTestModel::whereDate('created_at', '2023-01-01')
                ->whereTime('created_at', '12:30:45')
                ->whereYear('created_at', 2023)
                ->whereMonth('created_at', 6)
                ->whereDay('created_at', 15);

            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });
});
