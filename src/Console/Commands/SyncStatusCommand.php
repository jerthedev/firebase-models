<?php

namespace JTD\FirebaseModels\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use JTD\FirebaseModels\Sync\SyncManager;

/**
 * Command to show sync status and configuration.
 */
class SyncStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'firebase:sync:status
                            {--collections= : Check specific collections}
                            {--detailed : Show detailed information}';

    /**
     * The console command description.
     */
    protected $description = 'Show Firebase sync status and configuration';

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
        $this->info('🔍 Firebase Sync Status');
        $this->newLine();

        // Show general configuration
        $this->showGeneralStatus();
        $this->newLine();

        // Show sync strategies and resolvers
        $this->showStrategiesAndResolvers();
        $this->newLine();

        // Show collection status
        $this->showCollectionStatus();
        $this->newLine();

        // Show schedule status
        $this->showScheduleStatus();

        return self::SUCCESS;
    }

    /**
     * Show general sync status.
     */
    protected function showGeneralStatus(): void
    {
        $config = $this->syncManager->getConfig();
        $isSyncEnabled = $this->syncManager->isSyncModeEnabled();

        $this->info('📋 General Configuration:');
        $this->table(
            ['Setting', 'Value', 'Status'],
            [
                ['Sync Mode', config('firebase-models.mode'), $isSyncEnabled ? '✅ Enabled' : '❌ Disabled'],
                ['Strategy', $config['strategy'] ?? 'N/A', ''],
                ['Conflict Policy', $config['conflict_policy'] ?? 'N/A', ''],
                ['Batch Size', $config['batch_size'] ?? 'N/A', ''],
                ['Timeout', ($config['timeout'] ?? 'N/A').'s', ''],
                ['Retry Attempts', $config['retry_attempts'] ?? 'N/A', ''],
            ]
        );
    }

    /**
     * Show available strategies and resolvers.
     */
    protected function showStrategiesAndResolvers(): void
    {
        $this->info('🔧 Available Components:');

        $strategies = $this->syncManager->getAvailableStrategies();
        $resolvers = $this->syncManager->getAvailableConflictResolvers();

        $this->table(
            ['Component Type', 'Available Options'],
            [
                ['Sync Strategies', implode(', ', $strategies)],
                ['Conflict Resolvers', implode(', ', $resolvers)],
            ]
        );
    }

    /**
     * Show collection sync status.
     */
    protected function showCollectionStatus(): void
    {
        $this->info('📊 Collection Status:');

        $collections = $this->getCollectionsToCheck();

        if ($collections->isEmpty()) {
            $this->warn('No collections configured for sync.');

            return;
        }

        $tableData = [];

        foreach ($collections as $collection) {
            $status = $this->getCollectionSyncStatus($collection);
            $tableData[] = [
                $collection,
                $status['has_mapping'] ? '✅' : '❌',
                $status['table_exists'] ? '✅' : '❌',
                $status['can_sync'] ? '✅' : '❌',
                $status['table_name'] ?? 'N/A',
            ];
        }

        $this->table(
            ['Collection', 'Has Mapping', 'Table Exists', 'Can Sync', 'Table Name'],
            $tableData
        );

        if ($this->option('detailed')) {
            $this->showDetailedCollectionInfo($collections);
        }
    }

    /**
     * Show schedule status.
     */
    protected function showScheduleStatus(): void
    {
        $scheduleConfig = config('firebase-models.sync.schedule', []);
        $isEnabled = $scheduleConfig['enabled'] ?? false;

        $this->info('⏰ Schedule Configuration:');
        $this->table(
            ['Setting', 'Value', 'Status'],
            [
                ['Enabled', $isEnabled ? 'Yes' : 'No', $isEnabled ? '✅' : '❌'],
                ['Frequency', $scheduleConfig['frequency'] ?? 'N/A', ''],
                ['Collections', $scheduleConfig['collections'] ?? 'N/A', ''],
            ]
        );

        if ($isEnabled) {
            $this->info('💡 To set up the schedule, add this to your app/Console/Kernel.php:');
            $this->line('   $schedule->command(\'firebase:sync:scheduled\')->'.($scheduleConfig['frequency'] ?? 'hourly').'();');
        }
    }

    /**
     * Get collections to check.
     */
    protected function getCollectionsToCheck(): \Illuminate\Support\Collection
    {
        if ($collectionsOption = $this->option('collections')) {
            return collect(explode(',', $collectionsOption))
                ->map(fn ($c) => trim($c))
                ->filter();
        }

        // Get from schedule config
        $configCollections = config('firebase-models.sync.schedule.collections', '');
        if ($configCollections) {
            return collect(explode(',', $configCollections))
                ->map(fn ($c) => trim($c))
                ->filter();
        }

        // Get from sync mappings
        $mappings = config('firebase-models.sync.mappings', []);

        return collect(array_keys($mappings));
    }

    /**
     * Get sync status for a collection.
     */
    protected function getCollectionSyncStatus(string $collection): array
    {
        $schemaMapper = $this->syncManager->getSchemaMapper();

        $hasMapping = $schemaMapper->hasMapping($collection);
        $tableName = $hasMapping ? $schemaMapper->getTableName($collection) : null;
        $tableExists = $tableName ? Schema::hasTable($tableName) : false;

        return [
            'has_mapping' => $hasMapping,
            'table_name' => $tableName,
            'table_exists' => $tableExists,
            'can_sync' => $hasMapping && $tableExists,
        ];
    }

    /**
     * Show detailed collection information.
     */
    protected function showDetailedCollectionInfo(\Illuminate\Support\Collection $collections): void
    {
        $this->newLine();
        $this->info('📝 Detailed Collection Information:');

        foreach ($collections as $collection) {
            $this->newLine();
            $this->line("Collection: <comment>{$collection}</comment>");

            $status = $this->getCollectionSyncStatus($collection);
            $schemaMapper = $this->syncManager->getSchemaMapper();

            if ($status['has_mapping']) {
                $mapping = $schemaMapper->getColumnMapping($collection);
                $this->line("  Table: {$status['table_name']}");
                $this->line('  Column Mapping: '.json_encode($mapping, JSON_PRETTY_PRINT));

                if ($status['table_exists']) {
                    $columns = Schema::getColumnListing($status['table_name']);
                    $this->line('  Table Columns: '.implode(', ', $columns));
                } else {
                    $this->line('  <error>Table does not exist</error>');
                }
            } else {
                $this->line('  <error>No mapping configured</error>');
            }
        }
    }
}
