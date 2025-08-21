# Conflict Resolution Guide

When working with distributed systems like Firestore, conflicts can occur when multiple clients attempt to modify the same data simultaneously. Firebase Models provides comprehensive conflict resolution strategies to handle these scenarios gracefully.

## Table of Contents

- [Understanding Conflicts](#understanding-conflicts)
- [Conflict Resolution Strategies](#conflict-resolution-strategies)
- [Configuration](#configuration)
- [Implementation Examples](#implementation-examples)
- [Custom Conflict Resolvers](#custom-conflict-resolvers)
- [Monitoring and Debugging](#monitoring-and-debugging)
- [Best Practices](#best-practices)
- [Advanced Scenarios](#advanced-scenarios)

## Understanding Conflicts

### What Are Conflicts?

Conflicts occur when:
1. **Concurrent modifications** - Multiple clients modify the same document simultaneously
2. **Network partitions** - Clients work offline and sync conflicting changes
3. **Race conditions** - Operations depend on stale data due to timing issues

### Types of Conflicts

```php
// 1. Write-Write Conflicts
// Client A and B both update user profile simultaneously
Client A: User::find('user-123')->update(['name' => 'Alice Smith']);
Client B: User::find('user-123')->update(['name' => 'Alice Johnson']);

// 2. Read-Write Conflicts  
// Client A reads data, Client B modifies it, then Client A tries to update
Client A: $user = User::find('user-123'); // balance: 100
Client B: User::find('user-123')->update(['balance' => 150]);
Client A: $user->update(['balance' => $user->balance - 50]); // Conflict!

// 3. Version Conflicts
// Updates based on outdated version information
$user = User::find('user-123'); // version: 5
// ... time passes, another client updates to version 6
$user->update(['status' => 'active']); // Conflict - version mismatch
```

## Conflict Resolution Strategies

### 1. Last Write Wins (LWW)

The most recent update takes precedence based on timestamps.

```php
// Configuration
'conflict_resolution' => [
    'policy' => 'last_write_wins',
    'timestamp_field' => 'updated_at',
],

// Usage
class User extends FirestoreModel
{
    protected $conflictResolution = 'last_write_wins';
    protected $timestampField = 'updated_at';
    
    // Automatic conflict resolution
    public function updateProfile(array $data)
    {
        return $this->updateWithConflictResolution($data);
    }
}
```

### 2. Version-Based Resolution

Uses version numbers to detect and resolve conflicts.

```php
// Configuration
'conflict_resolution' => [
    'policy' => 'version_based',
    'version_field' => 'version',
],

// Model implementation
class Document extends FirestoreModel
{
    protected $conflictResolution = 'version_based';
    protected $versionField = 'version';
    
    protected $fillable = ['title', 'content', 'version'];
    
    // Automatic version increment
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($model) {
            if ($model->exists) {
                $model->version = ($model->version ?? 0) + 1;
            } else {
                $model->version = 1;
            }
        });
    }
}

// Usage with version checking
$document = Document::find('doc-123');
$originalVersion = $document->version;

try {
    $document->update([
        'content' => 'Updated content',
        'version' => $originalVersion + 1
    ]);
} catch (ConflictException $e) {
    // Handle version conflict
    $document->refresh();
    // Retry or merge changes
}
```

### 3. Cloud Wins

Cloud (Firestore) data always takes precedence over local changes.

```php
'conflict_resolution' => [
    'policy' => 'cloud_wins',
],

// Useful for reference data that shouldn't be modified locally
class Category extends FirestoreModel
{
    protected $conflictResolution = 'cloud_wins';
    
    // Local changes are discarded in favor of cloud data
}
```

### 4. Local Wins

Local changes take precedence over cloud data.

```php
'conflict_resolution' => [
    'policy' => 'local_wins',
],

// Useful for user preferences or draft data
class UserPreferences extends FirestoreModel
{
    protected $conflictResolution = 'local_wins';
    
    // Cloud changes are discarded in favor of local modifications
}
```

### 5. Manual Resolution

Conflicts are detected but require manual intervention.

```php
'conflict_resolution' => [
    'policy' => 'manual',
],

class CriticalData extends FirestoreModel
{
    protected $conflictResolution = 'manual';
    
    public function resolveConflict($localData, $cloudData)
    {
        // Store conflict for manual resolution
        ConflictLog::create([
            'model_type' => static::class,
            'model_id' => $this->getKey(),
            'local_data' => $localData,
            'cloud_data' => $cloudData,
            'status' => 'pending',
        ]);
        
        throw new ManualResolutionRequiredException(
            'Conflict requires manual resolution'
        );
    }
}
```

## Configuration

### Global Configuration

```php
// config/firebase-models.php
'conflict_resolution' => [
    'default_policy' => 'last_write_wins',
    'timestamp_field' => 'updated_at',
    'version_field' => 'version',
    
    'policies' => [
        'last_write_wins' => [
            'timestamp_tolerance' => 1000, // 1 second tolerance in ms
        ],
        'version_based' => [
            'auto_increment' => true,
            'start_version' => 1,
        ],
        'manual' => [
            'log_conflicts' => true,
            'notification_channel' => 'conflict_alerts',
        ],
    ],
    
    'sync_mode' => [
        'conflict_detection' => true,
        'auto_resolve' => true,
        'fallback_policy' => 'cloud_wins',
    ],
],
```

### Model-Specific Configuration

```php
class Order extends FirestoreModel
{
    // Override global settings
    protected $conflictResolution = 'version_based';
    protected $versionField = 'revision';
    protected $timestampField = 'modified_at';
    
    // Custom conflict resolution options
    protected $conflictOptions = [
        'merge_arrays' => true,
        'preserve_user_fields' => ['notes', 'tags'],
        'auto_retry' => 3,
    ];
}
```

## Implementation Examples

### Implementing Last Write Wins

```php
use JTD\FirebaseModels\Sync\ConflictResolvers\LastWriteWinsResolver;

class BlogPost extends FirestoreModel
{
    protected $conflictResolution = 'last_write_wins';
    
    public function updateContent(string $content, string $authorId)
    {
        return $this->updateWithConflictResolution([
            'content' => $content,
            'last_edited_by' => $authorId,
            'updated_at' => now(),
        ]);
    }
    
    // Custom conflict resolution logic
    protected function resolveLastWriteWinsConflict($localData, $cloudData)
    {
        $localTime = Carbon::parse($localData['updated_at']);
        $cloudTime = Carbon::parse($cloudData['updated_at']);
        
        // Add tolerance for clock skew
        $tolerance = 1; // 1 second
        
        if ($cloudTime->diffInSeconds($localTime) <= $tolerance) {
            // Times are too close, use additional criteria
            return $this->resolveByEditCount($localData, $cloudData);
        }
        
        return $cloudTime->gt($localTime) ? $cloudData : $localData;
    }
    
    private function resolveByEditCount($localData, $cloudData)
    {
        $localCount = $localData['edit_count'] ?? 0;
        $cloudCount = $cloudData['edit_count'] ?? 0;
        
        return $cloudCount > $localCount ? $cloudData : $localData;
    }
}
```

### Implementing Version-Based Resolution

```php
class CollaborativeDocument extends FirestoreModel
{
    protected $conflictResolution = 'version_based';
    protected $versionField = 'version';
    
    public function updateWithVersion(array $data, int $expectedVersion)
    {
        // Ensure version matches expected value
        $data['version'] = $expectedVersion + 1;
        
        try {
            return $this->conditionalUpdate(
                ['version' => $expectedVersion],
                $data
            );
        } catch (ConflictException $e) {
            // Version mismatch - handle conflict
            return $this->handleVersionConflict($data, $expectedVersion);
        }
    }
    
    private function handleVersionConflict(array $newData, int $expectedVersion)
    {
        // Refresh to get latest version
        $this->refresh();
        $currentVersion = $this->version;
        
        if ($currentVersion > $expectedVersion) {
            // Document was updated by someone else
            $conflictData = [
                'expected_version' => $expectedVersion,
                'current_version' => $currentVersion,
                'attempted_changes' => $newData,
                'current_data' => $this->toArray(),
            ];
            
            // Try to merge changes automatically
            $mergedData = $this->attemptAutoMerge($newData, $conflictData);
            
            if ($mergedData) {
                return $this->updateWithVersion($mergedData, $currentVersion);
            }
            
            // Auto-merge failed, require manual resolution
            throw new VersionConflictException(
                'Version conflict requires manual resolution',
                $conflictData
            );
        }
        
        // Retry with current version
        return $this->updateWithVersion($newData, $currentVersion);
    }
    
    private function attemptAutoMerge(array $newData, array $conflictData)
    {
        // Simple field-level merging
        $currentData = $conflictData['current_data'];
        $mergedData = $currentData;
        
        foreach ($newData as $field => $value) {
            if ($field === 'version') continue;
            
            // Only merge if field hasn't changed in current version
            if (!isset($currentData[$field]) || 
                $currentData[$field] === $this->getOriginal($field)) {
                $mergedData[$field] = $value;
            }
        }
        
        // Return merged data if any changes were applied
        return array_diff_assoc($mergedData, $currentData) ? $mergedData : null;
    }
}
```

## Custom Conflict Resolvers

### Creating Custom Resolvers

```php
use JTD\FirebaseModels\Contracts\ConflictResolverInterface;

class BusinessLogicResolver implements ConflictResolverInterface
{
    public function resolve($localData, $cloudData, array $options = [])
    {
        // Custom business logic for conflict resolution
        
        // Example: Merge financial data carefully
        if (isset($localData['balance']) && isset($cloudData['balance'])) {
            // Use the higher balance (conservative approach)
            $resolvedData = $cloudData;
            $resolvedData['balance'] = max($localData['balance'], $cloudData['balance']);
            
            // Log the conflict for audit
            $this->logFinancialConflict($localData, $cloudData, $resolvedData);
            
            return $resolvedData;
        }
        
        // Default to last write wins for other fields
        return $this->lastWriteWins($localData, $cloudData);
    }
    
    private function logFinancialConflict($local, $cloud, $resolved)
    {
        Log::warning('Financial data conflict resolved', [
            'local_balance' => $local['balance'],
            'cloud_balance' => $cloud['balance'],
            'resolved_balance' => $resolved['balance'],
            'timestamp' => now(),
        ]);
    }
    
    private function lastWriteWins($localData, $cloudData)
    {
        $localTime = Carbon::parse($localData['updated_at'] ?? 0);
        $cloudTime = Carbon::parse($cloudData['updated_at'] ?? 0);
        
        return $cloudTime->gt($localTime) ? $cloudData : $localData;
    }
}

// Register custom resolver
class Account extends FirestoreModel
{
    protected $conflictResolver = BusinessLogicResolver::class;
}
```

### Field-Level Conflict Resolution

```php
class UserProfile extends FirestoreModel
{
    protected $conflictResolution = 'custom';
    
    // Define field-specific resolution strategies
    protected $fieldResolutionStrategies = [
        'email' => 'cloud_wins',           // Email changes from cloud only
        'preferences' => 'local_wins',      // User preferences stay local
        'last_login' => 'last_write_wins',  // Most recent login time
        'profile_image' => 'manual',        // Require manual resolution
    ];
    
    public function resolveConflict($localData, $cloudData)
    {
        $resolvedData = [];
        $manualFields = [];
        
        foreach ($localData as $field => $localValue) {
            $cloudValue = $cloudData[$field] ?? null;
            $strategy = $this->fieldResolutionStrategies[$field] ?? 'last_write_wins';
            
            switch ($strategy) {
                case 'cloud_wins':
                    $resolvedData[$field] = $cloudValue;
                    break;
                    
                case 'local_wins':
                    $resolvedData[$field] = $localValue;
                    break;
                    
                case 'last_write_wins':
                    $resolvedData[$field] = $this->resolveByTimestamp(
                        $localData, $cloudData, $field
                    );
                    break;
                    
                case 'manual':
                    $manualFields[] = $field;
                    break;
            }
        }
        
        if (!empty($manualFields)) {
            throw new ManualResolutionRequiredException(
                'Fields require manual resolution: ' . implode(', ', $manualFields),
                ['fields' => $manualFields, 'local' => $localData, 'cloud' => $cloudData]
            );
        }
        
        return $resolvedData;
    }
}
```

## Monitoring and Debugging

### Conflict Logging

```php
// Enable conflict logging
'conflict_resolution' => [
    'logging' => [
        'enabled' => true,
        'level' => 'info',
        'channel' => 'conflict_resolution',
    ],
],

// Custom conflict logger
class ConflictLogger
{
    public static function logConflict($model, $localData, $cloudData, $resolution)
    {
        Log::channel('conflict_resolution')->info('Conflict resolved', [
            'model' => get_class($model),
            'model_id' => $model->getKey(),
            'resolution_strategy' => $model->getConflictResolution(),
            'local_timestamp' => $localData['updated_at'] ?? null,
            'cloud_timestamp' => $cloudData['updated_at'] ?? null,
            'resolution' => $resolution,
            'resolved_at' => now(),
        ]);
    }
}
```

### Conflict Metrics

```php
// Track conflict statistics
class ConflictMetrics
{
    public static function recordConflict($model, $strategy, $outcome)
    {
        Cache::increment("conflicts.{$strategy}.total");
        Cache::increment("conflicts.{$strategy}.{$outcome}");
        Cache::increment("conflicts.model." . class_basename($model));
        
        // Store detailed metrics
        DB::table('conflict_metrics')->insert([
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'strategy' => $strategy,
            'outcome' => $outcome,
            'created_at' => now(),
        ]);
    }
    
    public static function getConflictStats($period = '24h')
    {
        return [
            'total_conflicts' => Cache::get('conflicts.total', 0),
            'auto_resolved' => Cache::get('conflicts.auto_resolved', 0),
            'manual_required' => Cache::get('conflicts.manual_required', 0),
            'resolution_rate' => $this->calculateResolutionRate(),
        ];
    }
}
```

## Best Practices

### 1. Choose Appropriate Strategies

```php
// For user-generated content
class UserPost extends FirestoreModel
{
    protected $conflictResolution = 'last_write_wins';
    // Users expect their latest changes to be preserved
}

// For system-managed data
class SystemConfig extends FirestoreModel
{
    protected $conflictResolution = 'cloud_wins';
    // System updates should always take precedence
}

// For collaborative documents
class SharedDocument extends FirestoreModel
{
    protected $conflictResolution = 'version_based';
    // Prevent accidental overwrites in collaborative editing
}

// For critical financial data
class Transaction extends FirestoreModel
{
    protected $conflictResolution = 'manual';
    // Financial conflicts require human review
}
```

### 2. Implement Proper Validation

```php
class Order extends FirestoreModel
{
    protected $conflictResolution = 'version_based';
    
    public function updateStatus($newStatus, $expectedVersion)
    {
        // Validate state transitions
        if (!$this->isValidStatusTransition($this->status, $newStatus)) {
            throw new InvalidStatusTransitionException(
                "Cannot change status from {$this->status} to {$newStatus}"
            );
        }
        
        // Update with version check
        return $this->updateWithVersion([
            'status' => $newStatus,
            'status_changed_at' => now(),
        ], $expectedVersion);
    }
    
    private function isValidStatusTransition($from, $to)
    {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            'delivered' => [],
            'cancelled' => [],
        ];
        
        return in_array($to, $validTransitions[$from] ?? []);
    }
}
```

### 3. Handle Edge Cases

```php
class RobustConflictHandler
{
    public function handleConflict($model, $localData, $cloudData)
    {
        try {
            // Attempt automatic resolution
            $resolved = $this->autoResolve($model, $localData, $cloudData);
            
            if ($resolved) {
                return $resolved;
            }
            
            // Fall back to manual resolution
            return $this->queueManualResolution($model, $localData, $cloudData);
            
        } catch (Exception $e) {
            // Log error and use safe fallback
            Log::error('Conflict resolution failed', [
                'model' => get_class($model),
                'error' => $e->getMessage(),
            ]);
            
            // Safe fallback: preserve cloud data
            return $cloudData;
        }
    }
    
    private function queueManualResolution($model, $localData, $cloudData)
    {
        // Store conflict for manual review
        ConflictQueue::create([
            'model_type' => get_class($model),
            'model_id' => $model->getKey(),
            'local_data' => $localData,
            'cloud_data' => $cloudData,
            'priority' => $this->getConflictPriority($model),
        ]);
        
        // Notify administrators
        event(new ConflictRequiresAttention($model, $localData, $cloudData));
        
        // Return cloud data as temporary resolution
        return $cloudData;
    }
}
```

## Advanced Scenarios

### Operational Transform for Real-time Collaboration

```php
class OperationalTransformResolver
{
    public function resolveTextConflict($localOps, $cloudOps, $baseText)
    {
        // Transform operations to resolve conflicts
        $transformedLocalOps = $this->transform($localOps, $cloudOps);
        $transformedCloudOps = $this->transform($cloudOps, $localOps);
        
        // Apply operations in correct order
        $result = $baseText;
        $result = $this->applyOperations($result, $transformedCloudOps);
        $result = $this->applyOperations($result, $transformedLocalOps);
        
        return $result;
    }
}
```

### Three-Way Merge

```php
class ThreeWayMergeResolver
{
    public function merge($base, $local, $cloud)
    {
        $merged = $base;
        
        // Apply changes from local
        foreach ($local as $field => $value) {
            if (!isset($base[$field]) || $base[$field] !== $value) {
                $merged[$field] = $value;
            }
        }
        
        // Apply non-conflicting changes from cloud
        foreach ($cloud as $field => $value) {
            if (!isset($base[$field]) || $base[$field] !== $value) {
                if (!isset($local[$field]) || $local[$field] === $base[$field]) {
                    $merged[$field] = $value;
                } else {
                    // Conflict detected
                    $merged[$field] = $this->resolveFieldConflict(
                        $field, $base[$field] ?? null, $local[$field], $value
                    );
                }
            }
        }
        
        return $merged;
    }
}
```

For more information, see:
- [Sync Mode Guide](sync-mode.md)
- [Transactions Documentation](transactions.md)
- [Performance Optimization](performance.md)
