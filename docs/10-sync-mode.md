# Sync Mode Guide

Firebase Models provides a powerful sync mode that allows you to synchronize data between Firestore and your local database. This enables offline capabilities, improved performance, and hybrid cloud-local architectures.

## Table of Contents

- [Overview](#overview)
- [Configuration](#configuration)
- [Setting Up Sync Mode](#setting-up-sync-mode)
- [Sync Strategies](#sync-strategies)
- [Model Configuration](#model-configuration)
- [Running Sync Operations](#running-sync-operations)
- [Monitoring and Logging](#monitoring-and-logging)
- [Best Practices](#best-practices)
- [Troubleshooting](#troubleshooting)

## Overview

Sync mode enables bidirectional synchronization between Firestore (cloud) and your local database. This allows your application to:

- **Work offline** with local data
- **Improve performance** by serving data from local database
- **Reduce costs** by minimizing Firestore reads
- **Enable hybrid architectures** with both cloud and local data sources

### Sync Flow

```
Firestore (Cloud) ←→ Sync Manager ←→ Local Database
```

The Sync Manager handles:
- Detecting changes in both directions
- Resolving conflicts using configurable policies
- Maintaining data consistency
- Scheduling automatic sync operations

## Configuration

### Basic Configuration

Add sync configuration to your `config/firebase-models.php`:

```php
<?php

return [
    // ... existing configuration

    'sync' => [
        'enabled' => env('FIREBASE_SYNC_ENABLED', false),
        'mode' => env('FIREBASE_SYNC_MODE', 'one_way'), // 'one_way', 'two_way'
        'direction' => env('FIREBASE_SYNC_DIRECTION', 'cloud_to_local'), // 'cloud_to_local', 'local_to_cloud'
        
        'schedule' => [
            'enabled' => true,
            'frequency' => '*/5 * * * *', // Every 5 minutes
            'collections' => ['users', 'posts', 'comments'],
        ],
        
        'conflict_resolution' => [
            'policy' => 'last_write_wins', // 'last_write_wins', 'manual', 'cloud_wins', 'local_wins'
            'timestamp_field' => 'updated_at',
            'version_field' => 'version',
        ],
        
        'local_database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'table_prefix' => 'firebase_sync_',
        ],
        
        'performance' => [
            'batch_size' => 100,
            'max_concurrent_syncs' => 3,
            'timeout' => 300, // 5 minutes
        ],
    ],
];
```

### Environment Variables

Add these to your `.env` file:

```env
FIREBASE_SYNC_ENABLED=true
FIREBASE_SYNC_MODE=one_way
FIREBASE_SYNC_DIRECTION=cloud_to_local

# Optional: Custom sync settings
FIREBASE_SYNC_BATCH_SIZE=100
FIREBASE_SYNC_TIMEOUT=300
```

## Setting Up Sync Mode

### 1. Enable Sync in Configuration

```php
// config/firebase-models.php
'sync' => [
    'enabled' => true,
    'mode' => 'one_way',
    'direction' => 'cloud_to_local',
],
```

### 2. Create Local Database Tables

Generate migrations for your synced collections:

```bash
php artisan firebase:sync:make-migration users
php artisan firebase:sync:make-migration posts
php artisan migrate
```

Or create them manually:

```php
// database/migrations/create_firebase_sync_users_table.php
Schema::create('firebase_sync_users', function (Blueprint $table) {
    $table->string('firebase_id')->primary();
    $table->string('name');
    $table->string('email');
    $table->timestamp('firebase_created_at');
    $table->timestamp('firebase_updated_at');
    $table->timestamp('synced_at');
    $table->json('firebase_data'); // Full Firestore document
    $table->timestamps();
    
    $table->index(['firebase_updated_at']);
    $table->index(['synced_at']);
});
```

### 3. Configure Models for Sync

```php
<?php

namespace App\Models;

use JTD\FirebaseModels\Firestore\FirestoreModel;

class User extends FirestoreModel
{
    protected $collection = 'users';
    
    // Enable sync mode
    protected $syncEnabled = true;
    
    // Local database configuration
    protected $syncConnection = 'mysql';
    protected $syncTable = 'firebase_sync_users';
    
    // Sync field mappings
    protected $syncFieldMap = [
        'id' => 'firebase_id',
        'created_at' => 'firebase_created_at',
        'updated_at' => 'firebase_updated_at',
    ];
    
    // Fields to sync
    protected $syncFields = ['name', 'email', 'profile'];
    
    // Conflict resolution
    protected $syncConflictResolution = 'last_write_wins';
}
```

## Sync Strategies

### One-Way Sync (Cloud to Local)

Best for read-heavy applications where Firestore is the source of truth:

```php
'sync' => [
    'mode' => 'one_way',
    'direction' => 'cloud_to_local',
],
```

**Use Cases:**
- Content management systems
- Product catalogs
- Reference data
- Analytics dashboards

### One-Way Sync (Local to Cloud)

Best for applications that primarily work with local data:

```php
'sync' => [
    'mode' => 'one_way',
    'direction' => 'local_to_cloud',
],
```

**Use Cases:**
- Data collection apps
- Offline-first applications
- Batch processing systems

### Two-Way Sync

Best for collaborative applications with multiple data sources:

```php
'sync' => [
    'mode' => 'two_way',
    'conflict_resolution' => [
        'policy' => 'last_write_wins',
    ],
],
```

**Use Cases:**
- Collaborative editing
- Multi-user applications
- Distributed systems

## Model Configuration

### Per-Model Sync Configuration

You can control sync behavior at the model level using the `$syncEnabled` property. This allows you to enable sync globally while disabling it for specific models, or vice versa.

```php
<?php

class Post extends FirestoreModel
{
    protected $collection = 'posts';

    // Per-model sync configuration
    protected ?bool $syncEnabled = true;  // Force enable sync for this model
    // protected ?bool $syncEnabled = false; // Force disable sync for this model
    // protected ?bool $syncEnabled = null;  // Use global configuration (default)

    protected $fillable = [
        'title', 'content', 'author_id', 'status'
    ];
}
```

**Sync Configuration Options:**

- `null` (default): Use the global sync configuration from `config('firebase-models.mode')`
- `true`: Force enable sync for this model, even if global sync is disabled
- `false`: Force disable sync for this model, even if global sync is enabled

### Use Cases for Per-Model Sync

**Disable Sync for Temporary Data:**
```php
class TempUpload extends FirestoreModel
{
    protected $collection = 'temp_uploads';
    protected ?bool $syncEnabled = false; // Don't sync temporary files
}
```

**Enable Sync for Critical Data in Cloud Mode:**
```php
class AuditLog extends FirestoreModel
{
    protected $collection = 'audit_logs';
    protected ?bool $syncEnabled = true; // Always sync audit logs for compliance
}
```

**Model Inheritance:**
```php
class BaseModel extends FirestoreModel
{
    protected ?bool $syncEnabled = true; // Parent enables sync
}

class CachedModel extends BaseModel
{
    // Inherits $syncEnabled = true from parent
}

class VolatileModel extends BaseModel
{
    protected ?bool $syncEnabled = false; // Child overrides parent
}
```

### Basic Sync Model

```php
<?php

class Post extends FirestoreModel
{
    protected $collection = 'posts';
    protected ?bool $syncEnabled = true;

    protected $fillable = [
        'title', 'content', 'author_id', 'status'
    ];

    protected $syncFields = [
        'title', 'content', 'author_id', 'status', 'created_at', 'updated_at'
    ];
}
```

### Advanced Sync Configuration

```php
<?php

class User extends FirestoreModel
{
    protected $collection = 'users';
    protected $syncEnabled = true;
    
    // Custom sync table
    protected $syncTable = 'user_sync_cache';
    
    // Custom field mappings
    protected $syncFieldMap = [
        'id' => 'user_firebase_id',
        'email' => 'user_email',
        'profile.name' => 'full_name', // Nested field mapping
    ];
    
    // Sync transformations
    protected $syncTransformers = [
        'profile' => 'json', // Store as JSON
        'tags' => 'comma_separated', // Array to comma-separated string
    ];
    
    // Custom conflict resolution
    protected $syncConflictResolution = 'manual';
    
    // Sync hooks
    protected function beforeSync(array $data): array
    {
        // Transform data before syncing
        $data['full_name'] = $data['first_name'] . ' ' . $data['last_name'];
        return $data;
    }
    
    protected function afterSync(array $data): void
    {
        // Perform actions after sync
        $this->updateSearchIndex();
    }
}
```

## Running Sync Operations

### Manual Sync Commands

```bash
# Sync all collections
php artisan firebase:sync

# Sync specific collection
php artisan firebase:sync --collection=users

# Sync since specific timestamp
php artisan firebase:sync --since="2024-01-01 00:00:00"

# Dry run (show what would be synced)
php artisan firebase:sync --dry-run

# Force full sync (ignore timestamps)
php artisan firebase:sync --force

# Sync with verbose output
php artisan firebase:sync --verbose
```

### Programmatic Sync

```php
use JTD\FirebaseModels\Sync\SyncManager;

// Get sync manager instance
$syncManager = app(SyncManager::class);

// Sync all collections
$result = $syncManager->syncAll();

// Sync specific collection
$result = $syncManager->syncCollection('users');

// Sync with options
$result = $syncManager->syncCollection('posts', [
    'since' => now()->subHours(1),
    'batch_size' => 50,
    'direction' => 'cloud_to_local',
]);

// Check sync status
if ($result->isSuccess()) {
    echo "Synced {$result->getSyncedCount()} records";
} else {
    echo "Sync failed: {$result->getError()}";
}
```

### Scheduled Sync

Add to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Sync every 5 minutes
    $schedule->command('firebase:sync')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->runInBackground();
    
    // Sync specific collections at different intervals
    $schedule->command('firebase:sync --collection=users')
        ->hourly();
        
    $schedule->command('firebase:sync --collection=posts')
        ->everyTenMinutes();
}
```

## Monitoring and Logging

### Sync Status Monitoring

```php
use JTD\FirebaseModels\Sync\SyncStatus;

// Get overall sync status
$status = SyncStatus::overall();
echo "Last sync: {$status->lastSyncAt}";
echo "Status: {$status->status}";

// Get collection-specific status
$userSyncStatus = SyncStatus::forCollection('users');
echo "Users synced: {$userSyncStatus->recordCount}";
echo "Last error: {$userSyncStatus->lastError}";

// Get sync metrics
$metrics = SyncStatus::metrics();
echo "Total syncs today: {$metrics->syncsToday}";
echo "Average sync time: {$metrics->averageSyncTime}ms";
```

### Logging Configuration

```php
// config/logging.php
'channels' => [
    'firebase_sync' => [
        'driver' => 'daily',
        'path' => storage_path('logs/firebase-sync.log'),
        'level' => 'info',
        'days' => 14,
    ],
],
```

### Custom Sync Events

```php
use JTD\FirebaseModels\Sync\Events\SyncStarted;
use JTD\FirebaseModels\Sync\Events\SyncCompleted;
use JTD\FirebaseModels\Sync\Events\SyncFailed;

// Listen for sync events
Event::listen(SyncStarted::class, function ($event) {
    Log::info("Sync started for collection: {$event->collection}");
});

Event::listen(SyncCompleted::class, function ($event) {
    Log::info("Sync completed: {$event->recordCount} records synced");
});

Event::listen(SyncFailed::class, function ($event) {
    Log::error("Sync failed: {$event->error}");
    // Send notification, trigger alert, etc.
});
```

## Best Practices

### Performance Optimization

1. **Use Appropriate Batch Sizes**
   ```php
   'performance' => [
       'batch_size' => 100, // Adjust based on document size
   ],
   ```

2. **Index Sync Fields**
   ```php
   // In your migration
   $table->index(['firebase_updated_at']);
   $table->index(['synced_at']);
   ```

3. **Limit Synced Fields**
   ```php
   protected $syncFields = [
       'title', 'status', 'updated_at' // Only sync what you need
   ];
   ```

### Data Consistency

1. **Use Transactions for Critical Operations**
   ```php
   DB::transaction(function () use ($syncData) {
       // Update local data
       // Update sync timestamps
   });
   ```

2. **Implement Proper Conflict Resolution**
   ```php
   protected $syncConflictResolution = 'last_write_wins';
   
   protected function resolveConflict($localData, $cloudData)
   {
       // Custom conflict resolution logic
       return $cloudData['updated_at'] > $localData['updated_at'] 
           ? $cloudData 
           : $localData;
   }
   ```

### Error Handling

1. **Implement Retry Logic**
   ```php
   'sync' => [
       'retry' => [
           'max_attempts' => 3,
           'delay' => 5, // seconds
       ],
   ],
   ```

2. **Monitor Sync Health**
   ```php
   // Check for stale syncs
   $staleCollections = SyncStatus::staleCollections(hours: 2);
   
   if (!empty($staleCollections)) {
       // Alert administrators
   }
   ```

## Troubleshooting

### Common Issues

1. **Sync Not Running**
   - Check if sync is enabled in configuration
   - Verify database connection
   - Check Laravel scheduler is running

2. **Performance Issues**
   - Reduce batch size
   - Add database indexes
   - Limit synced fields

3. **Conflict Resolution Failures**
   - Check timestamp field configuration
   - Verify conflict resolution policy
   - Review custom conflict resolution logic

### Debug Commands

```bash
# Check sync configuration
php artisan firebase:sync:status

# Test sync connection
php artisan firebase:sync:test

# Clear sync cache
php artisan firebase:sync:clear

# Reset sync timestamps
php artisan firebase:sync:reset --collection=users
```

### Logging and Debugging

```php
// Enable debug logging
'sync' => [
    'debug' => true,
    'log_level' => 'debug',
],

// Check sync logs
tail -f storage/logs/firebase-sync.log
```

## Best Practices for Per-Model Sync

### When to Disable Sync

**Temporary or Cache Data:**
```php
class SessionCache extends FirestoreModel
{
    protected ?bool $syncEnabled = false; // Don't sync session data
}
```

**High-Volume Analytics:**
```php
class PageView extends FirestoreModel
{
    protected ?bool $syncEnabled = false; // Don't sync analytics events
}
```

**File Metadata:**
```php
class FileUpload extends FirestoreModel
{
    protected ?bool $syncEnabled = false; // Don't sync file metadata
}
```

### When to Force Enable Sync

**Audit and Compliance:**
```php
class AuditLog extends FirestoreModel
{
    protected ?bool $syncEnabled = true; // Always sync for compliance
}
```

**Critical Business Data:**
```php
class Order extends FirestoreModel
{
    protected ?bool $syncEnabled = true; // Always sync orders
}
```

**User Preferences:**
```php
class UserSetting extends FirestoreModel
{
    protected ?bool $syncEnabled = true; // Always sync user settings
}
```

### Performance Considerations

1. **Selective Syncing**: Only sync models that need local database access
2. **Inheritance Planning**: Use base classes to set default sync behavior
3. **Testing**: Test both sync-enabled and sync-disabled models thoroughly
4. **Monitoring**: Monitor sync performance for enabled models

### Migration Strategy

When adding per-model sync to existing applications:

1. **Start with Global Configuration**: Keep existing global sync settings
2. **Identify Candidates**: Find models that don't need sync
3. **Gradual Migration**: Disable sync for non-critical models first
4. **Monitor Impact**: Watch for performance improvements
5. **Document Decisions**: Record why each model has its sync setting

For more advanced topics, see:
- [Conflict Resolution Guide](conflict-resolution.md)
- [Transactions Documentation](12-transactions.md)
- [Performance Optimization](performance.md)
