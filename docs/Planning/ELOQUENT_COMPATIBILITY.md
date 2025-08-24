# Eloquent Compatibility

This document outlines the compatibility between Laravel's Eloquent ORM and JTD Firebase Models, providing developers with a clear understanding of what Eloquent features are available when working with Firestore.

## ✅ Fully Implemented (Sprint 1)

### Core Model Features
- **✅ Attributes & Mass Assignment**: Complete `$fillable`/`$guarded` protection with Laravel-identical behavior
- **✅ Attribute Casting**: Full casting system supporting all Laravel cast types (string, int, bool, array, date, datetime, collection, etc.)
- **✅ Accessors/Mutators**: Complete support for `getXAttribute()` and `setXAttribute()` methods
- **✅ Timestamps**: Automatic `created_at`/`updated_at` management with Firestore Timestamp support
- **✅ Model Events**: Complete event lifecycle (creating, created, updating, updated, deleting, deleted, saving, saved, retrieved)
- **✅ Event Observers**: Full observer pattern support with automatic method registration
- **✅ Serialization**: Array and JSON conversion with `$hidden`, `$visible`, and `$appends` support

### CRUD Operations
- **✅ Create**: `Model::create()`, `new Model()->save()`, `firstOrCreate()`, `updateOrCreate()`
- **✅ Read**: `find()`, `findOrFail()`, `first()`, `firstOrFail()`, `get()`, `all()`
- **✅ Update**: `update()`, `save()`, `touch()`, `increment()`, `decrement()`
- **✅ Delete**: `delete()`, `deleteQuietly()`, `forceDelete()`

### Query Builder
- **✅ Where Clauses**: `where()`, `orWhere()`, `whereIn()`, `whereNotIn()`, `whereNull()`, `whereNotNull()`
- **✅ Ordering**: `orderBy()`, `orderByDesc()` with Firestore direction mapping
- **✅ Limiting**: `limit()`, `take()`, `offset()`, `skip()` (with Firestore adaptations)
- **✅ Selection**: `select()`, `addSelect()`, `distinct()`
- **✅ Aggregates**: `count()`, `max()`, `min()`, `avg()`, `sum()` (collection-based)
- **✅ Existence**: `exists()`, `doesntExist()`
- **✅ Chunking**: `chunk()`, `each()`, `lazy()` for efficient batch processing

### Pagination
- **✅ Length-Aware Pagination**: `paginate()` with total count calculation
- **✅ Simple Pagination**: `simplePaginate()` for performance-optimized pagination
- **🔄 Cursor Pagination**: Planned for Sprint 2 using Firestore's native cursor support

### Collections & Results
- **✅ Illuminate Collections**: All query results return Laravel Collections
- **✅ Model Hydration**: Automatic conversion of Firestore documents to model instances
- **✅ Collection Methods**: Full access to Laravel's collection manipulation methods

## ✅ Firebase Authentication (Sprint 2 Week 1)

### Laravel Auth Integration
- **✅ Authenticatable Interface**: Complete implementation with Firebase UID as primary key
- **✅ Authorizable Interface**: Full authorization support with Laravel's Gate system
- **✅ CanResetPassword Interface**: Password reset interface (adapted for Firebase)
- **✅ Notifiable Trait**: Laravel notification system integration
- **✅ Email Verification**: `hasVerifiedEmail()`, `markEmailAsVerified()`, `getEmailForVerification()`

### Firebase-Specific Features
- **✅ Token Hydration**: Automatic user data extraction from Firebase ID tokens
- **✅ Claims Mapping**: Custom claims support for roles and permissions
- **✅ UserRecord Integration**: Hydration from Firebase Admin SDK UserRecord objects
- **✅ Multi-Provider Support**: Handle multiple authentication providers (Google, Facebook, etc.)

### Laravel Auth System Integration
- **✅ Firebase Guard**: Custom guard implementing Laravel's Guard interface
- **✅ Firebase User Provider**: Custom provider implementing Laravel's UserProvider interface
- **✅ Service Provider Registration**: Automatic guard and provider registration
- **✅ Token Sources**: Support for Bearer tokens, query params, input fields, and cookies
- **✅ Laravel Auth Facade**: Full compatibility with `Auth::user()`, `Auth::check()`, etc.

### Middleware Integration
- **✅ Firebase Auth Middleware**: Laravel-compatible auth middleware with Firebase-specific features
- **✅ Token Verification Middleware**: Standalone token verification without full authentication
- **✅ Email Verification Middleware**: Firebase-compatible email verification enforcement
- **✅ Automatic Registration**: Middleware aliases automatically registered in Laravel
- **✅ Multiple Token Sources**: Bearer header, query params, form input, cookies

### Laravel-Compatible User Model
- **✅ Standard Attributes**: `name`, `email`, `email_verified_at`, `created_at`, `updated_at`
- **✅ Hidden Attributes**: `password`, `remember_token` (for compatibility)
- **✅ Casting Support**: `email_verified_at` as datetime, `password` as hashed
- **✅ Mass Assignment**: Standard Laravel fillable/guarded protection
- **✅ Query Scopes**: `verified()`, `unverified()`, `withRole()` scopes

### Computed Attributes & Helpers
- **✅ Avatar Support**: Gravatar integration with photo_url fallback
- **✅ Name Handling**: `full_name`, `initials` computed attributes
- **✅ Role Checking**: `isAdmin()`, `isModerator()`, `hasRole()`, `hasPermission()`
- **✅ Preferences**: User preference storage and management
- **✅ Localization**: Timezone and locale support

## 🔄 Planned Features (Sprint 2+)

### Relationships
- **🔄 belongsTo-like**: Store foreign document ID or reference with lazy loading
- **🔄 hasMany-like**: Query by foreign key field or array of refs with eager loading
- **🔄 Many-to-many-like**: Join collection pattern or array of IDs with pivot support
- **🔄 Relationship Helpers**: Common ergonomics for document-based relationships

### Advanced Features
- **🔄 Soft Deletes**: `deleted_at` emulation with `SoftDeletes` trait
- **🔄 Global Scopes**: Automatic query constraints applied to all model queries
- **🔄 Local Scopes**: Reusable query constraints as model methods
- **🔄 Attribute Mutators**: New Laravel attribute mutator syntax support
- **🔄 Model Factories**: Firestore-compatible model factories for testing

### Performance & Optimization
- **🔄 Eager Loading**: Efficient relationship loading to minimize queries
- **🔄 Cursor Pagination**: Native Firestore cursor-based pagination
- **🔄 Query Optimization**: Automatic query optimization for Firestore constraints
- **🔄 Caching Integration**: Model-level caching with automatic invalidation

## 🎯 Usage Examples

### Basic Model Definition
```php
<?php

namespace App\Models;

use JTD\FirebaseModels\Firestore\FirestoreModel;

class User extends FirestoreModel
{
    protected $fillable = [
        'name', 'email', 'active'
    ];

    protected $casts = [
        'active' => 'boolean',
        'email_verified_at' => 'datetime',
        'preferences' => 'array',
        'metadata' => 'json',
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    // Automatic timestamps
    public $timestamps = true;
}
```

### CRUD Operations
```php
// Create - exactly like Eloquent
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'active' => true
]);

// Read - familiar Eloquent syntax
$user = User::find($id);
$users = User::where('active', true)->get();
$activeUser = User::where('email', 'john@example.com')->first();

// Update - same as Eloquent
$user->update(['name' => 'Jane Doe']);
$user->name = 'Jane Smith';
$user->save();

// Delete - identical behavior
$user->delete();
```

### Advanced Queries
```php
// Complex queries work exactly like Eloquent
$recentActiveUsers = User::where('active', true)
    ->where('created_at', '>', Carbon::now()->subDays(30))
    ->whereIn('role', ['admin', 'editor'])
    ->orderBy('name')
    ->limit(50)
    ->get();

// Pagination
$users = User::where('active', true)->paginate(15);

// Aggregates
$userCount = User::where('active', true)->count();
$averageAge = User::avg('age');

// First or create
$user = User::firstOrCreate(
    ['email' => 'john@example.com'],
    ['name' => 'John Doe', 'active' => true]
);
```

### Model Events
```php
class User extends FirestoreModel
{
    protected static function booted()
    {
        static::creating(function ($user) {
            $user->uuid = Str::uuid();
        });

        static::updating(function ($user) {
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
        });

        static::deleted(function ($user) {
            // Clean up related data
        });
    }
}
```

### Attribute Casting
```php
class Post extends FirestoreModel
{
    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => 'array',
        'active' => 'boolean',
        'views' => 'integer',
        'tags' => 'collection',
    ];
}

// Automatic casting works exactly like Eloquent
$post = Post::find($id);
$post->published_at; // Returns Carbon instance
$post->metadata; // Returns array
$post->active; // Returns boolean
$post->tags; // Returns Collection
```

## 🚫 Firestore Limitations & Differences

### Firebase Authentication Constraints
- **❌ Password Methods**: `getAuthPassword()` and `getAuthPasswordName()` throw exceptions (Firebase handles auth)
- **❌ Remember Token**: `getRememberToken()` and `setRememberToken()` are no-ops (Firebase manages sessions)
- **⚠️ Password Reset**: Uses Firebase Auth password reset flow, not Laravel's default email-based reset
- **⚠️ Email Verification**: Uses Firebase email verification, not Laravel's default verification emails
- **⚠️ Primary Key**: Uses Firebase UID (`uid`) instead of auto-incrementing `id`
- **⚠️ User Storage**: Users stored in Firestore collections, not traditional database tables

### Firebase Authentication Compatibility
- **✅ Auth Facades**: Works with `Auth::user()`, `Auth::check()`, `Auth::id()`, etc.
- **✅ Middleware**: Compatible with `auth`, `verified`, and custom auth middleware
- **✅ Gates & Policies**: Full Laravel authorization system support
- **✅ Notifications**: Send notifications to Firebase-authenticated users
- **✅ Model Events**: User model events work exactly like Eloquent
- **✅ Query Scopes**: User query scopes work with Firestore queries
- **✅ Relationships**: Can define relationships with other Firestore models

### Guard and Provider Features
- **✅ Multiple Token Sources**: Bearer header, query params, form input, cookies
- **✅ Token Validation**: Firebase ID token verification with proper error handling
- **✅ User Resolution**: Automatic user creation/retrieval from Firebase Auth
- **✅ Session Management**: Stateless authentication with optional session support
- **✅ Laravel Integration**: Standard `attempt()`, `validate()`, `logout()` methods

### Middleware Features
- **✅ Standard Auth Middleware**: `auth:firebase` works like Laravel's built-in auth
- **✅ Firebase-Specific Middleware**: `firebase.auth`, `firebase.token`, `firebase.verified`
- **✅ Optional Authentication**: Token verification without requiring authentication
- **✅ Error Handling**: Proper JSON responses for API requests, redirects for web
- **✅ Request Attributes**: Token data automatically added to request for downstream use

### Unsupported Features
- **Complex Joins**: No server-side joins in Firestore (use relationships with multiple queries)
- **Raw SQL**: Not applicable to document databases
- **Database-level Constraints**: No foreign key constraints (handled at application level)
- **Stored Procedures**: Not supported in Firestore
- **Views**: No database views (create computed properties or scopes instead)

### Query Constraints
- **Inequality Filters**: Only one field can have inequality operators per query
- **Array Queries**: Limited `array-contains` and `array-contains-any` operations
- **OR Queries**: Limited support; use `whereIn()` or multiple queries with union
- **Case-Insensitive Queries**: Not natively supported (requires data normalization)

### Firestore-Specific Adaptations
- **Composite Indexes**: Required for certain where/order combinations
- **Query Limits**: Maximum 30 composite filters per query
- **Document Size**: 1MB limit per document
- **Collection Group Queries**: Special syntax for querying across subcollections

## 🔧 Firestore-Specific Features

### Data Types
```php
class Event extends FirestoreModel
{
    protected $casts = [
        'timestamp' => 'timestamp',      // Firestore Timestamp
        'location' => 'geopoint',        // Firestore GeoPoint
        'attendees' => 'array',          // Firestore Array
        'metadata' => 'map',             // Firestore Map
    ];
}
```

### Document References
```php
// Store document references
$post->author_ref = $userDocumentReference;

// Query by reference
$posts = Post::where('author_ref', '==', $userRef)->get();
```

### Array Operations
```php
// Array contains queries
$posts = Post::where('tags', 'array-contains', 'php')->get();
$posts = Post::where('tags', 'array-contains-any', ['php', 'laravel'])->get();
```

## 📊 Performance Considerations

### Indexing Strategy
- **Automatic Indexes**: Single-field indexes created automatically
- **Composite Indexes**: Required for complex queries (auto-suggested by Firestore)
- **Index Management**: Use Firebase Console or CLI for index management

### Query Optimization
```php
// Efficient: Uses single-field indexes
$users = User::where('active', true)->orderBy('created_at')->get();

// Requires composite index: active + created_at
$users = User::where('active', true)
    ->where('role', 'admin')
    ->orderBy('created_at')
    ->get();
```

### Pagination Best Practices
```php
// Efficient cursor-based pagination (Sprint 2)
$users = User::orderBy('created_at')
    ->startAfter($lastDocument)
    ->limit(20)
    ->get();

// Less efficient offset pagination
$users = User::orderBy('created_at')
    ->offset(100)
    ->limit(20)
    ->get();
```

## 🧪 Testing & Development

### Unit Testing
- **FirebaseMock**: Emulates Firestore behaviors for testing
- **Test Helpers**: Eloquent-compatible test assertions
- **Index Validation**: Tests validate query index requirements

### Development Tools
- **Query Debugging**: Built-in query logging and debugging
- **Index Suggestions**: Automatic composite index suggestions
- **Performance Monitoring**: Query performance tracking

## 🔄 Migration from Eloquent

### Code Changes Required
```php
// Before (Eloquent)
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    // Same code works!
}

// After (Firestore)
use JTD\FirebaseModels\Firestore\FirestoreModel;

class User extends FirestoreModel
{
    // Identical code - no changes needed!
}
```

### Data Migration
- **Schema-less**: No migrations needed for adding fields
- **Data Transformation**: Use Laravel commands for data migration
- **Relationship Mapping**: Convert foreign keys to document references

### Best Practices
- **Start Simple**: Begin with basic CRUD operations
- **Add Complexity Gradually**: Implement relationships and advanced features incrementally
- **Monitor Performance**: Use Firestore monitoring tools
- **Plan Indexes**: Design queries with indexing in mind

## 📈 Compatibility Matrix

| Feature | Eloquent | Firestore Models | Notes |
|---------|----------|------------------|-------|
| **Basic CRUD** | ✅ | ✅ | Identical API |
| **Mass Assignment** | ✅ | ✅ | Same protection |
| **Attribute Casting** | ✅ | ✅ | All types supported |
| **Model Events** | ✅ | ✅ | Complete lifecycle |
| **Query Builder** | ✅ | ✅ | Firestore constraints apply |
| **Pagination** | ✅ | ✅ | Offset + cursor support |
| **Relationships** | ✅ | 🔄 | Sprint 2 feature |
| **Soft Deletes** | ✅ | 🔄 | Sprint 2 feature |
| **Scopes** | ✅ | 🔄 | Sprint 2 feature |
| **Joins** | ✅ | ❌ | Not supported |
| **Raw Queries** | ✅ | ❌ | Not applicable |
| **Transactions** | ✅ | ✅ | Firestore semantics |

**Legend**: ✅ Supported | 🔄 Planned | ❌ Not Supported

