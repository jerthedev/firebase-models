# Firebase Authentication How-To Guide

This practical guide provides step-by-step instructions for common Firebase Authentication scenarios using the JTD Firebase Models package.

## 📋 Quick Start Checklist

Before diving into specific scenarios, ensure you have:

- ✅ Firebase project with Authentication enabled
- ✅ Service account JSON file downloaded
- ✅ Package installed and configured ([Installation Guide](INSTALLATION.md))
- ✅ Auth configuration completed ([Auth Setup](AUTH_SETUP.md))

## 🔐 Common Authentication Scenarios

### 1. API Authentication with Bearer Tokens

**Use Case**: Secure API endpoints with Firebase ID tokens

#### Frontend (JavaScript)

```javascript
// Get Firebase ID token
import { getAuth, onAuthStateChanged } from 'firebase/auth';

const auth = getAuth();
onAuthStateChanged(auth, async (user) => {
    if (user) {
        const idToken = await user.getIdToken();
        
        // Make authenticated API request
        const response = await fetch('/api/user/profile', {
            headers: {
                'Authorization': `Bearer ${idToken}`,
                'Content-Type': 'application/json'
            }
        });
        
        const userData = await response.json();
    }
});
```

#### Backend (Laravel)

```php
<?php

// routes/api.php
Route::middleware(['auth:firebase'])->group(function () {
    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
});

// app/Http/Controllers/UserController.php
class UserController extends Controller
{
    public function profile(Request $request)
    {
        $user = Auth::user(); // Authenticated Firebase user
        
        return response()->json([
            'user' => $user,
            'firebase_uid' => Auth::id(),
            'email_verified' => $user->hasVerifiedEmail(),
        ]);
    }
}
```

### 2. Web Application with Session-Based Auth

**Use Case**: Traditional web app with Firebase authentication

#### Frontend (Blade + JavaScript)

```html
<!-- resources/views/auth/login.blade.php -->
<script type="module">
import { getAuth, signInWithEmailAndPassword } from 'firebase/auth';

document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const auth = getAuth();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    try {
        const userCredential = await signInWithEmailAndPassword(auth, email, password);
        const idToken = await userCredential.user.getIdToken();
        
        // Send token to Laravel backend
        const response = await fetch('/auth/firebase-login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ firebase_token: idToken })
        });
        
        if (response.ok) {
            window.location.href = '/dashboard';
        }
    } catch (error) {
        console.error('Login failed:', error);
    }
});
</script>
```

#### Backend (Laravel)

```php
<?php

// routes/web.php
Route::post('/auth/firebase-login', [AuthController::class, 'firebaseLogin']);
Route::post('/auth/logout', [AuthController::class, 'logout']);

// app/Http/Controllers/AuthController.php
class AuthController extends Controller
{
    public function firebaseLogin(Request $request)
    {
        $request->validate([
            'firebase_token' => 'required|string'
        ]);
        
        // Attempt authentication with Firebase token
        if (Auth::guard('firebase')->attempt(['token' => $request->firebase_token])) {
            $request->session()->regenerate();
            
            return response()->json([
                'success' => true,
                'redirect' => '/dashboard'
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Authentication failed'
        ], 401);
    }
    
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
}
```

### 3. Mobile App API with Custom Claims

**Use Case**: Mobile app with role-based access using Firebase custom claims

#### Set Custom Claims (Admin SDK)

```php
<?php

// app/Console/Commands/SetUserRole.php
use Kreait\Firebase\Contract\Auth as FirebaseAuth;

class SetUserRole extends Command
{
    public function handle(FirebaseAuth $auth)
    {
        $uid = $this->argument('uid');
        $role = $this->argument('role');
        
        $auth->setCustomUserClaims($uid, [
            'role' => $role,
            'permissions' => $this->getPermissionsForRole($role)
        ]);
        
        $this->info("Role '{$role}' set for user {$uid}");
    }
    
    private function getPermissionsForRole(string $role): array
    {
        return match($role) {
            'admin' => ['read', 'write', 'delete', 'manage_users'],
            'editor' => ['read', 'write'],
            'viewer' => ['read'],
            default => []
        };
    }
}
```

#### Backend Authorization

```php
<?php

// app/Http/Middleware/CheckRole.php
class CheckRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }
        
        $userRole = $user->custom_claims['role'] ?? null;
        
        if ($userRole !== $role) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }
        
        return $next($request);
    }
}

// routes/api.php
Route::middleware(['auth:firebase', 'role:admin'])->group(function () {
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
});
```

### 4. Email Verification Enforcement

**Use Case**: Require email verification for sensitive operations

```php
<?php

// routes/api.php
Route::middleware(['auth:firebase', 'firebase.verified'])->group(function () {
    Route::post('/payments', [PaymentController::class, 'process']);
    Route::get('/sensitive-data', [DataController::class, 'sensitive']);
});

// app/Http/Controllers/UserController.php
class UserController extends Controller
{
    public function requireEmailVerification(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'error' => 'Email verification required',
                'message' => 'Please verify your email before accessing this resource'
            ], 403);
        }
        
        // Continue with verified user
        return response()->json(['data' => 'Sensitive information']);
    }
}
```

## 🔧 Advanced Authentication Patterns

### 1. Multi-Tenant Authentication

```php
<?php

// app/Models/User.php
class User extends FirebaseAuthenticatable
{
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
    
    public function hasAccessToTenant(string $tenantId): bool
    {
        return $this->tenant_id === $tenantId || 
               $this->custom_claims['tenants'] ?? [] contains $tenantId;
    }
}

// app/Http/Middleware/CheckTenantAccess.php
class CheckTenantAccess
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $request->route('tenant');
        $user = Auth::user();
        
        if (!$user->hasAccessToTenant($tenantId)) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        
        return $next($request);
    }
}
```

### 2. Token Refresh Handling

```php
<?php

// app/Http/Middleware/RefreshFirebaseToken.php
class RefreshFirebaseToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if ($user && $this->tokenNeedsRefresh($user)) {
            // Notify frontend to refresh token
            return response()->json([
                'error' => 'Token refresh required',
                'code' => 'TOKEN_REFRESH_REQUIRED'
            ], 401);
        }
        
        return $next($request);
    }
    
    private function tokenNeedsRefresh($user): bool
    {
        $token = $user->getFirebaseToken();
        $expiresAt = $token->claims()->get('exp');
        
        // Refresh if token expires in less than 5 minutes
        return $expiresAt < (time() + 300);
    }
}
```

### 3. Custom User Provider

```php
<?php

// app/Auth/CustomFirebaseUserProvider.php
class CustomFirebaseUserProvider extends FirebaseUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        $user = parent::retrieveByCredentials($credentials);
        
        if ($user) {
            // Add custom logic, e.g., check if user is active
            if (!$user->is_active) {
                return null;
            }
            
            // Update last login
            $user->update(['last_login_at' => now()]);
        }
        
        return $user;
    }
}

// app/Providers/AuthServiceProvider.php
public function boot()
{
    Auth::provider('custom_firebase', function ($app, array $config) {
        return new CustomFirebaseUserProvider($config['model']);
    });
}
```

## 🛠️ Testing Authentication

### Unit Tests

```php
<?php

// tests/Feature/AuthTest.php
class AuthTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_authenticated_user_can_access_protected_route()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user, 'firebase')
            ->getJson('/api/user/profile');
            
        $response->assertOk()
            ->assertJsonStructure(['user', 'firebase_uid']);
    }
    
    public function test_unauthenticated_user_cannot_access_protected_route()
    {
        $response = $this->getJson('/api/user/profile');
        
        $response->assertUnauthorized();
    }
}
```

### Mock Firebase Authentication

```php
<?php

// tests/TestCase.php
abstract class TestCase extends BaseTestCase
{
    protected function mockFirebaseAuth()
    {
        $this->mock(FirebaseAuth::class, function ($mock) {
            $mock->shouldReceive('verifyIdToken')
                ->andReturn($this->createMockToken());
        });
    }
    
    private function createMockToken()
    {
        return new class {
            public function claims() {
                return collect([
                    'sub' => 'test-uid',
                    'email' => 'test@example.com',
                    'email_verified' => true,
                ]);
            }
        };
    }
}
```

## 🐛 Troubleshooting

### Common Issues and Solutions

1. **"Firebase guard not found"**
   ```bash
   # Clear config cache
   php artisan config:clear
   
   # Verify service provider is registered
   php artisan route:list | grep firebase
   ```

2. **"Invalid Firebase token"**
   ```php
   // Check token format
   $token = $request->bearerToken();
   if (!$token || !str_starts_with($token, 'eyJ')) {
       // Invalid token format
   }
   ```

3. **"User not found after authentication"**
   ```php
   // Ensure user exists in your database
   $firebaseUser = Auth::guard('firebase')->user();
   if (!$firebaseUser) {
       // Create user from Firebase data
       User::createFromFirebaseUser($firebaseData);
   }
   ```

### Debug Authentication

```php
<?php

// Add to routes/web.php for debugging
Route::get('/debug/auth', function () {
    return [
        'guards' => array_keys(config('auth.guards')),
        'default_guard' => config('auth.defaults.guard'),
        'current_guard' => Auth::getDefaultDriver(),
        'user' => Auth::user(),
        'check' => Auth::check(),
    ];
})->middleware('auth:firebase');
```

## 📚 Next Steps

- [Caching Configuration](CACHING.md)
- [Model Relationships](models.md)
- [Query Builder](query-builder.md)
- [Testing Guide](testing.md)
