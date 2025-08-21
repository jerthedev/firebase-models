# Transactions Guide

Firebase Models provides a comprehensive transaction system that ensures data consistency and handles concurrent operations safely in Firestore. This guide covers everything from basic usage to advanced patterns.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [Model-Level Transactions](#model-level-transactions)
- [Advanced Transaction Patterns](#advanced-transaction-patterns)
- [Batch Operations](#batch-operations)
- [Error Handling and Retry Logic](#error-handling-and-retry-logic)
- [Performance Optimization](#performance-optimization)
- [Best Practices](#best-practices)
- [Testing Transactions](#testing-transactions)

## Overview

Transactions in Firestore ensure that a set of operations either all succeed or all fail, maintaining data consistency even under concurrent access. Firebase Models provides multiple ways to work with transactions:

- **Database-level transactions** using `FirestoreDB::transaction()`
- **Model-level transactions** using traits like `HasTransactions`
- **Advanced transaction management** with `TransactionManager`
- **Batch operations** for bulk data operations

### Key Features

- **ACID compliance** - Atomicity, Consistency, Isolation, Durability
- **Automatic retry logic** with exponential backoff
- **Conflict detection and resolution**
- **Performance monitoring** and detailed result tracking
- **Laravel-style API** familiar to Laravel developers

## Basic Usage

### Simple Database Transaction

```php
use JTD\FirebaseModels\Facades\FirestoreDB;

// Basic transaction
$result = FirestoreDB::transaction(function ($transaction) {
    // Read data
    $userRef = FirestoreDB::collection('users')->document('user-123');
    $userSnapshot = $transaction->snapshot($userRef);
    
    if (!$userSnapshot->exists()) {
        throw new Exception('User not found');
    }
    
    $userData = $userSnapshot->data();
    
    // Update data
    $transaction->update($userRef, [
        'balance' => $userData['balance'] + 100,
        'updated_at' => now(),
    ]);
    
    return $userData['balance'] + 100;
});

echo "New balance: {$result}";
```

### Transaction with Retry Logic

```php
// Transaction with custom retry attempts
$result = FirestoreDB::transactionWithRetry(function ($transaction) {
    // Your transaction logic here
    return $this->transferFunds($transaction, $fromUser, $toUser, $amount);
}, 5); // Retry up to 5 times

// Transaction with detailed result
$result = FirestoreDB::transactionWithResult(function ($transaction) {
    // Your transaction logic
    return $this->processOrder($transaction, $orderId);
});

if ($result->isSuccess()) {
    echo "Transaction completed in {$result->getDurationMs()}ms";
    echo "Attempts: {$result->getAttempts()}";
} else {
    echo "Transaction failed: {$result->getError()}";
}
```

## Model-Level Transactions

### Using HasTransactions Trait

Models automatically include the `HasTransactions` trait, providing convenient transaction methods:

```php
use App\Models\User;
use App\Models\Order;

// Save model in transaction
$user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
$user->saveInTransaction();

// Delete model in transaction
$user->deleteInTransaction();

// Conditional update with conflict detection
$user = User::find('user-123');
$success = $user->conditionalUpdate(
    ['status' => 'pending'], // Current expected values
    ['status' => 'active', 'activated_at' => now()] // New values
);

if ($success) {
    echo "User activated successfully";
} else {
    echo "Update failed - user status was modified by another process";
}
```

### Atomic Operations

```php
// Atomic increment
$post = Post::find('post-456');
$post->incrementInTransaction('views', 1);
$post->incrementInTransaction('likes', 1, ['last_viewed_at' => now()]);

// Atomic decrement
$user->decrementInTransaction('credits', 10);

// Bulk operations in transaction
$users = User::createManyInTransaction([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);

$userIds = ['user-1', 'user-2', 'user-3'];
User::deleteManyInTransaction($userIds);
```

## Advanced Transaction Patterns

### Transaction Builder Pattern

```php
use JTD\FirebaseModels\Firestore\Transactions\TransactionManager;

// Complex transaction with builder pattern
$result = TransactionManager::builder()
    ->create('orders', [
        'user_id' => 'user-123',
        'total' => 99.99,
        'status' => 'pending'
    ])
    ->update('users', 'user-123', [
        'last_order_at' => now(),
        'total_orders' => FirestoreDB::increment(1)
    ])
    ->when('products', 'product-456', ['stock' => 10]) // Conditional check
    ->update('products', 'product-456', [
        'stock' => FirestoreDB::increment(-1)
    ])
    ->withRetry(3, 200) // 3 attempts, 200ms delay
    ->withTimeout(30) // 30 second timeout
    ->executeWithResult();

if ($result->isSuccess()) {
    $data = $result->getData();
    echo "Order created with ID: {$data[0]}"; // First operation result
}
```

### Complex Business Logic

```php
class OrderService
{
    public function processOrder(string $userId, array $items): array
    {
        return TransactionManager::execute(function ($transaction) use ($userId, $items) {
            // 1. Validate user
            $userRef = FirestoreDB::collection('users')->document($userId);
            $userSnapshot = $transaction->snapshot($userRef);
            
            if (!$userSnapshot->exists()) {
                throw new Exception('User not found');
            }
            
            $user = $userSnapshot->data();
            
            // 2. Calculate total and validate inventory
            $total = 0;
            $updates = [];
            
            foreach ($items as $item) {
                $productRef = FirestoreDB::collection('products')->document($item['product_id']);
                $productSnapshot = $transaction->snapshot($productRef);
                
                if (!$productSnapshot->exists()) {
                    throw new Exception("Product {$item['product_id']} not found");
                }
                
                $product = $productSnapshot->data();
                
                if ($product['stock'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for {$product['name']}");
                }
                
                $total += $product['price'] * $item['quantity'];
                $updates[] = [
                    'ref' => $productRef,
                    'data' => ['stock' => $product['stock'] - $item['quantity']]
                ];
            }
            
            // 3. Check user balance
            if ($user['balance'] < $total) {
                throw new Exception('Insufficient balance');
            }
            
            // 4. Create order
            $orderRef = FirestoreDB::collection('orders')->newDocument();
            $transaction->set($orderRef, [
                'user_id' => $userId,
                'items' => $items,
                'total' => $total,
                'status' => 'confirmed',
                'created_at' => now(),
            ]);
            
            // 5. Update user balance
            $transaction->update($userRef, [
                'balance' => $user['balance'] - $total,
                'last_order_at' => now(),
            ]);
            
            // 6. Update product stock
            foreach ($updates as $update) {
                $transaction->update($update['ref'], $update['data']);
            }
            
            return [
                'order_id' => $orderRef->id(),
                'total' => $total,
                'new_balance' => $user['balance'] - $total,
            ];
        });
    }
}
```

## Batch Operations

### Basic Batch Operations

```php
use JTD\FirebaseModels\Firestore\Batch\BatchManager;

// Bulk insert
$documents = [
    ['name' => 'Product 1', 'price' => 19.99],
    ['name' => 'Product 2', 'price' => 29.99],
    ['name' => 'Product 3', 'price' => 39.99],
];

$result = BatchManager::bulkInsert('products', $documents);

if ($result->isSuccess()) {
    echo "Inserted {$result->getOperationCount()} products";
    echo "Duration: {$result->getDurationMs()}ms";
}

// Bulk update
$updates = [
    'product-1' => ['price' => 24.99, 'updated_at' => now()],
    'product-2' => ['price' => 34.99, 'updated_at' => now()],
];

BatchManager::bulkUpdate('products', $updates);

// Bulk delete
$productIds = ['product-1', 'product-2', 'product-3'];
BatchManager::bulkDelete('products', $productIds);
```

### Advanced Batch Operations

```php
// Batch operation builder
$result = BatchManager::create()
    ->create('categories', ['name' => 'Electronics'])
    ->createMany('products', [
        ['name' => 'Laptop', 'category_id' => 'electronics'],
        ['name' => 'Phone', 'category_id' => 'electronics'],
    ])
    ->update('stats', 'global', [
        'total_products' => FirestoreDB::increment(2),
        'last_updated' => now(),
    ])
    ->withOptions([
        'chunk_size' => 50,
        'validate_operations' => true,
    ])
    ->execute();

// Model-level batch operations
$users = User::createManyInBatch([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob', 'email' => 'bob@example.com'],
]);

// Batch with custom logic
User::bulkOperation(function ($batch) {
    $users = User::where('status', 'pending')->get();
    
    foreach ($users as $user) {
        $user->status = 'active';
        $user->activated_at = now();
        $user->addToBatch($batch);
    }
});
```

## Error Handling and Retry Logic

### Automatic Retry with Exponential Backoff

```php
// Configure retry behavior
$result = TransactionManager::executeWithRetry(function ($transaction) {
    // Your transaction logic that might fail due to conflicts
    return $this->updateCounters($transaction);
}, 5, [
    'retry_delay' => 100, // Start with 100ms
    'max_delay' => 5000,  // Cap at 5 seconds
    'backoff_multiplier' => 2, // Double delay each retry
]);
```

### Custom Error Handling

```php
use JTD\FirebaseModels\Firestore\Transactions\Exceptions\TransactionException;
use JTD\FirebaseModels\Firestore\Transactions\Exceptions\TransactionRetryException;

try {
    $result = TransactionManager::execute(function ($transaction) {
        // Transaction logic
    });
} catch (TransactionRetryException $e) {
    // All retry attempts failed
    Log::error('Transaction failed after retries', [
        'attempts' => $e->getAttempts(),
        'errors' => $e->getAttemptErrors(),
        'last_error' => $e->getLastAttemptError(),
    ]);
    
    // Maybe queue for later processing
    ProcessTransactionJob::dispatch($transactionData)->delay(now()->addMinutes(5));
    
} catch (TransactionException $e) {
    // Single attempt failed
    Log::error('Transaction failed', [
        'error' => $e->getMessage(),
        'context' => $e->getContext(),
    ]);
}
```

### Graceful Degradation

```php
class UserService
{
    public function updateUserStats(string $userId): bool
    {
        try {
            // Try transaction first
            return $this->updateStatsInTransaction($userId);
        } catch (TransactionException $e) {
            Log::warning('Transaction failed, falling back to eventual consistency', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            // Fall back to eventual consistency
            return $this->updateStatsEventually($userId);
        }
    }
    
    private function updateStatsInTransaction(string $userId): bool
    {
        return TransactionManager::execute(function ($transaction) use ($userId) {
            // Atomic update logic
        });
    }
    
    private function updateStatsEventually(string $userId): bool
    {
        // Queue job for later processing
        UpdateUserStatsJob::dispatch($userId);
        return true;
    }
}
```

## Performance Optimization

### Transaction Sizing

```php
// Good: Small, focused transactions
TransactionManager::execute(function ($transaction) {
    // Update 1-2 documents
    $transaction->update($userRef, $userData);
    $transaction->update($orderRef, $orderData);
});

// Avoid: Large transactions with many operations
// These are more likely to conflict and retry
```

### Batch vs Transaction Trade-offs

```php
// Use transactions for:
// - Operations that must be atomic
// - Operations involving reads and conditional writes
// - Critical business logic requiring consistency

// Use batch operations for:
// - Bulk data operations
// - Operations that don't require reads
// - Performance-critical bulk updates

// Example: Use batch for bulk imports
BatchManager::bulkInsert('products', $thousandsOfProducts);

// Example: Use transaction for order processing
TransactionManager::execute(function ($transaction) {
    // Read inventory, update stock, create order
});
```

### Monitoring Performance

```php
// Monitor transaction performance
$result = TransactionManager::executeWithResult(function ($transaction) {
    // Your logic
});

$metrics = $result->getPerformanceMetrics();
Log::info('Transaction performance', [
    'duration_ms' => $metrics['duration_ms'],
    'attempts' => $metrics['attempts'],
    'operations_per_second' => $metrics['operations_per_second'],
]);

// Set up alerts for slow transactions
if ($metrics['duration_ms'] > 5000) {
    Alert::send('Slow transaction detected', $metrics);
}
```

## Best Practices

### 1. Keep Transactions Small and Fast

```php
// Good: Focused transaction
TransactionManager::execute(function ($transaction) {
    $userRef = FirestoreDB::collection('users')->document($userId);
    $userSnapshot = $transaction->snapshot($userRef);
    
    $transaction->update($userRef, [
        'last_login' => now(),
        'login_count' => $userSnapshot->data()['login_count'] + 1,
    ]);
});

// Avoid: Large, complex transactions
// These increase conflict probability and retry overhead
```

### 2. Handle Conflicts Gracefully

```php
// Use conditional updates for conflict-sensitive operations
$success = $user->conditionalUpdate(
    ['version' => $expectedVersion],
    ['status' => 'updated', 'version' => $expectedVersion + 1]
);

if (!$success) {
    // Handle conflict - maybe refresh and retry
    $user->refresh();
    // Retry logic or user notification
}
```

### 3. Use Appropriate Isolation Levels

```php
// For read-heavy operations, consider eventual consistency
$stats = Cache::remember('user_stats', 300, function () {
    return User::selectRaw('COUNT(*) as total, AVG(age) as avg_age')->first();
});

// For critical operations, use transactions
TransactionManager::execute(function ($transaction) {
    // Ensure consistency for financial operations
});
```

### 4. Implement Proper Logging

```php
// Log transaction attempts and outcomes
TransactionManager::setDefaultOptions([
    'log_attempts' => true,
    'log_level' => 'info',
]);

// Custom transaction logging
$result = TransactionManager::executeWithResult(function ($transaction) {
    // Your logic
});

Log::info('Transaction completed', [
    'success' => $result->isSuccess(),
    'duration' => $result->getDurationMs(),
    'attempts' => $result->getAttempts(),
    'operation' => 'user_update',
]);
```

## Testing Transactions

### Unit Testing

```php
use JTD\FirebaseModels\Testing\TransactionTestHelper;

class TransactionTest extends TestCase
{
    public function test_user_balance_update_transaction()
    {
        // Create test user
        $user = User::create(['name' => 'Test User', 'balance' => 100]);
        
        // Test transaction
        $result = TransactionManager::executeWithResult(function ($transaction) use ($user) {
            return $this->updateUserBalance($transaction, $user->id, 50);
        });
        
        // Assert transaction success
        TransactionTestHelper::assertTransactionSuccess($result);
        
        // Assert data consistency
        $user->refresh();
        $this->assertEquals(150, $user->balance);
    }
    
    public function test_transaction_conflict_handling()
    {
        $user = User::create(['name' => 'Test User', 'balance' => 100]);
        
        // Simulate concurrent updates
        $results = TransactionTestHelper::simulateConcurrentTransactions([
            function () use ($user) {
                return $user->incrementInTransaction('balance', 10);
            },
            function () use ($user) {
                return $user->incrementInTransaction('balance', 20);
            },
        ]);
        
        // Assert both transactions eventually succeed
        $this->assertTrue($results[0]->isSuccess() || $results[1]->isSuccess());
        
        // Assert final balance is correct
        $user->refresh();
        $this->assertEquals(130, $user->balance);
    }
}
```

### Integration Testing

```php
class OrderProcessingTest extends TestCase
{
    public function test_complete_order_flow()
    {
        // Setup test data
        $user = User::create(['balance' => 1000]);
        $product = Product::create(['stock' => 10, 'price' => 99.99]);
        
        // Test order processing transaction
        $orderService = new OrderService();
        $result = $orderService->processOrder($user->id, [
            ['product_id' => $product->id, 'quantity' => 2]
        ]);
        
        // Assert order was created
        $this->assertArrayHasKey('order_id', $result);
        
        // Assert user balance was updated
        $user->refresh();
        $this->assertEquals(800.02, $user->balance);
        
        // Assert product stock was updated
        $product->refresh();
        $this->assertEquals(8, $product->stock);
        
        // Assert order exists
        $order = Order::find($result['order_id']);
        $this->assertNotNull($order);
        $this->assertEquals('confirmed', $order->status);
    }
}
```

For more information, see:
- [Sync Mode Guide](sync-mode.md)
- [Conflict Resolution Guide](conflict-resolution.md)
- [Performance Optimization](performance.md)
