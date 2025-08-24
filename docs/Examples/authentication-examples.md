# Authentication Examples

This guide shows practical examples of using Firebase Authentication with Laravel's Auth system.

## Setting Up Firebase Authentication

### User Model

```php
<?php

namespace App\Models;

use JTD\FirebaseModels\Auth\FirebaseAuthenticatable;

class User extends FirebaseAuthenticatable
{
    protected ?string $collection = 'users';

    protected array $fillable = [
        'name',
        'email',
        'email_verified_at',
        'profile_picture',
        'role'
    ];

    protected array $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected array $hidden = [
        'password',
        'remember_token',
    ];
}
```

### Configuration

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'firebase' => [
        'driver' => 'firebase',
        'provider' => 'firebase_users',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    'firebase_users' => [
        'driver' => 'firebase',
        'model' => App\Models\User::class,
    ],
],
```

## Frontend Authentication

### JavaScript SDK Setup

```html
<!-- Include Firebase SDK -->
<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-app.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-auth.js"></script>

<script>
// Initialize Firebase
const firebaseConfig = {
    apiKey: "your-api-key",
    authDomain: "your-project.firebaseapp.com",
    projectId: "your-project-id",
};

firebase.initializeApp(firebaseConfig);
const auth = firebase.auth();
</script>
```

### Login Form

```html
<form id="loginForm">
    <input type="email" id="email" placeholder="Email" required>
    <input type="password" id="password" placeholder="Password" required>
    <button type="submit">Login</button>
</form>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    try {
        const userCredential = await auth.signInWithEmailAndPassword(email, password);
        const idToken = await userCredential.user.getIdToken();
        
        // Send token to Laravel backend
        const response = await fetch('/auth/firebase/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ idToken })
        });
        
        if (response.ok) {
            window.location.href = '/dashboard';
        }
    } catch (error) {
        console.error('Login error:', error);
        alert('Login failed: ' + error.message);
    }
});
</script>
```

## Backend Authentication

### Login Controller

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use JTD\FirebaseModels\Facades\FirebaseAuth;

class FirebaseLoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'idToken' => 'required|string'
        ]);

        try {
            // Verify the Firebase ID token
            $verifiedIdToken = FirebaseAuth::verifyIdToken($request->idToken);
            $uid = $verifiedIdToken->claims()->get('sub');
            
            // Find or create user
            $user = $this->findOrCreateUser($verifiedIdToken);
            
            // Log the user in
            Auth::guard('firebase')->login($user);
            
            return response()->json([
                'success' => true,
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 401);
        }
    }

    private function findOrCreateUser($verifiedIdToken)
    {
        $uid = $verifiedIdToken->claims()->get('sub');
        $email = $verifiedIdToken->claims()->get('email');
        $name = $verifiedIdToken->claims()->get('name');
        
        // Try to find existing user
        $user = User::where('firebase_uid', $uid)->first();
        
        if (!$user) {
            // Create new user
            $user = User::create([
                'firebase_uid' => $uid,
                'name' => $name,
                'email' => $email,
                'email_verified_at' => $verifiedIdToken->claims()->get('email_verified') ? now() : null,
            ]);
        }
        
        return $user;
    }
}
```

### Logout Controller

```php
public function logout(Request $request)
{
    Auth::guard('firebase')->logout();
    
    return response()->json([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}
```

## Middleware and Route Protection

### Firebase Auth Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FirebaseAuth
{
    public function handle(Request $request, Closure $next, $guard = 'firebase')
    {
        if (!Auth::guard($guard)->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            
            return redirect()->route('login');
        }

        return $next($request);
    }
}
```

### Protected Routes

```php
// routes/web.php
Route::middleware(['auth:firebase'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/posts', [PostController::class, 'store']);
});

// API routes
Route::middleware(['auth:firebase'])->prefix('api')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::apiResource('posts', PostController::class);
});
```

## User Registration

### Registration Form

```html
<form id="registerForm">
    <input type="text" id="name" placeholder="Full Name" required>
    <input type="email" id="email" placeholder="Email" required>
    <input type="password" id="password" placeholder="Password" required>
    <button type="submit">Register</button>
</form>

<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    try {
        const userCredential = await auth.createUserWithEmailAndPassword(email, password);
        
        // Update profile
        await userCredential.user.updateProfile({
            displayName: name
        });
        
        const idToken = await userCredential.user.getIdToken();
        
        // Send to Laravel backend
        const response = await fetch('/auth/firebase/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ idToken, name })
        });
        
        if (response.ok) {
            window.location.href = '/dashboard';
        }
    } catch (error) {
        console.error('Registration error:', error);
        alert('Registration failed: ' + error.message);
    }
});
</script>
```

### Registration Controller

```php
public function register(Request $request)
{
    $request->validate([
        'idToken' => 'required|string',
        'name' => 'required|string|max:255'
    ]);

    try {
        $verifiedIdToken = FirebaseAuth::verifyIdToken($request->idToken);
        $uid = $verifiedIdToken->claims()->get('sub');
        $email = $verifiedIdToken->claims()->get('email');
        
        // Create user in Firestore
        $user = User::create([
            'firebase_uid' => $uid,
            'name' => $request->name,
            'email' => $email,
            'email_verified_at' => $verifiedIdToken->claims()->get('email_verified') ? now() : null,
        ]);
        
        Auth::guard('firebase')->login($user);
        
        return response()->json([
            'success' => true,
            'user' => $user
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Registration failed: ' . $e->getMessage()
        ], 400);
    }
}
```

## Social Authentication

### Google Sign-In

```html
<button id="googleSignIn">Sign in with Google</button>

<script>
document.getElementById('googleSignIn').addEventListener('click', async () => {
    const provider = new firebase.auth.GoogleAuthProvider();
    
    try {
        const result = await auth.signInWithPopup(provider);
        const idToken = await result.user.getIdToken();
        
        // Send to Laravel backend
        const response = await fetch('/auth/firebase/social', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ 
                idToken,
                provider: 'google'
            })
        });
        
        if (response.ok) {
            window.location.href = '/dashboard';
        }
    } catch (error) {
        console.error('Google sign-in error:', error);
    }
});
</script>
```

## User Profile Management

### Profile Controller

```php
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('profile.show', [
            'user' => $request->user()
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'profile_picture' => 'nullable|url'
        ]);

        $user = $request->user();
        $user->update($request->only(['name', 'profile_picture']));

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }
}
```

## Error Handling

### Authentication Errors

```php
use JTD\FirebaseModels\Exceptions\FirebaseAuthException;

try {
    $verifiedIdToken = FirebaseAuth::verifyIdToken($request->idToken);
} catch (FirebaseAuthException $e) {
    return response()->json([
        'error' => 'Invalid token',
        'message' => $e->getMessage()
    ], 401);
} catch (\Exception $e) {
    return response()->json([
        'error' => 'Authentication failed',
        'message' => $e->getMessage()
    ], 500);
}
```

## Testing Authentication

```php
use Tests\TestCase;
use App\Models\User;
use JTD\FirebaseModels\Testing\FirebaseAuthMock;

class AuthenticationTest extends TestCase
{
    use FirebaseAuthMock;

    public function test_user_can_login_with_firebase()
    {
        $user = User::factory()->create();
        
        $this->mockFirebaseAuth($user);
        
        $response = $this->postJson('/auth/firebase/login', [
            'idToken' => 'mock-token'
        ]);
        
        $response->assertOk();
        $this->assertAuthenticatedAs($user, 'firebase');
    }
}
```

## Next Steps

- Learn about [Caching Examples](caching-examples.md)
- Explore [Testing Examples](testing-examples.md)
- See [Basic CRUD Operations](basic-crud.md)
