# Advanced Querying

This guide covers advanced querying capabilities including complex where clauses, ordering, pagination, and aggregations.

## Complex Where Clauses

### Multiple Conditions

```php
use App\Models\Post;

// AND conditions
$posts = Post::where('published', true)
    ->where('author_id', 'user123')
    ->where('created_at', '>', now()->subDays(30))
    ->get();

// OR conditions using orWhere
$posts = Post::where('published', true)
    ->orWhere('featured', true)
    ->get();
```

### Comparison Operators

```php
// Greater than / Less than
$recentPosts = Post::where('created_at', '>', now()->subWeek())->get();
$oldPosts = Post::where('created_at', '<', now()->subYear())->get();

// Greater/Less than or equal
$posts = Post::where('view_count', '>=', 100)->get();
$posts = Post::where('rating', '<=', 3)->get();

// Not equal
$posts = Post::where('status', '!=', 'draft')->get();
```

### Array Operations

```php
// whereIn - match any value in array
$posts = Post::whereIn('category_id', [1, 2, 3, 4])->get();

// whereNotIn - exclude values in array
$posts = Post::whereNotIn('status', ['draft', 'archived'])->get();

// array-contains (Firestore specific)
$posts = Post::where('tags', 'array-contains', 'laravel')->get();

// array-contains-any (Firestore specific)
$posts = Post::where('tags', 'array-contains-any', ['laravel', 'php', 'firebase'])->get();
```

### Date and Time Queries

```php
// Date comparisons
$todayPosts = Post::whereDate('created_at', today())->get();
$thisWeekPosts = Post::whereBetween('created_at', [
    now()->startOfWeek(),
    now()->endOfWeek()
])->get();

// Custom date ranges
$posts = Post::whereBetween('published_at', [
    '2023-01-01',
    '2023-12-31'
])->get();
```

## Ordering and Sorting

### Basic Ordering

```php
// Order by single field
$posts = Post::orderBy('created_at', 'desc')->get();
$posts = Post::orderBy('title', 'asc')->get();

// Latest and oldest shortcuts
$latestPosts = Post::latest()->get(); // orders by created_at desc
$oldestPosts = Post::oldest()->get(); // orders by created_at asc

// Custom latest/oldest field
$posts = Post::latest('published_at')->get();
```

### Multiple Order Clauses

```php
// Multiple ordering
$posts = Post::orderBy('published', 'desc')
    ->orderBy('created_at', 'desc')
    ->orderBy('title', 'asc')
    ->get();
```

## Pagination

### Basic Pagination

```php
// Simple pagination
$posts = Post::paginate(15); // 15 posts per page

// Custom page size
$posts = Post::where('published', true)->paginate(10);

// Simple pagination (just next/previous)
$posts = Post::simplePaginate(20);
```

### Cursor-based Pagination (Firestore)

```php
// First page
$posts = Post::orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Next page using last document as cursor
$lastPost = $posts->last();
$nextPosts = Post::orderBy('created_at', 'desc')
    ->startAfter($lastPost->created_at)
    ->limit(10)
    ->get();

// Previous page using first document as cursor
$firstPost = $posts->first();
$prevPosts = Post::orderBy('created_at', 'desc')
    ->endBefore($firstPost->created_at)
    ->limit(10)
    ->get();
```

## Aggregations and Counting

### Counting Records

```php
// Count all records
$totalPosts = Post::count();

// Count with conditions
$publishedCount = Post::where('published', true)->count();

// Count distinct values
$authorCount = Post::distinct('author_id')->count();
```

### Existence Checks

```php
// Check if records exist
$hasPublishedPosts = Post::where('published', true)->exists();

// Check if no records exist
$noDrafts = Post::where('status', 'draft')->doesntExist();
```

## Query Scopes

### Local Scopes

```php
// Define in your model
class Post extends FirestoreModel
{
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }
}

// Use scopes
$posts = Post::published()->recent(30)->get();
$userPosts = Post::byAuthor('user123')->published()->get();
```

### Global Scopes

```php
// Define a global scope class
use JTD\FirebaseModels\Firestore\Scope;

class PublishedScope implements Scope
{
    public function apply($builder, $model)
    {
        $builder->where('published', true);
    }
}

// Apply to model
class Post extends FirestoreModel
{
    protected static function booted()
    {
        static::addGlobalScope(new PublishedScope);
    }
}

// Now all queries automatically include published = true
$posts = Post::all(); // Only published posts

// Remove global scope for specific query
$allPosts = Post::withoutGlobalScope(PublishedScope::class)->get();
```

## Raw Firestore Queries

### Using FirestoreDB Facade

```php
use JTD\FirebaseModels\Facades\FirestoreDB;

// Direct Firestore query
$snapshot = FirestoreDB::collection('posts')
    ->where('published', '=', true)
    ->where('view_count', '>', 100)
    ->orderBy('view_count', 'desc')
    ->limit(10)
    ->documents();

$posts = [];
foreach ($snapshot as $document) {
    $posts[] = $document->data();
}
```

### Complex Firestore Operations

```php
// Compound queries
$posts = FirestoreDB::collection('posts')
    ->where('category', '=', 'technology')
    ->where('published', '=', true)
    ->where('created_at', '>', new DateTime('2023-01-01'))
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->documents();

// Array queries
$posts = FirestoreDB::collection('posts')
    ->where('tags', 'array-contains-any', ['laravel', 'php'])
    ->where('published', '=', true)
    ->documents();
```

## Performance Optimization

### Limiting Results

```php
// Always limit large queries
$posts = Post::where('published', true)
    ->limit(100)
    ->get();

// Use take() as alias for limit()
$posts = Post::latest()->take(10)->get();
```

### Selecting Specific Fields

```php
// Select only needed fields (reduces bandwidth)
$posts = Post::select(['id', 'title', 'created_at'])
    ->where('published', true)
    ->get();
```

### Query Optimization Tips

```php
// 1. Use indexes for frequently queried fields
// 2. Limit the number of results
// 3. Use pagination for large datasets
// 4. Cache frequently accessed queries

// Example optimized query
$posts = Post::where('published', true)
    ->where('category_id', $categoryId)
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->remember(300) // Cache for 5 minutes
    ->get();
```

## Error Handling

```php
use JTD\FirebaseModels\Exceptions\FirestoreException;

try {
    $posts = Post::where('invalid_field', '>', 'value')->get();
} catch (FirestoreException $e) {
    // Handle Firestore-specific errors
    logger()->error('Firestore query error: ' . $e->getMessage());
}
```

## Next Steps

- Learn about [Authentication Examples](authentication-examples.md)
- Explore [Caching Examples](caching-examples.md)
- See [Testing Examples](testing-examples.md)
