# API Reference

Complete API reference for Firebase Models package.

## Table of Contents

- [FirestoreModel](#firestoremodel)
- [Query Builder](#query-builder)
- [FirestoreDB Facade](#firestoredb-facade)
- [Batch Operations](#batch-operations)
- [Transactions](#transactions)
- [Cache Manager](#cache-manager)
- [Performance Optimization](#performance-optimization)

## FirestoreModel

The base class for all Firestore models, providing Eloquent-like functionality.

### Properties

```php
class FirestoreModel
{
    // Collection name
    protected ?string $collection = null;
    
    // Primary key field
    protected string $primaryKey = 'id';
    
    // Timestamp management
    public bool $timestamps = true;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';
    
    // Mass assignment
    protected array $fillable = [];
    protected array $guarded = ['*'];
    
    // Attribute casting
    protected array $casts = [];
    
    // Hidden attributes
    protected array $hidden = [];
    
    // Appended attributes
    protected array $appends = [];
}
```

### Methods

#### Static Methods

```php
// Create new instance
static Model create(array $attributes = [])

// Find by primary key
static Model|null find(string $id)
static Model findOrFail(string $id)
static Collection findMany(array $ids)

// Query methods
static Builder where(string $field, string $operator, mixed $value)
static Builder whereIn(string $field, array $values)
static Builder whereNotIn(string $field, array $values)
static Builder whereNull(string $field)
static Builder whereNotNull(string $field)

// Ordering and limiting
static Builder orderBy(string $field, string $direction = 'asc')
static Builder limit(int $limit)
static Builder offset(int $offset)

// Aggregation
static int count()
static Collection all()
static Model|null first()
static Model firstOrFail()

// Mass operations
static bool insert(array $values)
static int update(array $values)
static int delete()
static bool destroy(string|array $ids)

// Relationships
static Builder with(string|array $relations)
static Builder has(string $relation)
static Builder whereHas(string $relation, callable $callback = null)

// Scopes
static Builder scope(string $scope, ...$parameters)

// Caching
static Builder cache(int $ttl = null)
static Builder cacheWithTags(array $tags, int $ttl = null)
static void forgetCache(string|array $tags = null)
```

#### Instance Methods

```php
// Persistence
bool save(array $options = [])
bool update(array $attributes = [])
bool delete()
Model fresh()
Model refresh()

// Attributes
mixed getAttribute(string $key)
Model setAttribute(string $key, mixed $value)
array getAttributes()
array getDirty()
array getChanges()
bool isDirty(string|array $attributes = null)

// Relationships
mixed getRelationValue(string $key)
Model setRelation(string $relation, mixed $value)
bool relationLoaded(string $key)

// Serialization
array toArray()
string toJson(int $options = 0)

// Timestamps
Model touch()
Model updateTimestamps()

// Increment/Decrement
int increment(string $column, int $amount = 1)
int decrement(string $column, int $amount = 1)

// Replication
Model replicate(array $except = null)

// Events
void fireModelEvent(string $event, bool $halt = true)
```

### Events

```php
// Model events (in order of execution)
'retrieving', 'retrieved'  // When loading from database
'creating', 'created'      // When creating new model
'updating', 'updated'      // When updating existing model
'saving', 'saved'          // When saving (create or update)
'deleting', 'deleted'      // When deleting model
'restoring', 'restored'    // When restoring soft-deleted model
```

### Example Usage

```php
class Post extends FirestoreModel
{
    protected array $fillable = ['title', 'content', 'published'];
    protected array $casts = ['published' => 'boolean'];
    
    // Relationships
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
    
    // Scopes
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }
    
    // Accessors
    public function getTitleAttribute($value)
    {
        return ucfirst($value);
    }
    
    // Mutators
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = strtolower($value);
    }
}
```

## Query Builder

Fluent interface for building Firestore queries.

### Where Clauses

```php
// Basic where
$query->where('field', 'operator', 'value')
$query->where('published', '=', true)
$query->where('views', '>', 1000)

// Supported operators
'=', '==', '!=', '<', '<=', '>', '>='
'array-contains', 'array-contains-any'
'in', 'not-in'

// Multiple conditions (AND)
$query->where('published', true)
      ->where('views', '>', 100)

// Array operations
$query->where('tags', 'array-contains', 'php')
$query->where('categories', 'array-contains-any', ['tech', 'programming'])
$query->whereIn('status', ['published', 'featured'])
$query->whereNotIn('author_id', ['banned-user-1', 'banned-user-2'])
```

### Ordering

```php
// Single field ordering
$query->orderBy('created_at', 'desc')
$query->orderBy('title', 'asc')

// Multiple field ordering
$query->orderBy('published', 'desc')
      ->orderBy('created_at', 'desc')
```

### Limiting and Pagination

```php
// Limit results
$query->limit(10)
$query->take(5)  // Alias for limit

// Offset (cursor-based pagination recommended for Firestore)
$query->offset(20)
$query->skip(10)  // Alias for offset

// Pagination
$posts = Post::paginate(15);
$posts = Post::simplePaginate(10);
```

### Aggregation

```php
// Count
$count = Post::count()
$count = Post::where('published', true)->count()

// Existence
$exists = Post::where('slug', 'my-post')->exists()
$doesntExist = Post::where('slug', 'my-post')->doesntExist()
```

### Relationships

```php
// Eager loading
$posts = Post::with('author')->get()
$posts = Post::with(['author', 'comments'])->get()
$posts = Post::with('comments.author')->get()

// Relationship existence
$posts = Post::has('comments')->get()
$posts = Post::whereHas('comments', function ($query) {
    $query->where('approved', true);
})->get()

// Relationship counting
$posts = Post::withCount('comments')->get()
```

## FirestoreDB Facade

Direct access to Firestore operations.

### Basic Operations

```php
use JTD\FirebaseModels\Facades\FirestoreDB;

// Collections
$collection = FirestoreDB::collection('posts')
$document = FirestoreDB::collection('posts')->document('post-id')

// Documents
$snapshot = FirestoreDB::collection('posts')->document('post-id')->snapshot()
$data = $snapshot->data()
$exists = $snapshot->exists()

// Create/Update
FirestoreDB::collection('posts')->add(['title' => 'New Post'])
FirestoreDB::collection('posts')->document('post-id')->set(['title' => 'Updated'])
FirestoreDB::collection('posts')->document('post-id')->update(['views' => 100])

// Delete
FirestoreDB::collection('posts')->document('post-id')->delete()
```

### Queries

```php
// Simple queries
$query = FirestoreDB::collection('posts')
                   ->where('published', '=', true)
                   ->orderBy('created_at', 'DESC')
                   ->limit(10)

$documents = $query->documents()

// Collection group queries
$comments = FirestoreDB::collectionGroup('comments')
                      ->where('approved', '=', true)
                      ->documents()
```

### Field Transforms

```php
// Server timestamp
FirestoreDB::collection('posts')->document('post-id')->update([
    'updated_at' => FirestoreDB::fieldValue()->serverTimestamp()
])

// Increment/Decrement
FirestoreDB::collection('posts')->document('post-id')->update([
    'views' => FirestoreDB::fieldValue()->increment(1),
    'likes' => FirestoreDB::fieldValue()->increment(5)
])

// Array operations
FirestoreDB::collection('posts')->document('post-id')->update([
    'tags' => FirestoreDB::fieldValue()->arrayUnion(['new-tag']),
    'old_tags' => FirestoreDB::fieldValue()->arrayRemove(['old-tag'])
])

// Delete field
FirestoreDB::collection('posts')->document('post-id')->update([
    'deprecated_field' => FirestoreDB::fieldValue()->delete()
])
```

## Batch Operations

Efficient bulk operations for multiple documents.

### BatchManager

```php
use JTD\FirebaseModels\Firestore\Batch\BatchManager;

// Bulk insert
$documents = [
    ['title' => 'Post 1', 'content' => 'Content 1'],
    ['title' => 'Post 2', 'content' => 'Content 2'],
];
$result = BatchManager::bulkInsert('posts', $documents);

// Bulk update
$updates = [
    'post-1' => ['views' => 100],
    'post-2' => ['views' => 200],
];
$result = BatchManager::bulkUpdate('posts', $updates);

// Bulk delete
$ids = ['post-1', 'post-2', 'post-3'];
$result = BatchManager::bulkDelete('posts', $ids);

// Mixed operations
$batch = BatchManager::create()
    ->insert('posts', 'new-post', ['title' => 'New Post'])
    ->update('posts', 'existing-post', ['views' => 100])
    ->delete('posts', 'old-post')
    ->commit();
```

### Batch Results

```php
$result = BatchManager::bulkInsert('posts', $documents);

// Result information
$result->isSuccess()           // bool
$result->getOperationCount()   // int
$result->getInsertedCount()    // int
$result->getUpdatedCount()     // int
$result->getDeletedCount()     // int
$result->getDurationMs()       // float
$result->hasErrors()           // bool
$result->getErrors()           // array
```

## Transactions

ACID-compliant transactions for data consistency.

### Basic Transactions

```php
use JTD\FirebaseModels\Facades\FirestoreDB;

$result = FirestoreDB::transaction(function ($transaction) {
    // Read operations
    $userRef = FirestoreDB::collection('users')->document('user-123');
    $userSnapshot = $transaction->snapshot($userRef);
    $userData = $userSnapshot->data();
    
    // Write operations
    $transaction->update($userRef, [
        'balance' => $userData['balance'] + 100
    ]);
    
    return $userData['balance'] + 100;
});
```

### Advanced Transactions

```php
use JTD\FirebaseModels\Firestore\Transactions\TransactionManager;

// Transaction with retry logic
$result = TransactionManager::executeWithResult(function ($transaction) {
    // Your transaction logic
}, [
    'max_attempts' => 5,
    'retry_delay_ms' => 200,
    'exponential_backoff' => true
]);

if ($result->isSuccess()) {
    $data = $result->getData();
    $attempts = $result->getAttempts();
    $duration = $result->getDurationMs();
} else {
    $error = $result->getError();
}

// Transaction builder
$result = TransactionManager::builder()
    ->create('orders', ['user_id' => 'user-123', 'total' => 99.99])
    ->update('users', 'user-123', ['last_order_at' => now()])
    ->withRetry(3, 200)
    ->executeWithResult();
```

## Cache Manager

Intelligent caching for improved performance.

### Basic Caching

```php
use JTD\FirebaseModels\Cache\CacheManager;

$cacheManager = app(CacheManager::class);

// Cache query results
$posts = Post::cache(3600)->where('published', true)->get();

// Cache with tags
$posts = Post::cacheWithTags(['posts', 'published'], 3600)
             ->where('published', true)
             ->get();

// Forget cache
Post::forgetCache(['posts', 'published']);
```

### Cache Configuration

```php
// Configure cache manager
$cacheManager->configure([
    'default_ttl' => 3600,
    'max_cache_size' => 100 * 1024 * 1024, // 100MB
    'enable_predictive_caching' => true,
    'cache_hit_logging' => true,
]);

// Get cache statistics
$stats = $cacheManager->getStatistics();
// Returns: hit_rate, total_requests, cache_size_bytes, etc.
```

## Performance Optimization

Tools for monitoring and optimizing performance.

### Query Optimizer

```php
use JTD\FirebaseModels\Optimization\QueryOptimizer;

// Enable optimization
QueryOptimizer::setEnabled(true);

// Configure optimizer
QueryOptimizer::configure([
    'suggest_indexes' => true,
    'log_slow_queries' => true,
    'slow_query_threshold_ms' => 1000,
]);

// Get optimization statistics
$stats = QueryOptimizer::getQueryStats();
$suggestions = QueryOptimizer::getIndexSuggestions();
$recommendations = QueryOptimizer::getOptimizationRecommendations();
```

### Memory Manager

```php
use JTD\FirebaseModels\Optimization\MemoryManager;

// Monitor memory usage
$result = MemoryManager::monitor('large_operation', function () {
    // Your memory-intensive operation
    return processLargeDataset();
});

// Process in chunks
$results = MemoryManager::processInChunks($largeCollection, 100, function ($chunk) {
    return processChunk($chunk);
});

// Get memory statistics
$stats = MemoryManager::getMemoryStats();
$allocations = MemoryManager::getAllocationStats();
```

### Performance Tuner

```php
use JTD\FirebaseModels\Optimization\PerformanceTuner;

// Initialize performance tuning
PerformanceTuner::initialize();

// Analyze performance
$analysis = PerformanceTuner::analyzePerformance();

// Auto-optimize
$result = PerformanceTuner::autoOptimize();

// Run benchmarks
$benchmark = PerformanceTuner::benchmark();

// Get performance trends
$trends = PerformanceTuner::getPerformanceTrends(24); // Last 24 hours
```

## Error Handling

### Exception Types

```php
// Model exceptions
JTD\FirebaseModels\Exceptions\ModelNotFoundException
JTD\FirebaseModels\Exceptions\MassAssignmentException
JTD\FirebaseModels\Exceptions\InvalidCastException

// Query exceptions
JTD\FirebaseModels\Exceptions\InvalidQueryException
JTD\FirebaseModels\Exceptions\QueryExecutionException

// Transaction exceptions
JTD\FirebaseModels\Exceptions\TransactionException
JTD\FirebaseModels\Exceptions\TransactionRetryException

// Cache exceptions
JTD\FirebaseModels\Exceptions\CacheException
```

### Error Handling Patterns

```php
try {
    $post = Post::findOrFail('non-existent-id');
} catch (ModelNotFoundException $e) {
    // Handle not found
}

try {
    FirestoreDB::transaction(function ($transaction) {
        // Transaction logic
    });
} catch (TransactionException $e) {
    // Handle transaction failure
    Log::error('Transaction failed: ' . $e->getMessage());
}
```

This API reference covers the core functionality. For more detailed examples and advanced usage patterns, see the [full documentation](README.md).
# Firebase Models Quick Reference

Quick reference for common Firebase Models operations, authentication, and caching.

## 🔧 Setup

### Environment Variables
```env
# Firebase
FIREBASE_CREDENTIALS=storage/app/firebase-service-account.json
FIREBASE_PROJECT_ID=your-project-id

# Authentication
FIREBASE_AUTH_GUARD=firebase

# Caching
FIREBASE_CACHE_ENABLED=true
FIREBASE_CACHE_STORE=redis
FIREBASE_CACHE_TTL=300
```

### User Model
```php
use JTD\FirebaseModels\Auth\FirebaseAuthenticatable;

class User extends FirebaseAuthenticatable
{
    protected $fillable = ['name', 'email'];
}
```

## 🔐 Authentication

### API Routes
```php
// Protect API routes
Route::middleware(['auth:firebase'])->group(function () {
    Route::get('/user', [UserController::class, 'show']);
});
```

### Frontend Token
```javascript
// Get Firebase ID token
const idToken = await user.getIdToken();

// API request
fetch('/api/user', {
    headers: { 'Authorization': `Bearer ${idToken}` }
});
```

### Backend Auth Check
```php
// Get authenticated user
$user = Auth::user();
$uid = Auth::id();

// Check authentication
if (Auth::check()) {
    // User is authenticated
}
```

### Custom Claims
```php
// Check user role
$role = Auth::user()->custom_claims['role'] ?? null;

// Middleware for role checking
Route::middleware(['auth:firebase', 'role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

## 💾 Caching

### Basic Caching
```php
// Auto-cached for default TTL (5 minutes)
$users = User::where('active', true)->get();

// Custom TTL (1 hour)
$posts = Post::cache(3600)->get();

// Cache forever
$settings = Setting::cache(null)->get();
```

### Cache Tags
```php
// Tag cache for easy invalidation
$userPosts = Post::where('user_id', $userId)
    ->cacheTags(['user:' . $userId, 'posts'])
    ->get();

// Invalidate tagged cache
Post::flushCacheTags(['user:' . $userId]);
```

### Cache Management
```php
// Check if cached
if (User::where('active', true)->isCached()) {
    // Result is cached
}

// Clear cache
User::where('active', true)->clearCache();

// Flush all cache for model
User::flushCache();

// Disable cache for query
$freshData = User::withoutCache()->get();
```

## 🔍 Query Scopes

### Local Scopes
```php
class User extends FirestoreModel
{
    // Simple scope
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
    
    // Parameterized scope
    public function scopeRole($query, $role)
    {
        return $query->where('role', $role);
    }
}

// Usage
$activeUsers = User::active()->get();
$admins = User::role('admin')->get();
```

### Global Scopes
```php
class User extends FirestoreModel
{
    protected static function booted()
    {
        static::addGlobalScope('active', function ($builder) {
            $builder->where('active', true);
        });
    }
}

// All queries automatically include active scope
$users = User::all(); // Only active users

// Skip global scopes
$allUsers = User::withoutGlobalScopes()->get();
```

## 🎨 Accessors & Mutators

### Legacy Style
```php
class User extends FirestoreModel
{
    // Accessor
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    // Mutator
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }
}
```

### Modern Style (Laravel 9+)
```php
use Illuminate\Database\Eloquent\Casts\Attribute;

class User extends FirestoreModel
{
    // Combined accessor/mutator
    public function name(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ucwords($value),
            set: fn ($value) => strtolower($value),
        );
    }
}
```

## 📊 Model Operations

### Basic CRUD
```php
// Create
$user = User::create(['name' => 'John', 'email' => 'john@example.com']);

// Read
$user = User::find('document-id');
$users = User::where('active', true)->get();

// Update
$user->update(['name' => 'Jane']);

// Delete
$user->delete();
```

### Query Builder
```php
// Where clauses
$users = User::where('age', '>', 18)
    ->where('city', 'New York')
    ->get();

// Ordering
$users = User::orderBy('created_at', 'desc')->get();

// Limiting
$users = User::limit(10)->get();

// Pagination
$users = User::paginate(15);
```

## 🧪 Testing

### Mock Authentication
```php
// In tests
$user = User::factory()->create();

$response = $this->actingAs($user, 'firebase')
    ->getJson('/api/user');

$response->assertOk();
```

### Disable Cache in Tests
```php
// In TestCase
public function setUp(): void
{
    parent::setUp();
    config(['firebase-models.cache.enabled' => false]);
}
```

## 🐛 Common Issues

### Authentication
```php
// Clear config cache
php artisan config:clear

// Check guard configuration
dd(config('auth.guards.firebase'));

// Debug authentication
Route::get('/debug', function () {
    return [
        'user' => Auth::user(),
        'check' => Auth::check(),
        'guard' => Auth::getDefaultDriver(),
    ];
});
```

### Caching
```php
// Check cache configuration
dd(config('firebase-models.cache'));

// Clear all cache
Cache::flush();

// Debug cache
$debugInfo = User::where('active', true)->getCacheDebugInfo();
```

## 📚 Documentation Links

- **[Complete Auth & Caching Guide](08-authentication.md)** - Comprehensive guide
- **[Authentication Setup](Reference/AUTH_SETUP.md)** - Step-by-step auth configuration
- **[Authentication How-To](Reference/AUTH_HOWTO.md)** - Practical examples
- **[Caching Guide](09-caching.md)** - Complete caching documentation
- **[Installation Guide](02-installation.md)** - Package setup
- **[Configuration Reference](03-configuration.md)** - All configuration options

## 🚀 Performance Tips

1. **Use Redis for caching** in production
2. **Enable cache tags** for efficient invalidation
3. **Warm cache** for frequently accessed data
4. **Use global scopes** to reduce query complexity
5. **Monitor cache hit rates** and optimize accordingly
6. **Use request cache** for repeated queries in single request
7. **Tag cache entries** for organized invalidation strategies
