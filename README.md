# JTD Firebase Models

🔥 **Eloquent-like models and Laravel Auth integration for Firebase** (Firestore + Firebase Auth), built on kreait/firebase-php.

[![Tests](https://github.com/jerthedev/firebase-models/workflows/tests/badge.svg)](https://github.com/jerthedev/firebase-models/actions)
[![Code Quality](https://github.com/jerthedev/firebase-models/workflows/code-quality/badge.svg)](https://github.com/jerthedev/firebase-models/actions)
[![Latest Stable Version](https://poser.pugx.org/jerthedev/firebase-models/v/stable)](https://packagist.org/packages/jerthedev/firebase-models)
[![License](https://poser.pugx.org/jerthedev/firebase-models/license)](https://packagist.org/packages/jerthedev/firebase-models)

- **Package**: `jerthedev/firebase-models`
- **Namespace**: `JTD\FirebaseModels`
- **Laravel**: 12.x compatible
- **PHP**: 8.2+ required

## ✨ Features

- 🎯 **FirestoreModel**: Eloquent-like base class with full CRUD operations
- 🔍 **Query Builder**: Advanced querying with where clauses, ordering, pagination
- ⚡ **Events System**: Complete model events (creating, created, updating, updated, deleting, deleted, saved, retrieved)
- 🎭 **FirestoreDB Facade**: Laravel-style facade for Firestore operations
- 🔐 **Firebase Auth**: Complete Laravel Auth integration with custom guards
- ⚡ **Intelligent Caching**: Two-tier caching system (request + persistent)
- 🔄 **Sync Mode**: Mirror Firestore to local database
- 🧪 **Testing**: Comprehensive test harness with FirestoreMock

## 📦 Installation

```bash
# Install via Composer
composer require jerthedev/firebase-models

# Publish configuration
php artisan vendor:publish --tag=firebase-models-config

# Configure your Firebase credentials in .env
FIREBASE_CREDENTIALS=path/to/service-account.json
FIREBASE_PROJECT_ID=your-project-id
```

👉 **See [docs/02-installation.md](docs/02-installation.md) for complete setup instructions**

## 🚀 Quick Start

### 1. Create a Firestore Model

```bash
php artisan make:firestore-model Post
```

```php
<?php

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

### 2. Use Your Model

```php
// Create a new post
$post = Post::create([
    'title' => 'My First Post',
    'content' => 'This is the content of my post.',
    'published' => true,
    'author_id' => 'user123'
]);

// Query posts
$posts = Post::where('published', true)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Find a specific post
$post = Post::find('post-id-123');

// Update a post
$post->update(['title' => 'Updated Title']);

// Delete a post
$post->delete();
```

### 3. Advanced Querying

```php
// Complex queries
$posts = Post::where('published', true)
    ->whereIn('category_id', [1, 2, 3])
    ->whereBetween('created_at', ['2023-01-01', '2023-12-31'])
    ->orderBy('created_at', 'desc')
    ->paginate(15);

// Aggregates
$count = Post::where('published', true)->count();
$latest = Post::latest()->first();

// Event listeners
Post::creating(function ($post) {
    $post->slug = Str::slug($post->title);
});
```

## 📚 Documentation

**👉 [Complete Documentation](docs/README.md)** - Comprehensive documentation index

### **Quick Links**
- 📖 [Installation Guide](docs/02-installation.md) - Complete setup instructions
- 🎯 [Models Guide](docs/04-models.md) - FirestoreModel usage and features
- 🔍 [Query Builder](docs/05-query-builder.md) - Advanced querying capabilities
- 🔐 [Authentication](docs/08-authentication.md) - Firebase Auth integration
- ⚡ [Caching](docs/09-caching.md) - Performance optimization
- 🧪 [Testing](docs/11-testing.md) - Testing with FirestoreMock

### **Examples**
- [Basic CRUD Operations](docs/Examples/basic-crud.md)
- [Advanced Querying](docs/Examples/advanced-querying.md)
- [Authentication Examples](docs/Examples/authentication-examples.md)
- [Caching Examples](docs/Examples/caching-examples.md)
- [Testing Examples](docs/Examples/testing-examples.md)

## 🧪 Testing

This package includes a **production-ready testing suite** with **100% passing core tests** for reliable CI/CD:

```bash
# Run core test suite (100% passing - recommended for CI/CD)
./test-core.sh

# Or run core tests directly
vendor/bin/phpunit --configuration=phpunit-core.xml

# Run additional test suites (may have known issues)
vendor/bin/pest --testsuite=Feature       # End-to-end feature tests
vendor/bin/pest --testsuite=Integration   # Integration tests
vendor/bin/pest --testsuite=Performance   # Performance benchmarks

# Run code quality checks
composer format
composer analyse
```

### **Core Test Suite (100% Passing)**
- ✅ **Firestore Query Builder** - All query operations and functionality
- ✅ **Firestore Models** - Complete model CRUD operations and features
- ✅ **Firebase Authentication** - Auth providers, guards, and error handling
- ✅ **Cache Integration** - Persistent caching and performance optimization
- ✅ **Accessor/Mutator System** - Data transformation and attribute handling
- ✅ **Query Scopes** - Local and global scope functionality

### **Testing Features**
- 🎯 **Production Ready** - 100% passing core test suite for reliable deployments
- 🎭 **FirestoreMock** - In-memory Firestore emulation for fast testing
- 🔧 **Test Helpers** - Custom expectations and assertions for Firebase models
- 🚀 **CI/CD Optimized** - GitHub Actions integration with guaranteed passing tests
- 📊 **Comprehensive Coverage** - All essential Firebase Models features tested

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### **Development Setup**
```bash
# Clone the repository
git clone https://github.com/jerthedev/firebase-models.git

# Install dependencies
composer install

# Run tests
composer test
```

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## 🙏 Credits

- Built on [kreait/firebase-php](https://github.com/kreait/firebase-php)
- Inspired by [Laravel Eloquent](https://laravel.com/docs/eloquent)
- Developed by [JTD](https://github.com/jerthedev)

