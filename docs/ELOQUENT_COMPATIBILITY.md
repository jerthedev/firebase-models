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

