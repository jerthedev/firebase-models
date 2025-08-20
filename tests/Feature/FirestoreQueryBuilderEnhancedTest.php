<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use Illuminate\Support\Carbon;

// Test model for query builder testing
class TestQueryProduct extends FirestoreModel
{
    protected ?string $collection = 'query_products';
    
    protected array $fillable = [
        'name', 'price', 'description', 'active', 'category_id', 'created_at', 'updated_at'
    ];
    
    protected array $casts = [
        'price' => 'float',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

describe('Enhanced FirestoreQueryBuilder', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
        
        // Mock sample data
        $this->mockFirestoreQuery('query_products', [
            ['id' => '1', 'name' => 'Product A', 'price' => 10.00, 'active' => true, 'category_id' => 1, 'created_at' => '2023-01-01 10:00:00'],
            ['id' => '2', 'name' => 'Product B', 'price' => 20.00, 'active' => false, 'category_id' => 1, 'created_at' => '2023-01-02 10:00:00'],
            ['id' => '3', 'name' => 'Product C', 'price' => 30.00, 'active' => true, 'category_id' => 2, 'created_at' => '2023-01-03 10:00:00'],
            ['id' => '4', 'name' => 'Product D', 'price' => 40.00, 'active' => true, 'category_id' => 2, 'created_at' => '2023-01-04 10:00:00'],
            ['id' => '5', 'name' => 'Product E', 'price' => 50.00, 'active' => false, 'category_id' => 3, 'created_at' => '2023-01-05 10:00:00'],
        ]);
    });

    describe('Enhanced Where Clauses', function () {
        it('can use whereIn clause', function () {
            $products = TestQueryProduct::whereIn('category_id', [1, 2])->get();
            
            expect($products)->toHaveCount(4);
            expect($products->pluck('category_id')->unique()->sort()->values()->toArray())->toBe([1, 2]);
        });

        it('can use whereNotIn clause', function () {
            $products = TestQueryProduct::whereNotIn('category_id', [1, 2])->get();
            
            expect($products)->toHaveCount(1);
            expect($products->first()->category_id)->toBe(3);
        });

        it('can use whereNull clause', function () {
            // Mock data with null values
            $this->mockFirestoreQuery('query_products', [
                ['id' => '1', 'name' => 'Product A', 'price' => 10.00, 'description' => null],
                ['id' => '2', 'name' => 'Product B', 'price' => 20.00, 'description' => 'Has description'],
            ]);
            
            $products = TestQueryProduct::whereNull('description')->get();
            
            expect($products)->toHaveCount(1);
            expect($products->first()->name)->toBe('Product A');
        });

        it('can use whereNotNull clause', function () {
            // Mock data with null values
            $this->mockFirestoreQuery('query_products', [
                ['id' => '1', 'name' => 'Product A', 'price' => 10.00, 'description' => null],
                ['id' => '2', 'name' => 'Product B', 'price' => 20.00, 'description' => 'Has description'],
            ]);
            
            $products = TestQueryProduct::whereNotNull('description')->get();
            
            expect($products)->toHaveCount(1);
            expect($products->first()->name)->toBe('Product B');
        });

        it('can use whereBetween clause', function () {
            $products = TestQueryProduct::whereBetween('price', [20.00, 40.00])->get();
            
            expect($products)->toHaveCount(3);
            expect($products->pluck('name')->toArray())->toBe(['Product B', 'Product C', 'Product D']);
        });

        it('can use whereNotBetween clause', function () {
            $products = TestQueryProduct::whereNotBetween('price', [20.00, 40.00])->get();
            
            expect($products)->toHaveCount(2);
            expect($products->pluck('name')->toArray())->toBe(['Product A', 'Product E']);
        });

        it('can use whereDate clause', function () {
            $products = TestQueryProduct::whereDate('created_at', '2023-01-01')->get();
            
            expect($products)->toHaveCount(1);
            expect($products->first()->name)->toBe('Product A');
        });

        it('can use whereYear clause', function () {
            $products = TestQueryProduct::whereYear('created_at', 2023)->get();
            
            expect($products)->toHaveCount(5);
        });

        it('can chain multiple where clauses', function () {
            $products = TestQueryProduct::where('active', true)
                ->where('price', '>', 20.00)
                ->get();
            
            expect($products)->toHaveCount(2);
            expect($products->pluck('name')->toArray())->toBe(['Product C', 'Product D']);
        });

        it('can use orWhere clauses', function () {
            $products = TestQueryProduct::where('price', '<', 15.00)
                ->orWhere('price', '>', 45.00)
                ->get();
            
            expect($products)->toHaveCount(2);
            expect($products->pluck('name')->toArray())->toBe(['Product A', 'Product E']);
        });
    });

    describe('Enhanced OrderBy', function () {
        it('can order by ascending', function () {
            $products = TestQueryProduct::orderBy('price', 'asc')->get();
            
            expect($products->first()->name)->toBe('Product A');
            expect($products->last()->name)->toBe('Product E');
        });

        it('can order by descending', function () {
            $products = TestQueryProduct::orderBy('price', 'desc')->get();
            
            expect($products->first()->name)->toBe('Product E');
            expect($products->last()->name)->toBe('Product A');
        });

        it('can use orderByDesc shorthand', function () {
            $products = TestQueryProduct::orderByDesc('price')->get();
            
            expect($products->first()->name)->toBe('Product E');
            expect($products->last()->name)->toBe('Product A');
        });

        it('can use latest method', function () {
            $products = TestQueryProduct::latest('created_at')->get();
            
            expect($products->first()->name)->toBe('Product E');
            expect($products->last()->name)->toBe('Product A');
        });

        it('can use oldest method', function () {
            $products = TestQueryProduct::oldest('created_at')->get();
            
            expect($products->first()->name)->toBe('Product A');
            expect($products->last()->name)->toBe('Product E');
        });

        it('can use latest with default created_at', function () {
            $products = TestQueryProduct::latest()->get();
            
            expect($products->first()->name)->toBe('Product E');
        });

        it('can chain multiple orderBy clauses', function () {
            $products = TestQueryProduct::orderBy('category_id', 'asc')
                ->orderBy('price', 'desc')
                ->get();
            
            // Should order by category first, then by price desc within each category
            expect($products->first()->category_id)->toBe(1);
            expect($products->first()->price)->toBe(20.00); // Product B (higher price in category 1)
        });
    });

    describe('Limit and Pagination', function () {
        it('can limit results', function () {
            $products = TestQueryProduct::limit(3)->get();
            
            expect($products)->toHaveCount(3);
        });

        it('can use take alias for limit', function () {
            $products = TestQueryProduct::take(2)->get();
            
            expect($products)->toHaveCount(2);
        });

        it('can use offset to skip results', function () {
            $products = TestQueryProduct::offset(2)->limit(2)->get();
            
            expect($products)->toHaveCount(2);
            // Should skip first 2 and take next 2
            expect($products->first()->name)->toBe('Product C');
            expect($products->last()->name)->toBe('Product D');
        });

        it('can use skip alias for offset', function () {
            $products = TestQueryProduct::skip(1)->take(2)->get();
            
            expect($products)->toHaveCount(2);
            expect($products->first()->name)->toBe('Product B');
        });
    });

    describe('Query Convenience Methods', function () {
        it('can get single column value', function () {
            $name = TestQueryProduct::where('id', '1')->value('name');
            
            expect($name)->toBe('Product A');
        });

        it('can pluck column values', function () {
            $names = TestQueryProduct::where('active', true)->pluck('name');
            
            expect($names->toArray())->toBe(['Product A', 'Product C', 'Product D']);
        });

        it('can pluck with key column', function () {
            $namesByCategory = TestQueryProduct::pluck('name', 'category_id');
            
            expect($namesByCategory)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });

        it('can check if records exist', function () {
            expect(TestQueryProduct::where('active', true)->exists())->toBeTrue();
            expect(TestQueryProduct::where('price', '>', 100)->exists())->toBeFalse();
        });

        it('can check if records dont exist', function () {
            expect(TestQueryProduct::where('price', '>', 100)->doesntExist())->toBeTrue();
            expect(TestQueryProduct::where('active', true)->doesntExist())->toBeFalse();
        });

        it('can get min value', function () {
            $minPrice = TestQueryProduct::min('price');
            
            expect($minPrice)->toBe(10.00);
        });

        it('can get max value', function () {
            $maxPrice = TestQueryProduct::max('price');
            
            expect($maxPrice)->toBe(50.00);
        });

        it('can get sum of values', function () {
            $totalPrice = TestQueryProduct::sum('price');
            
            expect($totalPrice)->toBe(150.00);
        });

        it('can get average of values', function () {
            $avgPrice = TestQueryProduct::avg('price');
            
            expect($avgPrice)->toBe(30.00);
        });

        it('can use average alias', function () {
            $avgPrice = TestQueryProduct::average('price');
            
            expect($avgPrice)->toBe(30.00);
        });
    });

    describe('Complex Queries', function () {
        it('can combine multiple query methods', function () {
            $products = TestQueryProduct::where('active', true)
                ->whereIn('category_id', [1, 2])
                ->whereBetween('price', [15.00, 35.00])
                ->orderBy('price', 'desc')
                ->limit(2)
                ->get();
            
            expect($products)->toHaveCount(2);
            expect($products->first()->name)->toBe('Product C');
            expect($products->last()->name)->toBe('Product B');
        });

        it('can use distinct to get unique results', function () {
            $categories = TestQueryProduct::distinct()->pluck('category_id');
            
            expect($categories->unique()->count())->toBe($categories->count());
        });
    });

    describe('Random Ordering', function () {
        it('can use inRandomOrder', function () {
            $products1 = TestQueryProduct::inRandomOrder()->get();
            $products2 = TestQueryProduct::inRandomOrder()->get();
            
            // Both should have same count
            expect($products1)->toHaveCount(5);
            expect($products2)->toHaveCount(5);
            
            // Results might be in different order (though not guaranteed in tests)
            expect($products1)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        });
    });
});
