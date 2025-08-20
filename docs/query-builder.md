# Query Builder Guide

The FirestoreQueryBuilder provides a fluent interface for building and executing Firestore queries. It's designed to be familiar to Laravel developers while respecting Firestore's unique capabilities and constraints.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Where Clauses](#where-clauses)
- [Ordering](#ordering)
- [Limiting & Pagination](#limiting--pagination)
- [Aggregates](#aggregates)
- [Advanced Features](#advanced-features)
- [Firestore Constraints](#firestore-constraints)
- [Performance Tips](#performance-tips)

## Basic Usage

### Getting Started

```php
use App\Models\Post;

// Basic query
$posts = Post::where('published', true)->get();

// Chaining methods
$posts = Post::where('published', true)
    ->where('author_id', 'user123')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

### Query Execution

```php
// Get all results
$posts = Post::where('published', true)->get();

// Get first result
$post = Post::where('title', 'My Post')->first();

// Get first or fail
$post = Post::where('title', 'My Post')->firstOrFail();

// Check existence
$exists = Post::where('published', true)->exists();
$doesntExist = Post::where('published', false)->doesntExist();

// Count results
$count = Post::where('published', true)->count();
```

## Where Clauses

### Basic Where Clauses

```php
// Basic equality
$posts = Post::where('published', true)->get();
$posts = Post::where('author_id', '=', 'user123')->get();

// Comparison operators
$posts = Post::where('views', '>', 100)->get();
$posts = Post::where('rating', '>=', 4.5)->get();
$posts = Post::where('created_at', '<', now())->get();

// Multiple where clauses (AND)
$posts = Post::where('published', true)
    ->where('views', '>', 100)
    ->where('rating', '>=', 4.0)
    ->get();
```

### Or Where Clauses

```php
// Or where
$posts = Post::where('published', true)
    ->orWhere('featured', true)
    ->get();

// Complex or conditions
$posts = Post::where('author_id', 'user123')
    ->orWhere(function ($query) {
        $query->where('published', true)
              ->where('featured', true);
    })
    ->get();
```

### Array-Based Where Clauses

```php
// Where in
$posts = Post::whereIn('category_id', [1, 2, 3])->get();
$posts = Post::whereIn('status', ['published', 'featured'])->get();

// Where not in
$posts = Post::whereNotIn('status', ['draft', 'archived'])->get();

// Or where in
$posts = Post::where('published', true)
    ->orWhereIn('category_id', [1, 2])
    ->get();
```

### Null Checks

```php
// Where null
$posts = Post::whereNull('deleted_at')->get();

// Where not null
$posts = Post::whereNotNull('published_at')->get();

// Or where null
$posts = Post::where('published', true)
    ->orWhereNull('featured_at')
    ->get();
```

### Range Queries

```php
// Where between
$posts = Post::whereBetween('views', [100, 1000])->get();
$posts = Post::whereBetween('created_at', ['2023-01-01', '2023-12-31'])->get();

// Where not between
$posts = Post::whereNotBetween('rating', [1.0, 2.0])->get();
```

### Date Queries

```php
// Where date
$posts = Post::whereDate('created_at', '2023-01-01')->get();
$posts = Post::whereDate('published_at', '>=', '2023-01-01')->get();

// Where year
$posts = Post::whereYear('created_at', 2023)->get();

// Where time
$posts = Post::whereTime('created_at', '>=', '14:00:00')->get();
```

## Ordering

### Basic Ordering

```php
// Order by ascending
$posts = Post::orderBy('created_at', 'asc')->get();
$posts = Post::orderBy('title')->get(); // defaults to 'asc'

// Order by descending
$posts = Post::orderBy('created_at', 'desc')->get();
$posts = Post::orderByDesc('created_at')->get(); // shorthand
```

### Multiple Order Clauses

```php
// Multiple ordering
$posts = Post::orderBy('category_id', 'asc')
    ->orderBy('created_at', 'desc')
    ->get();

// Order by priority
$posts = Post::orderBy('featured', 'desc')
    ->orderBy('views', 'desc')
    ->orderBy('created_at', 'desc')
    ->get();
```

### Convenience Methods

```php
// Latest (order by created_at desc)
$posts = Post::latest()->get();
$posts = Post::latest('updated_at')->get();

// Oldest (order by created_at asc)
$posts = Post::oldest()->get();
$posts = Post::oldest('published_at')->get();

// Random order (post-query shuffle)
$posts = Post::inRandomOrder()->get();
```

## Limiting & Pagination

### Basic Limiting

```php
// Limit results
$posts = Post::limit(10)->get();
$posts = Post::take(5)->get(); // alias for limit

// Skip and take
$posts = Post::skip(10)->take(5)->get();
$posts = Post::offset(10)->limit(5)->get(); // aliases
```

### Cursor Pagination (Firestore-Optimized)

```php
// Start after a specific document
$posts = Post::startAfter('document-id-123')->limit(10)->get();

// Start before a specific document
$posts = Post::startBefore('document-id-456')->limit(10)->get();

// Pagination with cursor
$firstPage = Post::orderBy('created_at')->limit(10)->get();
$lastDoc = $firstPage->last();

$nextPage = Post::orderBy('created_at')
    ->startAfter($lastDoc->id)
    ->limit(10)
    ->get();
```

### Laravel Pagination

```php
// Standard pagination
$posts = Post::where('published', true)->paginate(15);

// Simple pagination (next/previous only)
$posts = Post::where('published', true)->simplePaginate(10);

// Custom pagination
$posts = Post::where('published', true)->paginate(
    $perPage = 15,
    $columns = ['*'],
    $pageName = 'page',
    $page = null
);
```

## Aggregates

### Count

```php
// Count all
$count = Post::count();

// Count with conditions
$publishedCount = Post::where('published', true)->count();

// Count distinct (simulated)
$categoryCount = Post::distinct()->count('category_id');
```

### Min/Max

```php
// Minimum value
$minViews = Post::min('views');
$minRating = Post::where('published', true)->min('rating');

// Maximum value
$maxViews = Post::max('views');
$maxRating = Post::where('published', true)->max('rating');
```

### Sum/Average

```php
// Sum (calculated post-query)
$totalViews = Post::sum('views');
$publishedViews = Post::where('published', true)->sum('views');

// Average (calculated post-query)
$avgViews = Post::avg('views');
$avgRating = Post::where('published', true)->avg('rating');
$avgRating = Post::average('rating'); // alias
```

### Single Values

```php
// Get single column value
$title = Post::where('id', 'post-123')->value('title');

// Get single column value or fail
$title = Post::where('id', 'post-123')->valueOrFail('title');

// Pluck column values
$titles = Post::where('published', true)->pluck('title');
$titlesByCategory = Post::pluck('title', 'category_id');
```

## Advanced Features

### Chunking

```php
// Process results in chunks
Post::where('published', true)->chunk(100, function ($posts) {
    foreach ($posts as $post) {
        // Process each post
        $post->updateSearchIndex();
    }
});

// Chunk by ID (safer for large datasets)
Post::where('published', true)->chunkById(100, function ($posts) {
    foreach ($posts as $post) {
        // Process each post
    }
});

// Each (iterate over all results)
Post::where('published', true)->each(function ($post) {
    // Process each post individually
});
```

### Lazy Collections

```php
// Lazy loading for memory efficiency
$posts = Post::where('published', true)->lazy();

foreach ($posts as $post) {
    // Process one at a time
}

// Lazy by ID
$posts = Post::where('published', true)->lazyById();
```

### Raw Queries

```php
// When you need direct Firestore access
$collection = Post::getQuery()->getCollection();
$documents = $collection->where('published', '==', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->documents();
```

## Firestore Constraints

### Understanding Firestore Limitations

Firestore has specific constraints that affect query building:

1. **Composite Indexes**: Complex queries may require composite indexes
2. **Array Queries**: Limited array-contains operations
3. **OR Queries**: Limited OR operations (use `whereIn` when possible)
4. **Offset Limitations**: Large offsets are inefficient (use cursor pagination)

### Composite Index Requirements

```php
// This query requires a composite index
$posts = Post::where('published', true)
    ->where('category_id', 2)
    ->orderBy('created_at', 'desc')
    ->get();

// Firestore will suggest creating an index like:
// Collection: posts
// Fields: published (Ascending), category_id (Ascending), created_at (Descending)
```

### Array Queries

```php
// Array contains
$posts = Post::where('tags', 'array-contains', 'laravel')->get();

// Array contains any
$posts = Post::where('tags', 'array-contains-any', ['laravel', 'php'])->get();
```

### Optimized Patterns

```php
// Good: Use whereIn instead of multiple OR clauses
$posts = Post::whereIn('category_id', [1, 2, 3])->get();

// Avoid: Multiple OR clauses
$posts = Post::where('category_id', 1)
    ->orWhere('category_id', 2)
    ->orWhere('category_id', 3)
    ->get();

// Good: Use cursor pagination for large datasets
$posts = Post::orderBy('created_at')
    ->startAfter($lastDocumentId)
    ->limit(20)
    ->get();

// Avoid: Large offsets
$posts = Post::offset(10000)->limit(20)->get();
```

## Performance Tips

### 1. Use Specific Queries

```php
// Good: Specific conditions
$posts = Post::where('published', true)
    ->where('category_id', 1)
    ->limit(10)
    ->get();

// Avoid: Loading all data then filtering
$posts = Post::all()->where('published', true)->take(10);
```

### 2. Optimize Ordering

```php
// Good: Order by indexed fields
$posts = Post::orderBy('created_at', 'desc')->get();

// Consider: Composite indexes for complex ordering
$posts = Post::where('published', true)
    ->orderBy('featured', 'desc')
    ->orderBy('created_at', 'desc')
    ->get();
```

### 3. Use Cursor Pagination

```php
// Good: Cursor-based pagination
$posts = Post::orderBy('created_at')
    ->startAfter($lastDocumentId)
    ->limit(20)
    ->get();

// Avoid: Offset-based pagination for large datasets
$posts = Post::offset(1000)->limit(20)->get();
```

### 4. Minimize Data Transfer

```php
// Good: Select only needed fields
$posts = Post::select(['title', 'created_at'])
    ->where('published', true)
    ->get();

// Good: Use aggregates when you only need counts
$count = Post::where('published', true)->count();
```

### 5. Batch Operations

```php
// Good: Batch multiple operations
$posts = Post::whereIn('id', $postIds)->get();

// Avoid: Multiple single queries
foreach ($postIds as $id) {
    $post = Post::find($id); // N+1 problem
}
```
