# Firebase Models: Authentication & Caching Complete Guide

This comprehensive guide covers the authentication and caching systems in the JTD Firebase Models package, providing everything you need to build secure, high-performance Laravel applications with Firebase.

## 📚 Documentation Overview

### 🔐 Authentication Documentation

| Document | Purpose | Audience |
|----------|---------|----------|
| **[AUTH_SETUP.md](AUTH_SETUP.md)** | Complete setup and configuration | Developers setting up Firebase auth |
| **[AUTH_HOWTO.md](AUTH_HOWTO.md)** | Practical examples and scenarios | Developers implementing auth features |
| **[AUTH.md](AUTH.md)** | Technical reference and API docs | Advanced developers and troubleshooting |

### ⚡ Caching Documentation

| Document | Purpose | Audience |
|----------|---------|----------|
| **[09-caching.md](09-caching.md)** | Complete caching guide and optimization | All developers using the package |

## 🚀 Quick Start Guide

### 1. Authentication Setup (5 minutes)

```bash
# 1. Publish configuration
php artisan vendor:publish --tag=firebase-models-config

# 2. Set environment variables
echo "FIREBASE_CREDENTIALS=storage/app/firebase-service-account.json" >> .env
echo "FIREBASE_PROJECT_ID=your-project-id" >> .env
echo "FIREBASE_AUTH_GUARD=firebase" >> .env
```

```php
// 3. Update config/auth.php
'defaults' => ['guard' => 'firebase'],
'guards' => [
    'firebase' => [
        'driver' => 'firebase',
        'provider' => 'firebase_users',
    ],
],
'providers' => [
    'firebase_users' => [
        'driver' => 'firebase',
        'model' => App\Models\User::class,
    ],
],
```

```php
// 4. Update User model
class User extends FirebaseAuthenticatable
{
    use Notifiable;
    // ... your model code
}
```

### 2. Caching Setup (2 minutes)

```bash
# 1. Configure Redis (recommended)
echo "FIREBASE_CACHE_ENABLED=true" >> .env
echo "FIREBASE_CACHE_STORE=redis" >> .env
echo "FIREBASE_CACHE_TTL=300" >> .env
```

```php
// 2. Queries are automatically cached
$users = User::where('active', true)->get(); // Cached for 5 minutes
$posts = Post::cache(3600)->get(); // Cached for 1 hour
```

## 🎯 Common Use Cases

### API Authentication

**Frontend (JavaScript)**
```javascript
const idToken = await user.getIdToken();
fetch('/api/data', {
    headers: { 'Authorization': `Bearer ${idToken}` }
});
```

**Backend (Laravel)**
```php
Route::middleware(['auth:firebase'])->group(function () {
    Route::get('/api/data', [DataController::class, 'index']);
});
```

### Web Application Authentication

**Frontend (Blade + JS)**
```javascript
const userCredential = await signInWithEmailAndPassword(auth, email, password);
const idToken = await userCredential.user.getIdToken();

fetch('/auth/firebase-login', {
    method: 'POST',
    body: JSON.stringify({ firebase_token: idToken })
});
```

**Backend (Laravel)**
```php
public function firebaseLogin(Request $request)
{
    if (Auth::guard('firebase')->attempt(['token' => $request->firebase_token])) {
        return redirect('/dashboard');
    }
    return back()->withErrors(['auth' => 'Authentication failed']);
}
```

### High-Performance Queries with Caching

```php
// Automatic caching with tags for easy invalidation
$userPosts = Post::where('user_id', $userId)
    ->cacheTags(['user:' . $userId, 'posts'])
    ->cache(3600)
    ->get();

// Invalidate when user posts change
Post::flushCacheTags(['user:' . $userId]);
```

## 🔧 Advanced Configurations

### Multi-Environment Setup

```php
// config/firebase-models.php
'cache' => [
    'enabled' => env('FIREBASE_CACHE_ENABLED', !app()->environment('testing')),
    'store' => env('FIREBASE_CACHE_STORE', app()->environment('production') ? 'redis' : 'array'),
    'ttl' => env('FIREBASE_CACHE_TTL', app()->environment('production') ? 3600 : 60),
],

'auth' => [
    'guard' => env('FIREBASE_AUTH_GUARD', 'firebase'),
    'token_cache_ttl' => env('FIREBASE_TOKEN_CACHE_TTL', 3600),
],
```

### Role-Based Access Control

```php
// Set custom claims (Admin SDK)
$auth->setCustomUserClaims($uid, [
    'role' => 'admin',
    'permissions' => ['read', 'write', 'delete']
]);

// Check permissions in middleware
class CheckRole
{
    public function handle($request, Closure $next, $role)
    {
        $user = Auth::user();
        if (($user->custom_claims['role'] ?? null) !== $role) {
            abort(403);
        }
        return $next($request);
    }
}

// Use in routes
Route::middleware(['auth:firebase', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});
```

### Cache Optimization Strategies

```php
// Cache warming for frequently accessed data
class WarmCacheCommand extends Command
{
    public function handle()
    {
        User::where('active', true)->warmCache();
        Post::where('published', true)->cacheTags(['posts'])->warmCache();
        
        $this->info('Cache warmed successfully!');
    }
}

// Intelligent cache invalidation
class Post extends FirestoreModel
{
    protected static function booted()
    {
        static::saved(function ($post) {
            $post->flushCacheTags(['posts', 'user:' . $post->user_id]);
        });
    }
}
```

## 🛡️ Security Best Practices

### Authentication Security

1. **Token Validation**
   ```php
   // Always validate tokens server-side
   Route::middleware(['auth:firebase'])->group(function () {
       // Protected routes
   });
   ```

2. **Email Verification**
   ```php
   Route::middleware(['auth:firebase', 'firebase.verified'])->group(function () {
       // Require verified email
   });
   ```

3. **Custom Claims Validation**
   ```php
   public function handle($request, Closure $next)
   {
       $user = Auth::user();
       $requiredPermissions = ['read', 'write'];
       $userPermissions = $user->custom_claims['permissions'] ?? [];
       
       if (!array_intersect($requiredPermissions, $userPermissions)) {
           abort(403);
       }
       
       return $next($request);
   }
   ```

### Cache Security

1. **Sensitive Data Exclusion**
   ```php
   // Don't cache sensitive data
   $sensitiveData = User::withoutCache()
       ->where('role', 'admin')
       ->select('password_hash', 'api_keys')
       ->get();
   ```

2. **Cache Isolation**
   ```php
   // Use tenant-specific cache tags
   $tenantData = Data::cacheTags(['tenant:' . $tenantId])
       ->where('tenant_id', $tenantId)
       ->get();
   ```

## 📊 Monitoring and Debugging

### Authentication Monitoring

```php
// Log authentication attempts
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $result = Auth::guard('firebase')->attempt($credentials);
        
        Log::info('Firebase auth attempt', [
            'success' => $result,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return $result;
    }
}
```

### Cache Performance Monitoring

```php
// Monitor cache hit rates
$stats = RequestCache::getStats();
$hitRate = $stats['hits'] / ($stats['hits'] + $stats['misses']) * 100;

if ($hitRate < 80) {
    Log::warning("Low cache hit rate: {$hitRate}%", $stats);
}

// Cache debug information
$debugInfo = User::where('active', true)->getCacheDebugInfo();
Log::debug('Cache Debug', $debugInfo);
```

## 🧪 Testing

### Authentication Testing

```php
class AuthTest extends TestCase
{
    public function test_firebase_authentication()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'firebase')
            ->getJson('/api/user/profile');
            
        $response->assertOk();
    }
}
```

### Cache Testing

```php
class CacheTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        
        // Disable cache for testing
        config(['firebase-models.cache.enabled' => false]);
    }
    
    public function test_query_caching()
    {
        // Enable cache for this test
        config(['firebase-models.cache.enabled' => true]);
        
        $query = User::where('active', true);
        
        $this->assertFalse($query->isCached());
        
        $users = $query->get();
        
        $this->assertTrue($query->isCached());
    }
}
```

## 🚨 Troubleshooting

### Common Authentication Issues

| Issue | Solution |
|-------|----------|
| "Firebase guard not found" | Clear config cache: `php artisan config:clear` |
| "Invalid Firebase token" | Check token format and Firebase project ID |
| "User not found" | Ensure User model extends `FirebaseAuthenticatable` |

### Common Caching Issues

| Issue | Solution |
|-------|----------|
| Cache not working | Check `FIREBASE_CACHE_ENABLED=true` in .env |
| Wrong cache store | Verify cache store is properly configured |
| Cache not invalidating | Use `Model::flushCache()` or check cache tags |

## 📈 Performance Optimization

### Authentication Performance

- Use token caching: `FIREBASE_TOKEN_CACHE_TTL=3600`
- Implement custom user provider for database optimizations
- Use middleware to avoid repeated token validation

### Caching Performance

- Use Redis for production: `FIREBASE_CACHE_STORE=redis`
- Implement cache warming for frequently accessed data
- Use cache tags for efficient invalidation
- Monitor cache hit rates and optimize accordingly

## 📚 Additional Resources

- **[Installation Guide](02-installation.md)** - Package installation and setup
- **[Configuration Reference](03-configuration.md)** - Complete configuration options
- **[Model Documentation](04-models.md)** - Firestore model usage
- **[Query Builder](05-query-builder.md)** - Advanced querying
- **[Testing Guide](11-testing.md)** - Testing strategies and examples

## 🤝 Support

For issues and questions:
- Check the troubleshooting sections in individual guides
- Review the [GitHub Issues](https://github.com/jerthedev/firebase-models/issues)
- Consult the [Laravel Documentation](https://laravel.com/docs) for Laravel-specific questions
- Review [Firebase Documentation](https://firebase.google.com/docs) for Firebase-specific questions
