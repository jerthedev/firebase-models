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

### 🎯 **Core Features (Sprint 1 Complete)**
- ✅ **FirestoreModel**: Eloquent-like base class with full CRUD operations
- ✅ **Query Builder**: Advanced querying with where clauses, ordering, pagination
- ✅ **Events System**: Complete model events (creating, created, updating, updated, deleting, deleted, saved, retrieved)
- ✅ **FirestoreDB Facade**: Laravel-style facade for Firestore operations
- ✅ **Configuration**: Flexible Firebase configuration system
- ✅ **Testing**: Comprehensive test harness with FirestoreMock

### 🚀 **Coming Soon (Sprint 2-4)**
- 🔄 **Relationships**: Eloquent-style model relationships
- 🗑️ **Soft Deletes**: Soft delete functionality for models
- 🔍 **Scopes**: Global and local query scopes
- 🔐 **Firebase Auth**: Custom Auth guard/provider for Laravel
- 💾 **Caching**: Request + persistent caching layer
- 🔄 **Sync Mode**: Mirror Firestore to local database

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

👉 **See [docs/INSTALLATION.md](docs/INSTALLATION.md) for complete setup instructions**

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

### **Getting Started**
- 📖 [Installation Guide](docs/INSTALLATION.md) - Complete setup instructions
- 🏗️ [Architecture Overview](docs/ARCHITECTURE.md) - Package design and structure
- ⚙️ [Configuration Reference](docs/CONFIGURATION.md) - All configuration options

### **Core Features**
- 🎯 [FirestoreModel Guide](docs/models.md) - Complete model usage guide
- 🔍 [Query Builder](docs/query-builder.md) - Advanced querying capabilities
- ⚡ [Events System](docs/events.md) - Model events and observers
- 🎭 [Facades](docs/facades.md) - FirestoreDB facade usage

### **Advanced Topics**
- 🔐 [Firebase Auth Integration](docs/AUTH.md) - Laravel Auth with Firebase
- 💾 [Caching Strategy](docs/caching.md) - Performance optimization
- 🔄 [Sync Mode](docs/sync.md) - Local database mirroring
- 🧪 [Testing Guide](docs/testing.md) - Testing with FirestoreMock

### **Development**
- 📋 [Project Overview](docs/PROJECT_OVERVIEW.md) - Package goals and vision
- 🚀 [Sprint Plans](docs/Sprint1.md) - Development roadmap
- 🎨 [Laravel Compatibility](docs/ELOQUENT_COMPATIBILITY.md) - Eloquent feature mapping

## 🧪 Testing

This package includes a comprehensive testing suite:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run code quality checks
composer format
composer analyse
```

### **Testing Features**
- ✅ **100% Unit Test Coverage** - Comprehensive test coverage for all features
- 🎭 **FirestoreMock** - In-memory Firestore emulation for fast testing
- 🔧 **Test Helpers** - Custom expectations and assertions for Firebase models
- 🚀 **CI/CD Ready** - GitHub Actions integration with quality gates

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

