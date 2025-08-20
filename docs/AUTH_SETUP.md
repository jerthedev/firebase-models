# Firebase Auth Setup Guide

This guide walks you through setting up Firebase Authentication with Laravel's auth system using the JTD Firebase Models package.

## 📋 Prerequisites

1. **Firebase Project**: Set up a Firebase project with Authentication enabled
2. **Service Account**: Download your Firebase service account JSON file
3. **Laravel Project**: Laravel 10+ with the JTD Firebase Models package installed

## 🔧 Configuration Steps

### 1. Environment Variables

Add these to your `.env` file:

```env
# Firebase Configuration
FIREBASE_CREDENTIALS=path/to/your/service-account.json
FIREBASE_PROJECT_ID=your-firebase-project-id

# Optional: Firebase Auth Configuration
FIREBASE_AUTH_GUARD=firebase
FIREBASE_AUTH_PROVIDER=firebase_users
FIREBASE_USER_MODEL=App\Models\User
FIREBASE_TOKEN_CACHE_TTL=3600
```

### 2. Laravel Auth Configuration

Update your `config/auth.php` file:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'guard' => 'firebase',  // Set Firebase as default guard
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'firebase' => [
            'driver' => 'firebase',
            'provider' => 'firebase_users',
            'input_key' => 'token',           // Query/form parameter name
            'header_key' => 'Authorization',  // Header name for Bearer token
            'cookie_key' => 'firebase_token', // Cookie name
        ],

        'api' => [
            'driver' => 'firebase',  // Use Firebase for API authentication
            'provider' => 'firebase_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        'firebase_users' => [
            'driver' => 'firebase',
            'model' => App\Models\User::class, // Must extend FirebaseAuthenticatable
        ],
    ],

    // ... rest of your auth config
];
```

### 3. User Model Setup

Update your User model to extend `FirebaseAuthenticatable`:

```php
<?php

namespace App\Models;

use JTD\FirebaseModels\Auth\FirebaseAuthenticatable;
use Illuminate\Notifications\Notifiable;

class User extends FirebaseAuthenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'photo_url',
        'phone_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'custom_claims' => 'array',
    ];
}
```

## 🛡️ Middleware Usage

The package provides several middleware for different authentication scenarios:

### 1. Firebase Authentication Middleware

Require Firebase authentication for routes:

```php
// Using the auth middleware with firebase guard
Route::middleware(['auth:firebase'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/posts', [PostController::class, 'store']);
});

// Or use the firebase.auth alias
Route::middleware(['firebase.auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### 2. Token Verification Middleware

Verify Firebase tokens without full authentication:

```php
// Required token verification
Route::middleware(['firebase.token'])->group(function () {
    Route::get('/public-data', [DataController::class, 'public']);
});

// Optional token verification
Route::middleware(['firebase.token:optional'])->group(function () {
    Route::get('/mixed-content', [ContentController::class, 'mixed']);
});
```

### 3. Email Verification Middleware

Ensure users have verified their email:

```php
Route::middleware(['auth:firebase', 'firebase.verified'])->group(function () {
    Route::get('/verified-only', [SecureController::class, 'index']);
});
```

## 🔑 Token Sources

The Firebase guard automatically checks for tokens in multiple locations:

### 1. Authorization Header (Recommended for APIs)

```javascript
// Frontend JavaScript example
fetch('/api/user', {
    headers: {
        'Authorization': `Bearer ${firebaseIdToken}`,
        'Content-Type': 'application/json'
    }
});
```

### 2. Query Parameter

```
GET /api/user?token=eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
```

### 3. Form Input

```html
<form method="POST" action="/api/posts">
    @csrf
    <input type="hidden" name="token" value="{{ $firebaseToken }}">
    <input type="text" name="title" placeholder="Post title">
    <button type="submit">Create Post</button>
</form>
```

### 4. Cookie

```javascript
// Set cookie on frontend
document.cookie = `firebase_token=${firebaseIdToken}; path=/; secure; samesite=strict`;
```

## 🚀 Usage Examples

### Basic Authentication Check

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();
        
        // Check if user is authenticated
        if (Auth::check()) {
            return response()->json([
                'user' => $user,
                'uid' => Auth::id(),
                'firebase_token' => $user->getFirebaseToken(),
            ]);
        }
        
        return response()->json(['error' => 'Unauthenticated'], 401);
    }
}
```

### Manual Authentication

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $token = $request->input('firebase_token');
        
        // Attempt authentication with Firebase token
        if (Auth::attempt(['token' => $token])) {
            $user = Auth::user();
            
            return response()->json([
                'message' => 'Authentication successful',
                'user' => $user,
            ]);
        }
        
        return response()->json([
            'error' => 'Authentication failed',
            'message' => 'Invalid Firebase token'
        ], 401);
    }
    
    public function logout()
    {
        Auth::logout();
        
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
```

### Accessing Firebase Token Data

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        
        // Access Firebase token claims
        $token = $user->getFirebaseToken();
        $claims = $token->claims()->all();
        
        // Access custom claims
        $customClaims = $user->custom_claims ?? [];
        
        return response()->json([
            'user' => $user,
            'firebase_claims' => $claims,
            'custom_claims' => $customClaims,
            'email_verified' => $user->hasVerifiedEmail(),
        ]);
    }
}
```

## 🔧 Advanced Configuration

### Custom Token Sources

You can customize token source keys in your guard configuration:

```php
'guards' => [
    'firebase' => [
        'driver' => 'firebase',
        'provider' => 'firebase_users',
        'input_key' => 'firebase_token',      // Custom form input name
        'header_key' => 'X-Firebase-Token',   // Custom header name
        'cookie_key' => 'auth_token',         // Custom cookie name
    ],
],
```

### Multiple Guards

You can set up multiple Firebase guards for different purposes:

```php
'guards' => [
    'firebase_web' => [
        'driver' => 'firebase',
        'provider' => 'firebase_users',
        'cookie_key' => 'web_token',
    ],
    
    'firebase_api' => [
        'driver' => 'firebase',
        'provider' => 'firebase_users',
        'header_key' => 'Authorization',
    ],
],
```

## 🐛 Troubleshooting

### Common Issues

1. **"Firebase guard not found"**
   - Ensure the service provider is registered
   - Check that `config/auth.php` has the correct guard configuration

2. **"Invalid Firebase token"**
   - Verify your Firebase project ID is correct
   - Ensure the service account has proper permissions
   - Check that the token hasn't expired

3. **"User not found"**
   - Ensure your User model extends `FirebaseAuthenticatable`
   - Check that the Firebase UID exists in your user provider

### Debug Mode

Enable debug logging in your `.env`:

```env
LOG_LEVEL=debug
```

This will log Firebase authentication attempts and errors to help with troubleshooting.

## 📚 Next Steps

- [Model Relationships](RELATIONSHIPS.md)
- [Caching Configuration](CACHING.md)
- [API Documentation](API.md)
