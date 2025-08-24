# Overview

**JTD Firebase Models** is a Laravel package that provides Eloquent-like models and Laravel Auth integration for Firebase (Firestore + Firebase Auth), built on kreait/firebase-php.

## What is JTD Firebase Models?

This package lets developers work with Firebase using a familiar, Laravel-style experience. Instead of learning Firebase's SDK directly, you can use models, query builders, and authentication patterns that feel just like Laravel's Eloquent and Auth systems.

## Key Features

### 🎯 **Core Features**
- **FirestoreModel**: Eloquent-like base class with full CRUD operations
- **Query Builder**: Advanced querying with where clauses, ordering, pagination
- **Events System**: Complete model events (creating, created, updating, updated, deleting, deleted, saved, retrieved)
- **FirestoreDB Facade**: Laravel-style facade for Firestore operations
- **Configuration**: Flexible Firebase configuration system

### 🚀 **Advanced Features**
- **Eloquent Accessors & Mutators**: Full Laravel-style attribute manipulation
- **Query Scopes**: Local and global scopes for reusable query logic
- **Firebase Auth**: Complete Laravel Auth integration with custom guards
- **Intelligent Caching**: Two-tier caching system (request + persistent)

### 🔄 **Operating Modes**

The package supports two operating modes:

1. **Cloud Mode**: Operate directly against Firestore with optional caching
   - All reads/writes go to Firestore
   - Configurable caching layer to reduce costs and latency
   - Perfect for applications that want to leverage Firebase's real-time features

2. **Sync Mode**: Keep a mirrored copy in local database
   - Firestore remains the source of truth
   - Local database mirror for faster queries and offline tolerance
   - Background or on-demand synchronization
   - Ideal for applications needing complex queries or offline capabilities

## Architecture Overview

### Core Components

- **FirestoreModel**: Abstract base class that behaves like Eloquent models
- **FirestoreDB Facade**: Laravel-style facade for Firestore operations
- **FirebaseAuthenticatable**: User model for Firebase Auth integration
- **Auth Guard/Provider**: Custom authentication components for Laravel Auth
- **Caching Layer**: Configurable request and persistent caching

### External Dependencies

- **kreait/firebase-php**: Official PHP SDK for Firebase services
- **Laravel Framework**: 12.x compatible
- **PHP**: 8.2+ required

## Developer Experience

### Models
```php
class Post extends FirestoreModel 
{
    protected ?string $collection = 'posts';
    protected array $fillable = ['title', 'content', 'published'];
}
```

### Authentication
```php
class User extends FirebaseAuthenticatable 
{
    // Integrates with Laravel's Auth system
    // Supports both cloud and sync modes
}
```

### Facades & Configuration
```php
// Use familiar Laravel patterns
FirestoreDB::collection('posts')->where('published', true)->get();

// Simple environment-driven configuration
FIREBASE_CREDENTIALS=path/to/service-account.json
FIREBASE_PROJECT_ID=your-project-id
```

## Compatibility Goals

This package aims for 1:1 feature compatibility with Laravel's DB Facade and Eloquent, including:

- Query Builder patterns
- Model attributes, casts, and relationships
- Event system
- Pagination
- Authentication flows
- Migrations and seeding (where Firestore allows)

While Firestore's document/collection model differs from relational databases, we provide familiar Laravel patterns wherever possible.

## Next Steps

- **Installation**: See [02-installation.md](02-installation.md) for setup instructions
- **Configuration**: See [03-configuration.md](03-configuration.md) for detailed configuration options
- **Models**: See [04-models.md](04-models.md) to start building your first Firestore models
- **Examples**: Check out [Examples/](Examples/) for comprehensive code examples
