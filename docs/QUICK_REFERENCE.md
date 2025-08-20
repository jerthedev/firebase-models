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

- **[Complete Auth & Caching Guide](AUTH_AND_CACHING_GUIDE.md)** - Comprehensive guide
- **[Authentication Setup](AUTH_SETUP.md)** - Step-by-step auth configuration
- **[Authentication How-To](AUTH_HOWTO.md)** - Practical examples
- **[Caching Guide](CACHING.md)** - Complete caching documentation
- **[Installation Guide](INSTALLATION.md)** - Package setup
- **[Configuration Reference](CONFIGURATION.md)** - All configuration options

## 🚀 Performance Tips

1. **Use Redis for caching** in production
2. **Enable cache tags** for efficient invalidation
3. **Warm cache** for frequently accessed data
4. **Use global scopes** to reduce query complexity
5. **Monitor cache hit rates** and optimize accordingly
6. **Use request cache** for repeated queries in single request
7. **Tag cache entries** for organized invalidation strategies
