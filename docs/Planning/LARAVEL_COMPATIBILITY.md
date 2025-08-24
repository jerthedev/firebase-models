# Laravel 12 Database Compatibility Matrix

This document outlines the compatibility between Laravel 12's database features and JTD Firebase Models, helping you understand what works, what's adapted, and what's not supported due to Firestore's document-based architecture.

## 🎯 Full Compatibility (100%)

These Laravel 12 features work exactly as expected with identical syntax:

### Query Builder
```php
// All of these work exactly like Laravel
FirestoreDB::table('users')
    ->where('active', true)
    ->where('age', '>', 18)
    ->whereIn('role', ['admin', 'editor'])
    ->whereNotNull('email')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

| Laravel Method | Firestore Support | Notes |
|---------------|------------------|-------|
| `where()` | ✅ Full | Operators auto-converted (`=` → `==`) |
| `orWhere()` | ✅ Full | Native Firestore OR support |
| `whereIn()` | ✅ Full | Uses Firestore `in` operator |
| `whereNotIn()` | ✅ Full | Uses Firestore `not-in` operator |
| `whereNull()` | ✅ Full | Checks for `null` values |
| `whereNotNull()` | ✅ Full | Checks for non-`null` values |
| `orderBy()` | ✅ Full | Native Firestore ordering |
| `orderByDesc()` | ✅ Full | Descending order support |
| `limit()` / `take()` | ✅ Full | Native Firestore limit |
| `select()` | ✅ Full | Field selection after retrieval |
| `distinct()` | ✅ Full | Collection-based deduplication |

### Retrieving Results
```php
// Single results
$user = FirestoreDB::table('users')->where('email', $email)->first();
$user = FirestoreDB::table('users')->find($id);
$name = FirestoreDB::table('users')->where('id', $id)->value('name');

// Collections
$users = FirestoreDB::table('users')->get();
$names = FirestoreDB::table('users')->pluck('name');
$emails = FirestoreDB::table('users')->pluck('email', 'id');
```

| Laravel Method | Firestore Support | Notes |
|---------------|------------------|-------|
| `get()` | ✅ Full | Returns Laravel Collection |
| `first()` | ✅ Full | Single document retrieval |
| `firstOrFail()` | ✅ Full | Throws `RecordNotFoundException` |
| `find()` | ✅ Full | Direct document ID lookup |
| `value()` | ✅ Full | Single field value |
| `pluck()` | ✅ Full | Extract column values |
| `exists()` | ✅ Full | Check if documents exist |
| `doesntExist()` | ✅ Full | Check if no documents exist |

### Aggregates
```php
$count = FirestoreDB::table('users')->count();
$maxAge = FirestoreDB::table('users')->max('age');
$avgAge = FirestoreDB::table('users')->avg('age');
$totalSales = FirestoreDB::table('orders')->sum('amount');
```

| Laravel Method | Firestore Support | Notes |
|---------------|------------------|-------|
| `count()` | ✅ Full | Document counting |
| `max()` | ✅ Full | Collection-based calculation |
| `min()` | ✅ Full | Collection-based calculation |
| `avg()` | ✅ Full | Collection-based calculation |
| `sum()` | ✅ Full | Collection-based calculation |

### Pagination
```php
// Length-aware pagination (with total count)
$users = FirestoreDB::table('users')->paginate(15);

// Simple pagination (next/previous only)
$users = FirestoreDB::table('users')->simplePaginate(15);

// Custom pagination
$users = FirestoreDB::table('users')->paginate(
    $perPage = 20, 
    $columns = ['*'], 
    $pageName = 'users'
);
```

| Laravel Method | Firestore Support | Notes |
|---------------|------------------|-------|
| `paginate()` | ✅ Full | Length-aware with total count |
| `simplePaginate()` | ✅ Full | Simple next/previous pagination |
| `cursorPaginate()` | 🔄 Planned | Will use Firestore cursors |

### Chunking & Streaming
```php
// Process large datasets in chunks
FirestoreDB::table('users')->chunk(100, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});

// Stream results
FirestoreDB::table('users')->each(function ($user) {
    // Process each user
});
```

| Laravel Method | Firestore Support | Notes |
|---------------|------------------|-------|
| `chunk()` | ✅ Full | Batch processing |
| `each()` | ✅ Full | Individual item processing |
| `lazy()` | 🔄 Planned | Lazy collection streaming |

## 🔄 Adapted Features

These features work but with Firestore-specific adaptations:

### Insert Operations
```php
// Single insert
FirestoreDB::table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Multiple inserts (uses batch)
FirestoreDB::table('users')->insert([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
]);

// Insert and get auto-generated ID
$id = FirestoreDB::table('users')->insertGetId([
    'name' => 'John Doe'
]);
```

| Laravel Method | Firestore Adaptation | Notes |
|---------------|---------------------|-------|
| `insert()` | ✅ Adapted | Uses Firestore batch for multiple |
| `insertGetId()` | ✅ Adapted | Returns Firestore document ID |
| `insertOrIgnore()` | ❌ Not Supported | No equivalent in Firestore |
| `upsert()` | 🔄 Planned | Will use Firestore merge |

### Transactions
```php
// Laravel-style transactions
FirestoreDB::transaction(function ($transaction) {
    FirestoreDB::table('users')->insert(['name' => 'John']);
    FirestoreDB::table('posts')->insert(['title' => 'Hello']);
});
```

| Laravel Method | Firestore Adaptation | Notes |
|---------------|---------------------|-------|
| `transaction()` | ✅ Adapted | Uses Firestore transactions |
| `beginTransaction()` | ❌ Not Supported | Firestore requires closure-based |
| `commit()` | ❌ Not Supported | Automatic in Firestore |
| `rollBack()` | ❌ Not Supported | Automatic on exception |

### Raw Operations
```php
// Adapted raw operations
$users = FirestoreDB::select('users', [
    ['field' => 'active', 'operator' => '==', 'value' => true]
]);
```

| Laravel Method | Firestore Adaptation | Notes |
|---------------|---------------------|-------|
| `DB::select()` | ✅ Adapted | Uses constraint arrays |
| `DB::insert()` | ✅ Adapted | Collection-based insert |
| `DB::update()` | 🔄 Planned | Document update operations |
| `DB::delete()` | 🔄 Planned | Document delete operations |

## ❌ Not Supported (Firestore Limitations)

These Laravel features cannot be implemented due to Firestore's document-based nature:

### Joins
```php
// ❌ NOT SUPPORTED - Firestore doesn't support joins
FirestoreDB::table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->get();
```

**Alternative**: Use document references or denormalization
```php
// ✅ FIRESTORE WAY - Store references
$user = FirestoreDB::table('users')->find($userId);
$posts = FirestoreDB::table('posts')->where('user_id', $userId)->get();
```

### Subqueries
```php
// ❌ NOT SUPPORTED - No subquery support
FirestoreDB::table('users')
    ->whereIn('id', function ($query) {
        $query->select('user_id')->from('posts');
    })
    ->get();
```

**Alternative**: Use multiple queries or denormalization

### Advanced SQL Features
| Feature | Support | Alternative |
|---------|---------|-------------|
| `UNION` | ❌ No | Multiple queries + merge |
| `GROUP BY` | ❌ No | Collection grouping |
| `HAVING` | ❌ No | Collection filtering |
| `Window Functions` | ❌ No | Application-level calculation |
| `Foreign Keys` | ❌ No | Document references |
| `Indexes` (manual) | ❌ No | Firestore auto-indexing |

## 🔄 Planned Features (Future Sprints)

### Migrations (Sprint 2)
```php
// Coming soon - Firestore schema management
Schema::collection('users', function (Blueprint $collection) {
    $collection->field('name')->string();
    $collection->field('email')->string()->unique();
    $collection->timestamps();
});
```

### Seeding (Sprint 2)
```php
// Coming soon - Firestore data seeding
class UserSeeder extends Seeder
{
    public function run()
    {
        FirestoreDB::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@example.com'
        ]);
    }
}
```

### Advanced Query Features (Sprint 3)
- Cursor-based pagination
- Collection group queries
- Real-time listeners
- Advanced indexing strategies

## 🎯 Best Practices

### 1. Use Firestore Strengths
```php
// ✅ Good - Leverage document structure
$user = FirestoreDB::table('users')->find($id);
$profile = $user->profile; // Nested object

// ❌ Avoid - Don't try to simulate joins
$users = FirestoreDB::table('users')->get();
foreach ($users as $user) {
    $user->posts = FirestoreDB::table('posts')->where('user_id', $user->id)->get();
}
```

### 2. Optimize for Document Reads
```php
// ✅ Good - Single document read
$user = FirestoreDB::table('users')
    ->where('email', $email)
    ->first();

// ✅ Good - Batch operations
FirestoreDB::table('users')->insert($multipleUsers);
```

### 3. Use Appropriate Pagination
```php
// ✅ Good for small datasets - Shows total count
$users = FirestoreDB::table('users')->paginate(15);

// ✅ Good for large datasets - Better performance
$users = FirestoreDB::table('users')->simplePaginate(15);
```

## 📊 Performance Considerations

| Operation | Firestore Cost | Recommendation |
|-----------|----------------|----------------|
| `count()` | High (reads all docs) | Use `exists()` when possible |
| `paginate()` | High (counts + reads) | Use `simplePaginate()` for large datasets |
| `max()`/`min()` | Medium (reads all docs) | Consider storing aggregates |
| `chunk()` | Low (batch reads) | Preferred for large datasets |
| `transaction()` | Medium | Use for consistency requirements |

## 🔍 Migration Guide

### From Laravel Eloquent
```php
// Laravel Eloquent
User::where('active', true)->paginate(15);

// JTD Firebase Models
FirestoreDB::table('users')->where('active', true)->paginate(15);
```

### From Raw SQL
```php
// Laravel Raw SQL
DB::select('SELECT * FROM users WHERE active = ?', [true]);

// JTD Firebase Models
FirestoreDB::select('users', [
    ['field' => 'active', 'operator' => '==', 'value' => true]
]);
```

This compatibility matrix ensures you understand exactly what Laravel 12 features are available and how to use them effectively with Firestore!
