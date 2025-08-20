# Installation Guide

This comprehensive guide covers installing and configuring `jerthedev/firebase-models` in a Laravel application.

## Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 12.x
- **Firebase Project**: With Firestore enabled
- **Service Account**: Firebase service account with appropriate permissions

## 1. Install via Composer

```bash
composer require jerthedev/firebase-models
```

## 2. Publish Configuration

```bash
php artisan vendor:publish --tag=firebase-models-config
```

This creates `config/firebase-models.php` with all available configuration options.

## 3. Firebase Setup

### Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Create a new project or select existing one
3. Enable Firestore Database in Native mode
4. Create a service account:
   - Go to Project Settings → Service Accounts
   - Click "Generate new private key"
   - Download the JSON file

### Configure Credentials

Place your Firebase service account JSON file in a secure location:

```bash
# Recommended location
storage/app/firebase/service-account.json
```

Add environment variables to your `.env` file:

```env
# Firebase Configuration
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
FIREBASE_PROJECT_ID=your-project-id

# Optional: Cache Configuration
FIREBASE_CACHE_STORE=redis
FIREBASE_DEFAULT_TTL=300

# Optional: Mode Configuration (default: cloud)
FIREBASE_MODE=cloud
```

## 4. Verify Service Provider

The package uses Laravel's auto-discovery feature. If you have disabled auto-discovery, manually add the service provider to `config/app.php`:

```php
'providers' => [
    // Other providers...
    JTD\FirebaseModels\JtdFirebaseModelsServiceProvider::class,
],
```

## 5. Test Configuration

Verify your configuration is working:

```bash
php artisan tinker
```

```php
// Test Firestore connection
use JTD\FirebaseModels\Facades\FirestoreDB;

// This should not throw an error
$database = app('firebase.firestore');
echo "Firebase connection successful!";
```

## 6. Create Your First Model

Generate a Firestore model:

```bash
php artisan make:firestore-model Post
```

Edit the generated model in `app/Models/Post.php`:

```php
<?php

namespace App\Models;

use JTD\FirebaseModels\Firestore\FirestoreModel;

class Post extends FirestoreModel
{
    protected ?string $collection = 'posts';

    protected array $fillable = [
        'title', 'content', 'published', 'author_id'
    ];

    protected array $casts = [
        'published' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
```

## 7. Test Your Model

Test your model in `php artisan tinker`:

```php
use App\Models\Post;

// Create a post
$post = Post::create([
    'title' => 'My First Post',
    'content' => 'This is my first post using Firebase Models!',
    'published' => true
]);

echo "Post created with ID: " . $post->id;

// Query posts
$posts = Post::where('published', true)->get();
echo "Found " . $posts->count() . " published posts";
```

## 8. Advanced Configuration (Optional)

### Firebase Auth Integration (Coming Soon)

Firebase Auth integration will be available in Sprint 2. For now, you can prepare by adding the guard configuration to `config/auth.php`:

```php
'guards' => [
    'firebase' => [
        'driver' => 'firebase',
        'provider' => 'firebase_users',
    ],
],

'providers' => [
    'firebase_users' => [
        'driver' => 'firebase',
        'model' => App\Models\User::class, // Will extend FirebaseAuthenticatable
    ],
],
```

### Caching Configuration

Enable caching for better performance:

```env
FIREBASE_CACHE_STORE=redis
FIREBASE_DEFAULT_TTL=300
```

Update `config/firebase-models.php`:

```php
'cache' => [
    'enabled' => true,
    'store' => env('FIREBASE_CACHE_STORE', 'redis'),
    'ttl' => env('FIREBASE_DEFAULT_TTL', 300),
],
```

### Sync Mode (Coming Soon)

Sync mode will be available in Sprint 3-4:

```env
FIREBASE_MODE=sync
```

## 9. Troubleshooting

### Common Issues

#### Service Account Permissions

Ensure your service account has the following IAM roles:
- **Cloud Datastore User** (for Firestore access)
- **Firebase Admin** (for full Firebase access)

#### Firestore Database Mode

Verify Firestore is in **Native mode** (not Datastore mode):
1. Go to Firebase Console → Firestore Database
2. Check the mode in the database info

#### Connection Issues

```bash
# Test Firebase connection
php artisan tinker
```

```php
try {
    $database = app('firebase.firestore');
    echo "✅ Firebase connection successful!";
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
```

#### Common Error Messages

**"Service account key file not found"**
- Check the path in `FIREBASE_CREDENTIALS`
- Ensure the file exists and is readable

**"Project ID not found"**
- Verify `FIREBASE_PROJECT_ID` in your `.env` file
- Check the project ID in Firebase Console

**"Permission denied"**
- Check service account IAM permissions
- Ensure Firestore rules allow your operations

### Debug Mode

Enable debug logging in `config/firebase-models.php`:

```php
'debug' => env('FIREBASE_DEBUG', false),
```

Set in `.env`:

```env
FIREBASE_DEBUG=true
```

## 10. Next Steps

### Documentation

- 📖 [Model Usage Guide](models.md) - Learn how to use FirestoreModel
- 🔍 [Query Builder](query-builder.md) - Advanced querying capabilities
- ⚡ [Events System](events.md) - Model events and observers
- 🧪 [Testing Guide](testing.md) - Testing with FirestoreMock

### Configuration

- ⚙️ [Configuration Reference](CONFIGURATION.md) - All configuration options
- 🏗️ [Architecture Overview](ARCHITECTURE.md) - Package design and structure

### Advanced Features (Coming Soon)

- 🔐 [Firebase Auth Integration](AUTH.md) - Laravel Auth with Firebase
- 💾 [Caching Strategy](caching.md) - Performance optimization
- 🔄 [Sync Mode](sync.md) - Local database mirroring

## Support

If you encounter issues:

1. Check the [troubleshooting section](#troubleshooting) above
2. Review the [documentation](../README.md#documentation)
3. Search existing [GitHub issues](https://github.com/jerthedev/firebase-models/issues)
4. Create a new issue with detailed information

## Security

Never commit your Firebase service account JSON file to version control. Always use environment variables and secure file storage.

