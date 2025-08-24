<?php

namespace JTD\FirebaseModels\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use JTD\FirebaseModels\Sync\SyncManager;

/**
 * Scheduled sync command for automated Firebase synchronization.
 */
class ScheduledSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'firebase:sync:scheduled
                            {--collections= : Comma-separated list of collections to sync}
                            {--strategy= : Sync strategy to use}
                            {--batch-size= : Number of documents to process per batch}
                            {--since= : Sync documents modified since timestamp}
                            {--quiet : Suppress output}';

    /**
     * The console command description.
     */
    protected $description = 'Run scheduled Firebase sync operation';

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
        // Check if scheduled sync is enabled
        if (!config('firebase-models.sync.schedule.enabled', false)) {
            if (!$this->option('quiet')) {
                $this->info('Scheduled sync is disabled.');
            }

            return self::SUCCESS;
        }

        // Check if sync mode is enabled
        if (!$this->syncManager->isSyncModeEnabled()) {
            Log::warning('Scheduled sync attempted but sync mode is not enabled');
            if (!$this->option('quiet')) {
                $this->error('Sync mode is not enabled.');
            }

            return self::FAILURE;
        }

        $startTime = microtime(true);

        try {
            // Get collections to sync
            $collections = $this->getCollectionsToSync();

            if ($collections->isEmpty()) {
                if (!$this->option('quiet')) {
                    $this->info('No collections configured for scheduled sync.');
                }

                return self::SUCCESS;
            }

            if (!$this->option('quiet')) {
                $this->info('Starting scheduled sync for collections: '.$collections->implode(', '));
            }

            // Perform sync
            $results = $this->performSync($collections);

            // Log results
            $this->logResults($results, microtime(true) - $startTime);

            // Check for errors
            $hasErrors = $results->contains(function ($result) {
                return !$result->isSuccessful();
            });

            if (!$this->option('quiet')) {
                if ($hasErrors) {
                    $this->error('Scheduled sync completed with errors.');
                } else {
                    $this->info('Scheduled sync completed successfully.');
                }
            }

            return $hasErrors ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Scheduled sync failed: '.$e->getMessage(), [
                'exception' => $e,
                'collections' => $this->getCollectionsToSync()->toArray(),
            ]);

            if (!$this->option('quiet')) {
                $this->error('Scheduled sync failed: '.$e->getMessage());
            }

            return self::FAILURE;
        }
    }

    /**
     * Get collections to sync.
     */
    protected function getCollectionsToSync(): \Illuminate\Support\Collection
    {
        // Use command option if provided
        if ($collectionsOption = $this->option('collections')) {
            return collect(explode(',', $collectionsOption))
                ->map(fn ($c) => trim($c))
                ->filter();
        }

        // Use config collections
        $configCollections = config('firebase-models.sync.schedule.collections', '');
        if ($configCollections) {
            return collect(explode(',', $configCollections))
                ->map(fn ($c) => trim($c))
                ->filter();
        }

        return collect();
    }

    /**
     * Perform sync for all collections.
     */
    protected function performSync(\Illuminate\Support\Collection $collections): \Illuminate\Support\Collection
    {
        $results = collect();

        foreach ($collections as $collection) {
            try {
                $options = array_filter([
                    'strategy' => $this->option('strategy') ?: config('firebase-models.sync.strategy'),
                    'batch_size' => $this->option('batch-size') ?: config('firebase-models.sync.batch_size'),
                    'since' => $this->option('since'),
                ]);

                $result = $this->syncManager->syncCollection($collection, $options);
                $results->put($collection, $result);

                if (!$this->option('quiet')) {
                    $summary = $result->getSummary();
                    $this->line("  {$collection}: {$summary['synced']}/{$summary['processed']} synced");
                }
            } catch (\Exception $e) {
                Log::error("Failed to sync collection {$collection}: ".$e->getMessage(), [
                    'collection' => $collection,
                    'exception' => $e,
                ]);

                if (!$this->option('quiet')) {
                    $this->error("  {$collection}: Failed - ".$e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * Log sync results.
     */
    protected function logResults(\Illuminate\Support\Collection $results, float $duration): void
    {
        $totalProcessed = 0;
        $totalSynced = 0;
        $totalConflicts = 0;
        $totalErrors = 0;

        $collectionResults = [];

        foreach ($results as $collection => $result) {
            $summary = $result->getSummary();

            $collectionResults[$collection] = $summary;

            $totalProcessed += $summary['processed'];
            $totalSynced += $summary['synced'];
            $totalConflicts += $summary['conflicts'];
            $totalErrors += $summary['errors'];
        }

        $logData = [
            'duration' => round($duration, 2),
            'totals' => [
                'processed' => $totalProcessed,
                'synced' => $totalSynced,
                'conflicts' => $totalConflicts,
                'errors' => $totalErrors,
                'success_rate' => $totalProcessed > 0 ? round(($totalSynced / $totalProcessed) * 100, 2) : 0,
            ],
            'collections' => $collectionResults,
        ];

        if ($totalErrors > 0) {
            Log::error('Scheduled sync completed with errors', $logData);
        } elseif ($totalConflicts > 0) {
            Log::warning('Scheduled sync completed with conflicts', $logData);
        } else {
            Log::info('Scheduled sync completed successfully', $logData);
        }
    }
}
