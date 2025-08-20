# Facades Guide

The Firebase Models package provides Laravel-style facades for interacting with Firebase services. This guide covers the available facades and their usage.

## Table of Contents

- [FirestoreDB Facade](#firestoredb-facade)
- [Basic Operations](#basic-operations)
- [Collections & Documents](#collections--documents)
- [Queries](#queries)
- [Transactions](#transactions)
- [Batch Operations](#batch-operations)
- [Advanced Usage](#advanced-usage)

## FirestoreDB Facade

The `FirestoreDB` facade provides a Laravel-style interface for Firestore operations.

### Import

```php
use JTD\FirebaseModels\Facades\FirestoreDB;
```

### Basic Usage

```php
// Get a collection reference
$collection = FirestoreDB::collection('posts');

// Get a document reference
$document = FirestoreDB::collection('posts')->document('post-123');

// Get the underlying Firestore client
$firestore = FirestoreDB::database();
```

## Basic Operations

### Document Operations

```php
// Create a document with auto-generated ID
$docRef = FirestoreDB::collection('posts')->add([
    'title' => 'My Post',
    'content' => 'Post content here',
    'published' => true,
    'created_at' => now()
]);

echo "Document created with ID: " . $docRef->id();

// Create a document with specific ID
FirestoreDB::collection('posts')->document('custom-id')->set([
    'title' => 'Custom ID Post',
    'content' => 'Content here',
    'published' => false
]);

// Update a document
FirestoreDB::collection('posts')->document('post-123')->update([
    'title' => 'Updated Title',
    'updated_at' => now()
]);

// Delete a document
FirestoreDB::collection('posts')->document('post-123')->delete();
```

### Reading Documents

```php
// Get a single document
$snapshot = FirestoreDB::collection('posts')->document('post-123')->snapshot();

if ($snapshot->exists()) {
    $data = $snapshot->data();
    echo "Title: " . $data['title'];
} else {
    echo "Document not found";
}

// Get multiple documents
$documents = FirestoreDB::collection('posts')->documents();

foreach ($documents as $document) {
    if ($document->exists()) {
        $data = $document->data();
        echo "Post: " . $data['title'] . "\n";
    }
}
```

## Collections & Documents

### Collection References

```php
// Get collection reference
$postsCollection = FirestoreDB::collection('posts');

// Nested collections
$commentsCollection = FirestoreDB::collection('posts')
    ->document('post-123')
    ->collection('comments');

// Collection group queries (all comments across all posts)
$allComments = FirestoreDB::collectionGroup('comments');
```

### Document References

```php
// Get document reference
$postDoc = FirestoreDB::collection('posts')->document('post-123');

// Auto-generate document ID
$newPostDoc = FirestoreDB::collection('posts')->newDocument();
echo "New document ID: " . $newPostDoc->id();

// Document path
echo $postDoc->path(); // "posts/post-123"
echo $postDoc->id();   // "post-123"
```

## Queries

### Basic Queries

```php
// Simple where clause
$query = FirestoreDB::collection('posts')
    ->where('published', '==', true);

$documents = $query->documents();

// Multiple where clauses
$query = FirestoreDB::collection('posts')
    ->where('published', '==', true)
    ->where('author_id', '==', 'user-123');

// Where with different operators
$query = FirestoreDB::collection('posts')
    ->where('views', '>', 100)
    ->where('rating', '>=', 4.0);
```

### Array Queries

```php
// Array contains
$query = FirestoreDB::collection('posts')
    ->where('tags', 'array-contains', 'laravel');

// Array contains any
$query = FirestoreDB::collection('posts')
    ->where('tags', 'array-contains-any', ['laravel', 'php', 'firebase']);

// In queries
$query = FirestoreDB::collection('posts')
    ->where('category_id', 'in', [1, 2, 3]);

// Not in queries
$query = FirestoreDB::collection('posts')
    ->where('status', 'not-in', ['draft', 'archived']);
```

### Ordering and Limiting

```php
// Order by
$query = FirestoreDB::collection('posts')
    ->orderBy('created_at', 'desc')
    ->orderBy('title', 'asc');

// Limit results
$query = FirestoreDB::collection('posts')
    ->orderBy('created_at', 'desc')
    ->limit(10);

// Offset (use with caution - prefer cursor pagination)
$query = FirestoreDB::collection('posts')
    ->orderBy('created_at', 'desc')
    ->offset(20)
    ->limit(10);
```

### Cursor Pagination

```php
// Start after a document
$lastDoc = $previousPageLastDocument;
$query = FirestoreDB::collection('posts')
    ->orderBy('created_at', 'desc')
    ->startAfter($lastDoc)
    ->limit(10);

// Start at a document
$query = FirestoreDB::collection('posts')
    ->orderBy('created_at', 'desc')
    ->startAt($someDocument)
    ->limit(10);

// End before/at a document
$query = FirestoreDB::collection('posts')
    ->orderBy('created_at', 'desc')
    ->endBefore($someDocument)
    ->limit(10);
```

### Real-time Listeners

```php
// Listen to document changes
$docRef = FirestoreDB::collection('posts')->document('post-123');

$listener = $docRef->onSnapshot(function ($snapshot) {
    if ($snapshot->exists()) {
        $data = $snapshot->data();
        echo "Document updated: " . $data['title'];
    } else {
        echo "Document deleted";
    }
});

// Listen to query changes
$query = FirestoreDB::collection('posts')->where('published', '==', true);

$listener = $query->onSnapshot(function ($snapshot) {
    foreach ($snapshot->documentChanges() as $change) {
        $doc = $change->document();
        
        switch ($change->type()) {
            case 'added':
                echo "New post: " . $doc->data()['title'];
                break;
            case 'modified':
                echo "Updated post: " . $doc->data()['title'];
                break;
            case 'removed':
                echo "Deleted post: " . $doc->id();
                break;
        }
    }
});

// Stop listening
$listener->stop();
```

## Transactions

### Basic Transactions

```php
$result = FirestoreDB::runTransaction(function ($transaction) {
    // Read operations first
    $postRef = FirestoreDB::collection('posts')->document('post-123');
    $postSnapshot = $transaction->snapshot($postRef);
    
    if (!$postSnapshot->exists()) {
        throw new Exception('Post not found');
    }
    
    $currentViews = $postSnapshot->data()['views'] ?? 0;
    
    // Write operations
    $transaction->update($postRef, [
        'views' => $currentViews + 1,
        'updated_at' => now()
    ]);
    
    return $currentViews + 1;
});

echo "Post views updated to: " . $result;
```

### Complex Transactions

```php
FirestoreDB::runTransaction(function ($transaction) {
    // Transfer points between users
    $fromUserRef = FirestoreDB::collection('users')->document('user-1');
    $toUserRef = FirestoreDB::collection('users')->document('user-2');
    
    $fromUserSnapshot = $transaction->snapshot($fromUserRef);
    $toUserSnapshot = $transaction->snapshot($toUserRef);
    
    $fromPoints = $fromUserSnapshot->data()['points'];
    $toPoints = $toUserSnapshot->data()['points'];
    
    if ($fromPoints < 100) {
        throw new Exception('Insufficient points');
    }
    
    $transaction->update($fromUserRef, ['points' => $fromPoints - 100]);
    $transaction->update($toUserRef, ['points' => $toPoints + 100]);
    
    // Log the transaction
    $transaction->create(FirestoreDB::collection('transactions')->newDocument(), [
        'from_user' => 'user-1',
        'to_user' => 'user-2',
        'amount' => 100,
        'timestamp' => now()
    ]);
});
```

## Batch Operations

### Batch Writes

```php
$batch = FirestoreDB::batch();

// Add multiple operations to batch
$batch->create(FirestoreDB::collection('posts')->newDocument(), [
    'title' => 'Batch Post 1',
    'content' => 'Content 1'
]);

$batch->create(FirestoreDB::collection('posts')->newDocument(), [
    'title' => 'Batch Post 2',
    'content' => 'Content 2'
]);

$batch->update(FirestoreDB::collection('posts')->document('existing-post'), [
    'updated_at' => now()
]);

$batch->delete(FirestoreDB::collection('posts')->document('old-post'));

// Commit all operations atomically
$batch->commit();
```

### Bulk Operations

```php
// Bulk create posts
$batch = FirestoreDB::batch();

$posts = [
    ['title' => 'Post 1', 'content' => 'Content 1'],
    ['title' => 'Post 2', 'content' => 'Content 2'],
    ['title' => 'Post 3', 'content' => 'Content 3'],
];

foreach ($posts as $postData) {
    $batch->create(
        FirestoreDB::collection('posts')->newDocument(),
        $postData
    );
}

$batch->commit();

// Bulk update
$batch = FirestoreDB::batch();

$postIds = ['post-1', 'post-2', 'post-3'];

foreach ($postIds as $postId) {
    $batch->update(
        FirestoreDB::collection('posts')->document($postId),
        ['updated_at' => now()]
    );
}

$batch->commit();
```

## Advanced Usage

### Field Transforms

```php
// Server timestamp
FirestoreDB::collection('posts')->document('post-123')->update([
    'updated_at' => FirestoreDB::fieldValue()->serverTimestamp()
]);

// Increment/decrement
FirestoreDB::collection('posts')->document('post-123')->update([
    'views' => FirestoreDB::fieldValue()->increment(1),
    'likes' => FirestoreDB::fieldValue()->increment(5)
]);

// Array operations
FirestoreDB::collection('posts')->document('post-123')->update([
    'tags' => FirestoreDB::fieldValue()->arrayUnion(['new-tag']),
    'old_tags' => FirestoreDB::fieldValue()->arrayRemove(['old-tag'])
]);

// Delete field
FirestoreDB::collection('posts')->document('post-123')->update([
    'temporary_field' => FirestoreDB::fieldValue()->deleteField()
]);
```

### Subcollections

```php
// Work with subcollections
$commentsRef = FirestoreDB::collection('posts')
    ->document('post-123')
    ->collection('comments');

// Add comment
$commentRef = $commentsRef->add([
    'author' => 'user-456',
    'content' => 'Great post!',
    'created_at' => now()
]);

// Query comments
$comments = $commentsRef
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->documents();
```

### Error Handling

```php
try {
    $result = FirestoreDB::collection('posts')->document('post-123')->snapshot();
    
    if ($result->exists()) {
        $data = $result->data();
    } else {
        throw new Exception('Document not found');
    }
} catch (Google\Cloud\Core\Exception\NotFoundException $e) {
    echo "Document not found: " . $e->getMessage();
} catch (Google\Cloud\Core\Exception\ServiceException $e) {
    echo "Firestore error: " . $e->getMessage();
} catch (Exception $e) {
    echo "General error: " . $e->getMessage();
}
```

### Performance Tips

```php
// Use select() to limit fields
$query = FirestoreDB::collection('posts')
    ->select(['title', 'created_at'])
    ->where('published', '==', true);

// Use limit() to control result size
$query = FirestoreDB::collection('posts')
    ->orderBy('created_at', 'desc')
    ->limit(20);

// Use cursor pagination for large datasets
$query = FirestoreDB::collection('posts')
    ->orderBy('created_at', 'desc')
    ->startAfter($lastDocument)
    ->limit(20);

// Batch reads for multiple documents
$documentRefs = [
    FirestoreDB::collection('posts')->document('post-1'),
    FirestoreDB::collection('posts')->document('post-2'),
    FirestoreDB::collection('posts')->document('post-3'),
];

$documents = FirestoreDB::getAll($documentRefs);
```
