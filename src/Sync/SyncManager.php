<?php

namespace JTD\FirebaseModels\Sync;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use JTD\FirebaseModels\Contracts\Sync\SyncStrategyInterface;
use JTD\FirebaseModels\Contracts\Sync\ConflictResolverInterface;
use JTD\FirebaseModels\Contracts\Sync\SchemaMapperInterface;
use JTD\FirebaseModels\Contracts\Sync\SyncResultInterface;
use JTD\FirebaseModels\Sync\Results\SyncResult;
use JTD\FirebaseModels\Sync\Strategies\OneWayStrategy;
use JTD\FirebaseModels\Sync\Conflicts\LastWriteWinsResolver;
use JTD\FirebaseModels\Sync\Conflicts\VersionBasedResolver;
use JTD\FirebaseModels\Sync\Schema\DefaultSchemaMapper;

/**
 * Core SyncManager class that orchestrates data synchronization
 * between Firestore and local database.
 */
class SyncManager
{
    /**
     * Available sync strategies.
     */
    protected array $strategies = [];

    /**
     * Available conflict resolvers.
     */
    protected array $conflictResolvers = [];

    /**
     * The schema mapper instance.
     */
    protected SchemaMapperInterface $schemaMapper;

    /**
     * Sync configuration.
     */
    protected array $config;

    /**
     * Create a new SyncManager instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->initializeDefaults();
    }

    /**
     * Sync a specific collection.
     */
    public function syncCollection(string $collection, array $options = []): SyncResultInterface
    {
        $strategy = $this->getStrategy($options['strategy'] ?? $this->config['strategy']);
        
        if (!$strategy) {
            throw new \InvalidArgumentException("Sync strategy not found: " . ($options['strategy'] ?? $this->config['strategy']));
        }

        // Set up strategy dependencies
        $strategy->setConflictResolver($this->getConflictResolver());
        $strategy->setSchemaMapper($this->schemaMapper);

        Log::info("Starting sync for collection: {$collection}", [
            'strategy' => $strategy->getName(),
            'options' => $options
        ]);

        try {
            $result = $strategy->sync($collection, $options);
            
            Log::info("Sync completed for collection: {$collection}", [
                'processed' => $result->getProcessedCount(),
                'synced' => $result->getSyncedCount(),
                'conflicts' => $result->getConflictCount(),
                'errors' => $result->getErrorCount()
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error("Sync failed for collection: {$collection}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Sync a specific document.
     */
    public function syncDocument(string $collection, string $documentId, array $options = []): SyncResultInterface
    {
        $strategy = $this->getStrategy($options['strategy'] ?? $this->config['strategy']);
        
        if (!$strategy) {
            throw new \InvalidArgumentException("Sync strategy not found: " . ($options['strategy'] ?? $this->config['strategy']));
        }

        $strategy->setConflictResolver($this->getConflictResolver());
        $strategy->setSchemaMapper($this->schemaMapper);

        return $strategy->syncDocument($collection, $documentId, $options);
    }

    /**
     * Sync multiple collections.
     */
    public function syncCollections(array $collections, array $options = []): Collection
    {
        $results = new Collection();

        foreach ($collections as $collection) {
            $results->put($collection, $this->syncCollection($collection, $options));
        }

        return $results;
    }

    /**
     * Register a sync strategy.
     */
    public function registerStrategy(string $name, SyncStrategyInterface $strategy): void
    {
        $this->strategies[$name] = $strategy;
    }

    /**
     * Register a conflict resolver.
     */
    public function registerConflictResolver(string $name, ConflictResolverInterface $resolver): void
    {
        $this->conflictResolvers[$name] = $resolver;
    }

    /**
     * Set the schema mapper.
     */
    public function setSchemaMapper(SchemaMapperInterface $mapper): void
    {
        $this->schemaMapper = $mapper;
    }

    /**
     * Get a sync strategy by name.
     */
    public function getStrategy(string $name): ?SyncStrategyInterface
    {
        return $this->strategies[$name] ?? null;
    }

    /**
     * Get the current conflict resolver.
     */
    public function getConflictResolver(): ConflictResolverInterface
    {
        $resolverName = $this->config['conflict_policy'] ?? 'last_write_wins';
        
        if (!isset($this->conflictResolvers[$resolverName])) {
            throw new \InvalidArgumentException("Conflict resolver not found: {$resolverName}");
        }

        return $this->conflictResolvers[$resolverName];
    }

    /**
     * Get the schema mapper.
     */
    public function getSchemaMapper(): SchemaMapperInterface
    {
        return $this->schemaMapper;
    }

    /**
     * Get available strategies.
     */
    public function getAvailableStrategies(): array
    {
        return array_keys($this->strategies);
    }

    /**
     * Get available conflict resolvers.
     */
    public function getAvailableConflictResolvers(): array
    {
        return array_keys($this->conflictResolvers);
    }

    /**
     * Check if sync mode is enabled.
     */
    public function isSyncModeEnabled(): bool
    {
        return config('firebase-models.mode') === 'sync';
    }

    /**
     * Get sync configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Initialize default strategies and resolvers.
     */
    protected function initializeDefaults(): void
    {
        // Register default strategy
        $this->registerStrategy('one_way', new OneWayStrategy());

        // Register conflict resolvers
        $this->registerConflictResolver('last_write_wins', new LastWriteWinsResolver());
        $this->registerConflictResolver('version_based', new VersionBasedResolver());

        // Set default schema mapper
        $this->schemaMapper = new DefaultSchemaMapper();
    }

    /**
     * Get default configuration.
     */
    protected function getDefaultConfig(): array
    {
        return [
            'strategy' => 'one_way',
            'conflict_policy' => 'last_write_wins',
            'batch_size' => 100,
            'timeout' => 300,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // milliseconds
        ];
    }
}
