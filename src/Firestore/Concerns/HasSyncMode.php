<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JTD\FirebaseModels\Sync\SyncManager;

/**
 * Trait for handling sync mode detection and operations in Firestore models.
 */
trait HasSyncMode
{
    /**
     * Check if sync mode is enabled globally.
     */
    public function isSyncModeEnabled(): bool
    {
        return config('firebase-models.mode') === 'sync';
    }

    /**
     * Check if this model supports sync mode.
     */
    public function supportsSyncMode(): bool
    {
        return $this->isSyncModeEnabled() && $this->hasLocalTable();
    }

    /**
     * Check if the model has a corresponding local table.
     */
    public function hasLocalTable(): bool
    {
        $tableName = $this->getSyncTableName();
        return Schema::hasTable($tableName);
    }

    /**
     * Get the local table name for sync operations.
     */
    public function getSyncTableName(): string
    {
        // Allow models to override the sync table name
        if (property_exists($this, 'syncTable') && $this->syncTable) {
            return $this->syncTable;
        }

        // Default to collection name
        return $this->getCollection();
    }

    /**
     * Determine if reads should come from local database.
     */
    public function shouldReadFromLocal(): bool
    {
        if (!$this->supportsSyncMode()) {
            return false;
        }

        // Check sync strategy configuration
        $strategy = config('firebase-models.sync.read_strategy', 'local_first');
        
        return match ($strategy) {
            'local_only' => true,
            'local_first' => $this->hasLocalTable(),
            'firestore_first' => false,
            'firestore_only' => false,
            default => $this->hasLocalTable(),
        };
    }

    /**
     * Determine if writes should go to local database.
     */
    public function shouldWriteToLocal(): bool
    {
        if (!$this->supportsSyncMode()) {
            return false;
        }

        $strategy = config('firebase-models.sync.write_strategy', 'both');
        
        return in_array($strategy, ['local_only', 'both']);
    }

    /**
     * Determine if writes should go to Firestore.
     */
    public function shouldWriteToFirestore(): bool
    {
        if (!$this->isSyncModeEnabled()) {
            return true; // Always write to Firestore in cloud mode
        }

        $strategy = config('firebase-models.sync.write_strategy', 'both');
        
        return in_array($strategy, ['firestore_only', 'both']);
    }

    /**
     * Get data from local database.
     */
    protected function getFromLocal(string $id): ?array
    {
        if (!$this->hasLocalTable()) {
            return null;
        }

        $record = DB::table($this->getSyncTableName())
            ->where('id', $id)
            ->first();

        return $record ? (array) $record : null;
    }

    /**
     * Save data to local database.
     */
    protected function saveToLocal(array $data): bool
    {
        if (!$this->hasLocalTable()) {
            return false;
        }

        $tableName = $this->getSyncTableName();
        $id = $data['id'] ?? $this->getKey();

        try {
            // Check if record exists
            $exists = DB::table($tableName)->where('id', $id)->exists();

            if ($exists) {
                // Update existing record
                unset($data['id']); // Don't update the ID
                $data['updated_at'] = now();
                
                DB::table($tableName)->where('id', $id)->update($data);
            } else {
                // Insert new record
                $data['id'] = $id;
                $data['created_at'] = $data['created_at'] ?? now();
                $data['updated_at'] = now();
                
                DB::table($tableName)->insert($data);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to save to local database: " . $e->getMessage(), [
                'model' => static::class,
                'id' => $id,
                'table' => $tableName
            ]);
            return false;
        }
    }

    /**
     * Delete from local database.
     */
    protected function deleteFromLocal(string $id): bool
    {
        if (!$this->hasLocalTable()) {
            return false;
        }

        try {
            $deleted = DB::table($this->getSyncTableName())
                ->where('id', $id)
                ->delete();

            return $deleted > 0;
        } catch (\Exception $e) {
            \Log::error("Failed to delete from local database: " . $e->getMessage(), [
                'model' => static::class,
                'id' => $id
            ]);
            return false;
        }
    }

    /**
     * Sync this model instance with the sync manager.
     */
    public function syncToLocal(): bool
    {
        if (!$this->supportsSyncMode() || !$this->exists) {
            return false;
        }

        $syncManager = app(SyncManager::class);
        $result = $syncManager->syncDocument($this->getCollection(), $this->getKey());

        return $result->isSuccessful();
    }

    /**
     * Get sync status for this model.
     */
    public function getSyncStatus(): array
    {
        return [
            'sync_mode_enabled' => $this->isSyncModeEnabled(),
            'supports_sync' => $this->supportsSyncMode(),
            'has_local_table' => $this->hasLocalTable(),
            'should_read_local' => $this->shouldReadFromLocal(),
            'should_write_local' => $this->shouldWriteToLocal(),
            'should_write_firestore' => $this->shouldWriteToFirestore(),
            'sync_table_name' => $this->getSyncTableName(),
        ];
    }

    /**
     * Override the newModelQuery method to handle sync mode.
     */
    public function newModelQuery(): \JTD\FirebaseModels\Firestore\FirestoreModelQueryBuilder
    {
        $query = parent::newModelQuery();
        
        // Set sync mode preferences on the query builder
        if (method_exists($query, 'setSyncMode')) {
            $query->setSyncMode($this->shouldReadFromLocal());
        }

        return $query;
    }
}
