<?php

namespace JTD\FirebaseModels\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use JTD\FirebaseModels\Contracts\Sync\SyncResultInterface;
use JTD\FirebaseModels\Sync\SyncManager;

/**
 * Artisan command for running Firebase sync operations.
 */
class FirebaseSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'firebase:sync
                            {collection? : Specific collection to sync}
                            {--once : Run a single sync pass}
                            {--since= : Sync documents modified since timestamp}
                            {--dry-run : Show what would be synced without making changes}
                            {--strategy= : Sync strategy to use}
                            {--batch-size= : Number of documents to process per batch}
                            {--timeout= : Timeout in seconds}
                            {--collections= : Comma-separated list of collections to sync}
                            {--exclude= : Comma-separated list of collections to exclude}
                            {--force : Force sync even if not in sync mode}
                            {--verbose : Show detailed output}';

    /**
     * The console command description.
     */
    protected $description = 'Sync data between Firestore and local database';

    /**
     * The sync manager instance.
     */
    protected SyncManager $syncManager;

    /**
     * Create a new command instance.
     */
    public function __construct(SyncManager $syncManager)
    {
        parent::__construct();
        $this->syncManager = $syncManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Firebase Sync Command');
        $this->newLine();

        // Check if sync mode is enabled
        if (!$this->syncManager->isSyncModeEnabled() && !$this->option('force')) {
            $this->error('❌ Sync mode is not enabled. Use --force to override.');
            $this->line('   Set FIREBASE_MODE=sync in your .env file to enable sync mode.');

            return self::FAILURE;
        }

        // Validate options
        if (!$this->validateOptions()) {
            return self::FAILURE;
        }

        // Get collections to sync
        $collections = $this->getCollectionsToSync();

        if ($collections->isEmpty()) {
            $this->warn('⚠️  No collections to sync.');

            return self::SUCCESS;
        }

        // Show sync configuration
        $this->displaySyncConfiguration($collections);

        // Confirm if not in dry-run mode
        if (!$this->option('dry-run') && !$this->confirmSync()) {
            $this->info('Sync cancelled.');

            return self::SUCCESS;
        }

        // Perform sync
        return $this->performSync($collections);
    }

    /**
     * Validate command options.
     */
    protected function validateOptions(): bool
    {
        // Validate since timestamp
        if ($since = $this->option('since')) {
            try {
                \Carbon\Carbon::parse($since);
            } catch (\Exception $e) {
                $this->error("❌ Invalid timestamp format: {$since}");

                return false;
            }
        }

        // Validate batch size
        if ($batchSize = $this->option('batch-size')) {
            if (!is_numeric($batchSize) || $batchSize < 1) {
                $this->error("❌ Invalid batch size: {$batchSize}");

                return false;
            }
        }

        // Validate timeout
        if ($timeout = $this->option('timeout')) {
            if (!is_numeric($timeout) || $timeout < 1) {
                $this->error("❌ Invalid timeout: {$timeout}");

                return false;
            }
        }

        return true;
    }

    /**
     * Get collections to sync.
     */
    protected function getCollectionsToSync(): Collection
    {
        $collections = new Collection();

        // Single collection argument
        if ($collection = $this->argument('collection')) {
            $collections->push($collection);
        }
        // Multiple collections option
        elseif ($collectionsOption = $this->option('collections')) {
            $collections = collect(explode(',', $collectionsOption))
                ->map(fn ($c) => trim($c))
                ->filter();
        }
        // Default collections from config
        else {
            $configCollections = config('firebase-models.sync.schedule.collections', '');
            if ($configCollections) {
                $collections = collect(explode(',', $configCollections))
                    ->map(fn ($c) => trim($c))
                    ->filter();
            }
        }

        // Apply exclusions
        if ($exclude = $this->option('exclude')) {
            $excludeList = collect(explode(',', $exclude))
                ->map(fn ($c) => trim($c))
                ->filter();

            $collections = $collections->diff($excludeList);
        }

        return $collections;
    }

    /**
     * Display sync configuration.
     */
    protected function displaySyncConfiguration(Collection $collections): void
    {
        $this->info('📋 Sync Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Mode', $this->option('dry-run') ? 'DRY RUN' : 'LIVE'],
                ['Strategy', $this->option('strategy') ?: config('firebase-models.sync.strategy', 'one_way')],
                ['Collections', $collections->implode(', ')],
                ['Since', $this->option('since') ?: 'All time'],
                ['Batch Size', $this->option('batch-size') ?: config('firebase-models.sync.batch_size', 100)],
                ['Timeout', $this->option('timeout') ?: config('firebase-models.sync.timeout', 300).'s'],
            ]
        );
        $this->newLine();
    }

    /**
     * Confirm sync operation.
     */
    protected function confirmSync(): bool
    {
        return $this->confirm('🚀 Proceed with sync?', true);
    }

    /**
     * Perform the sync operation.
     */
    protected function performSync(Collection $collections): int
    {
        $startTime = microtime(true);
        $totalResults = new Collection();
        $hasErrors = false;

        $this->info('🔄 Starting sync...');
        $this->newLine();

        // Create progress bar
        $progressBar = $this->output->createProgressBar($collections->count());
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

        foreach ($collections as $collection) {
            $progressBar->setMessage("Syncing {$collection}...");

            try {
                $result = $this->syncCollection($collection);
                $totalResults->put($collection, $result);

                if (!$result->isSuccessful()) {
                    $hasErrors = true;
                }
            } catch (\Exception $e) {
                $hasErrors = true;
                $this->newLine();
                $this->error("❌ Failed to sync {$collection}: ".$e->getMessage());

                if ($this->option('verbose')) {
                    $this->line($e->getTraceAsString());
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->displayResults($totalResults, microtime(true) - $startTime);

        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Sync a single collection.
     */
    protected function syncCollection(string $collection): SyncResultInterface
    {
        $options = array_filter([
            'strategy' => $this->option('strategy'),
            'since' => $this->option('since'),
            'batch_size' => $this->option('batch-size'),
            'timeout' => $this->option('timeout'),
            'dry_run' => $this->option('dry-run'),
        ]);

        return $this->syncManager->syncCollection($collection, $options);
    }

    /**
     * Display sync results.
     */
    protected function displayResults(Collection $results, float $duration): void
    {
        $this->info('📊 Sync Results:');

        $tableData = [];
        $totalProcessed = 0;
        $totalSynced = 0;
        $totalConflicts = 0;
        $totalErrors = 0;

        foreach ($results as $collection => $result) {
            $summary = $result->getSummary();

            $tableData[] = [
                $collection,
                $summary['processed'],
                $summary['synced'],
                $summary['conflicts'],
                $summary['errors'],
                $summary['success_rate'].'%',
            ];

            $totalProcessed += $summary['processed'];
            $totalSynced += $summary['synced'];
            $totalConflicts += $summary['conflicts'];
            $totalErrors += $summary['errors'];
        }

        // Add totals row
        $tableData[] = ['---', '---', '---', '---', '---', '---'];
        $tableData[] = [
            'TOTAL',
            $totalProcessed,
            $totalSynced,
            $totalConflicts,
            $totalErrors,
            $totalProcessed > 0 ? round(($totalSynced / $totalProcessed) * 100, 2).'%' : '0%',
        ];

        $this->table(
            ['Collection', 'Processed', 'Synced', 'Conflicts', 'Errors', 'Success Rate'],
            $tableData
        );

        $this->info('⏱️  Duration: '.round($duration, 2).' seconds');

        if ($totalErrors > 0) {
            $this->error("❌ Sync completed with {$totalErrors} errors.");
        } elseif ($totalConflicts > 0) {
            $this->warn("⚠️  Sync completed with {$totalConflicts} conflicts.");
        } else {
            $this->info('✅ Sync completed successfully!');
        }
    }
}
