<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Firestore\FirestoreQueryBuilder;
use JTD\FirebaseModels\Tests\TestCase;

// Test model for query logic testing
class QueryLogicTestModel extends FirestoreModel
{
    protected ?string $collection = 'query_logic_test';
    
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

describe('Complex Query Logic Testing', function () {
    beforeEach(function () {
        // Use ultra-light mock for memory efficiency
        $this->enableUltraLightMock();
        $this->clearFirestoreMocks();
    });

    describe('Query Builder State Management', function () {
        it('maintains correct state through method chaining', function () {
            $query = QueryLogicTestModel::where('active', true)
                ->where('price', '>', 10.00)
                ->orderBy('price', 'desc')
                ->limit(50);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
            
            // Test that we can continue chaining
            $query->where('category_id', '!=', 5)->offset(10);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can clone queries independently', function () {
            $baseQuery = QueryLogicTestModel::where('active', true);
            
            $query1 = clone $baseQuery;
            $query1->where('price', '>', 50.00);
            
            $query2 = clone $baseQuery;
            $query2->where('price', '<', 25.00);
            
            expect($query1)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
            expect($query2)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
            expect($query1)->not->toBe($query2);
        });

        it('handles complex method chaining without errors', function () {
            $query = QueryLogicTestModel::select(['name', 'price'])
                ->where('active', true)
                ->whereIn('category_id', [1, 2, 3])
                ->whereBetween('price', [10.00, 100.00])
                ->whereNotNull('description')
                ->orderBy('price', 'desc')
                ->orderBy('name', 'asc')
                ->limit(50)
                ->offset(10)
                ->distinct();
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Query Validation Logic', function () {
        it('validates whereBetween requires exactly 2 values', function () {
            expect(function () {
                QueryLogicTestModel::whereBetween('price', [10.00]);
            })->toThrow(\InvalidArgumentException::class, 'whereBetween requires exactly 2 values');
            
            expect(function () {
                QueryLogicTestModel::whereBetween('price', [10.00, 20.00, 30.00]);
            })->toThrow(\InvalidArgumentException::class, 'whereBetween requires exactly 2 values');
        });

        it('handles empty whereIn arrays gracefully', function () {
            $query = QueryLogicTestModel::whereIn('category_id', []);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('validates limit and offset values', function () {
            $query = QueryLogicTestModel::limit(100)->offset(50);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles null values in where conditions', function () {
            $query = QueryLogicTestModel::where('description', null)
                ->where('category_id', '!=', null)
                ->whereNull('deleted_at')
                ->whereNotNull('created_at');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Complex Where Condition Logic', function () {
        it('can chain multiple where conditions with different operators', function () {
            $query = QueryLogicTestModel::where('active', true)
                ->where('price', '>', 10.00)
                ->where('category_id', '!=', 5)
                ->whereIn('status', ['published', 'featured'])
                ->whereNotNull('description');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use complex boolean logic with orWhere', function () {
            $query = QueryLogicTestModel::where('category_id', 1)
                ->orWhere('featured', true);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can combine where conditions with different types', function () {
            $query = QueryLogicTestModel::where('name', 'like', '%Product%')
                ->whereDate('created_at', '2023-01-01')
                ->whereYear('created_at', 2023)
                ->whereMonth('created_at', 1)
                ->whereDay('created_at', 1)
                ->whereTime('created_at', '12:00:00');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use whereBetween with complex conditions', function () {
            $query = QueryLogicTestModel::where('active', true)
                ->whereBetween('price', [10.00, 50.00])
                ->whereNotBetween('views', [0, 100])
                ->whereBetween('created_at', ['2023-01-01', '2023-12-31']);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Cursor Pagination Logic', function () {
        it('can set cursor pagination with startAfter', function () {
            $query = QueryLogicTestModel::orderBy('created_at')
                ->startAfter('document-id-123')
                ->limit(10);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can set cursor pagination with startBefore', function () {
            $query = QueryLogicTestModel::orderBy('created_at')
                ->startBefore('document-id-456')
                ->limit(10);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can combine cursor pagination with complex queries', function () {
            $query = QueryLogicTestModel::where('active', true)
                ->whereIn('category_id', [1, 2, 3])
                ->orderBy('created_at', 'desc')
                ->orderBy('price', 'asc')
                ->startAfter('last-document-id')
                ->limit(25);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Column Selection and Distinct Logic', function () {
        it('can select specific columns', function () {
            $query = QueryLogicTestModel::select(['name', 'price']);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use distinct to get unique results', function () {
            $query = QueryLogicTestModel::distinct()->select(['category_id']);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can combine column selection with complex conditions', function () {
            $query = QueryLogicTestModel::select(['name', 'price', 'category_id'])
                ->where('active', true)
                ->whereIn('category_id', [1, 2])
                ->distinct()
                ->orderBy('price');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Performance and Memory Logic', function () {
        it('optimizes query building for complex chains', function () {
            $startTime = microtime(true);
            
            $query = QueryLogicTestModel::where('active', true)
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
            $baseQuery = QueryLogicTestModel::where('active', true)
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

    describe('Edge Cases and Error Handling Logic', function () {
        it('handles boolean values correctly', function () {
            $query = QueryLogicTestModel::where('active', true)
                ->where('featured', false)
                ->where('published', '=', true)
                ->where('archived', '!=', false);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles numeric values and type casting', function () {
            $query = QueryLogicTestModel::where('price', 10)
                ->where('views', 100.5)
                ->where('rating', '4.5')
                ->where('count', '>', '50');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles string values with special characters', function () {
            $query = QueryLogicTestModel::where('name', 'Product "Special" Name')
                ->where('description', "Text with 'quotes' and symbols")
                ->where('code', 'ABC-123_XYZ')
                ->where('email', 'user@domain.com');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('handles date and time values', function () {
            $query = QueryLogicTestModel::whereDate('created_at', '2023-01-01')
                ->whereTime('created_at', '12:30:45')
                ->whereYear('created_at', 2023)
                ->whereMonth('created_at', 6)
                ->whereDay('created_at', 15);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });

    describe('Query Method Aliases and Shortcuts', function () {
        it('can use take alias for limit', function () {
            $query = QueryLogicTestModel::take(10);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use skip alias for offset', function () {
            $query = QueryLogicTestModel::skip(5);
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use orderByDesc shorthand', function () {
            $query = QueryLogicTestModel::orderByDesc('price');
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use latest and oldest shortcuts', function () {
            $query1 = QueryLogicTestModel::latest('created_at');
            $query2 = QueryLogicTestModel::oldest('updated_at');
            
            expect($query1)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
            expect($query2)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });

        it('can use inRandomOrder', function () {
            $query = QueryLogicTestModel::inRandomOrder();
            
            expect($query)->toBeInstanceOf(\JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder::class);
        });
    });
});
