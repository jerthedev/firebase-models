<?php

namespace JTD\FirebaseModels\Console\Commands;

use Illuminate\Console\Command;
use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\Optimization\QueryOptimizer;
use JTD\FirebaseModels\Optimization\MemoryManager;
use JTD\FirebaseModels\Optimization\PerformanceTuner;
use JTD\FirebaseModels\Cache\CacheManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'firestore:debug')]
class FirestoreDebugCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'firestore:debug 
                           {--connection : Test Firestore connection}
                           {--performance : Show performance statistics}
                           {--memory : Show memory usage statistics}
                           {--cache : Show cache statistics}
                           {--queries : Show query optimization data}
                           {--indexes : Show index suggestions}
                           {--config : Show configuration}
                           {--health : Perform health check}
                           {--all : Show all debug information}';

    /**
     * The console command description.
     */
    protected $description = 'Debug and analyze Firestore configuration and performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();

        if ($this->option('all')) {
            $this->showAll();
        } else {
            $this->showSpecificSections();
        }

        return Command::SUCCESS;
    }

    /**
     * Display command header.
     */
    protected function displayHeader(): void
    {
        $this->info('🔍 Firebase Models Debug Information');
        $this->line('=====================================');
        $this->newLine();
    }

    /**
     * Show all debug information.
     */
    protected function showAll(): void
    {
        $this->testConnection();
        $this->showConfiguration();
        $this->showPerformanceStats();
        $this->showMemoryStats();
        $this->showCacheStats();
        $this->showQueryStats();
        $this->showIndexSuggestions();
        $this->performHealthCheck();
    }

    /**
     * Show specific sections based on options.
     */
    protected function showSpecificSections(): void
    {
        if ($this->option('connection')) {
            $this->testConnection();
        }

        if ($this->option('config')) {
            $this->showConfiguration();
        }

        if ($this->option('performance')) {
            $this->showPerformanceStats();
        }

        if ($this->option('memory')) {
            $this->showMemoryStats();
        }

        if ($this->option('cache')) {
            $this->showCacheStats();
        }

        if ($this->option('queries')) {
            $this->showQueryStats();
        }

        if ($this->option('indexes')) {
            $this->showIndexSuggestions();
        }

        if ($this->option('health')) {
            $this->performHealthCheck();
        }

        // If no specific options, show basic info
        if (!$this->hasAnyOption()) {
            $this->testConnection();
            $this->showConfiguration();
            $this->performHealthCheck();
        }
    }

    /**
     * Test Firestore connection.
     */
    protected function testConnection(): void
    {
        $this->info('🔗 Connection Test');
        $this->line('------------------');

        try {
            $projectId = FirestoreDB::getProjectId();
            $databaseId = FirestoreDB::getDatabaseId();
            
            $this->line("Project ID: <comment>{$projectId}</comment>");
            $this->line("Database ID: <comment>{$databaseId}</comment>");
            
            // Test basic operation
            $testCollection = FirestoreDB::collection('_debug_test');
            $testDoc = $testCollection->document('connection_test');
            
            $testDoc->set(['timestamp' => now()->toISOString(), 'test' => true]);
            $snapshot = $testDoc->snapshot();
            
            if ($snapshot->exists()) {
                $this->line("Status: <info>✓ Connected</info>");
                $testDoc->delete(); // Cleanup
            } else {
                $this->line("Status: <error>✗ Connection failed</error>");
            }
            
        } catch (\Exception $e) {
            $this->line("Status: <error>✗ Connection failed</error>");
            $this->line("Error: <error>{$e->getMessage()}</error>");
        }

        $this->newLine();
    }

    /**
     * Show configuration information.
     */
    protected function showConfiguration(): void
    {
        $this->info('⚙️  Configuration');
        $this->line('------------------');

        $config = config('firebase', []);
        $firestoreConfig = config('firestore', []);

        $projectId = isset($config['project_id']) ? $config['project_id'] : 'Not set';
        $credentialsFile = isset($config['credentials']['file']) ? $config['credentials']['file'] : 'Environment variables';
        $cacheEnabled = (isset($firestoreConfig['cache']['enabled']) && $firestoreConfig['cache']['enabled']) ? 'Yes' : 'No';
        $defaultTtl = isset($firestoreConfig['cache']['default_ttl']) ? $firestoreConfig['cache']['default_ttl'] : 3600;

        $this->line("Firebase Project: <comment>{$projectId}</comment>");
        $this->line("Credentials: <comment>{$credentialsFile}</comment>");
        $this->line("Cache Enabled: <comment>{$cacheEnabled}</comment>");
        $this->line("Default TTL: <comment>{$defaultTtl}s</comment>");
        $this->line("Query Optimization: <comment>" . (QueryOptimizer::class . ' available') . "</comment>");
        $this->line("Memory Management: <comment>" . (MemoryManager::class . ' available') . "</comment>");

        $this->newLine();
    }

    /**
     * Show performance statistics.
     */
    protected function showPerformanceStats(): void
    {
        $this->info('📊 Performance Statistics');
        $this->line('-------------------------');

        try {
            $analysis = PerformanceTuner::analyzePerformance();
            
            $this->line("Overall Score: <comment>{$analysis['overall_score']}/100</comment>");
            
            if (isset($analysis['components']['queries'])) {
                $queries = $analysis['components']['queries'];
                $this->line("Query Performance: <comment>{$queries['score']}/100</comment>");
                $this->line("  - Average Time: <comment>{$queries['avg_time_ms']}ms</comment>");
                $this->line("  - Total Queries: <comment>{$queries['total_queries']}</comment>");
                $this->line("  - Slow Queries: <comment>{$queries['slow_queries']}</comment>");
            }

            if (isset($analysis['components']['memory'])) {
                $memory = $analysis['components']['memory'];
                $this->line("Memory Performance: <comment>{$memory['score']}/100</comment>");
                $this->line("  - Current Usage: <comment>{$memory['current_usage_mb']}MB</comment>");
                $this->line("  - Usage Percent: <comment>{$memory['usage_percent']}%</comment>");
            }

            if (isset($analysis['components']['cache'])) {
                $cache = $analysis['components']['cache'];
                $this->line("Cache Performance: <comment>{$cache['score']}/100</comment>");
                $this->line("  - Hit Rate: <comment>{$cache['hit_rate_percent']}%</comment>");
                $this->line("  - Total Requests: <comment>{$cache['total_requests']}</comment>");
            }

        } catch (\Exception $e) {
            $this->line("Error: <error>{$e->getMessage()}</error>");
        }

        $this->newLine();
    }

    /**
     * Show memory statistics.
     */
    protected function showMemoryStats(): void
    {
        $this->info('🧠 Memory Statistics');
        $this->line('-------------------');

        try {
            $stats = MemoryManager::getMemoryStats();
            $allocations = MemoryManager::getAllocationStats();

            $this->line("Current Usage: <comment>{$stats['current_usage_mb']}MB</comment>");
            $this->line("Peak Usage: <comment>{$stats['peak_usage_mb']}MB</comment>");
            $this->line("Memory Limit: <comment>{$stats['limit_mb']}MB</comment>");
            $this->line("Usage Percentage: <comment>{$stats['usage_percentage']}%</comment>");
            $this->line("Active Allocations: <comment>{$allocations['active_allocations']}</comment>");
            $this->line("Total Allocations: <comment>{$allocations['total_allocations']}</comment>");

            if (!empty($allocations['allocations_by_context'])) {
                $this->line("\nAllocations by Context:");
                foreach ($allocations['allocations_by_context'] as $context => $data) {
                    $this->line("  - {$context}: <comment>{$data['count']} ({$data['total_mb']}MB)</comment>");
                }
            }

        } catch (\Exception $e) {
            $this->line("Error: <error>{$e->getMessage()}</error>");
        }

        $this->newLine();
    }

    /**
     * Show cache statistics.
     */
    protected function showCacheStats(): void
    {
        $this->info('💾 Cache Statistics');
        $this->line('------------------');

        try {
            $cacheManager = app(CacheManager::class);
            $stats = $cacheManager->getStatistics();

            $hitRate = (isset($stats['hit_rate']) ? $stats['hit_rate'] : 0) * 100;
            $totalRequests = isset($stats['total_requests']) ? $stats['total_requests'] : 0;
            $hits = isset($stats['hits']) ? $stats['hits'] : 0;
            $misses = isset($stats['misses']) ? $stats['misses'] : 0;
            $cacheSize = (isset($stats['cache_size_bytes']) ? $stats['cache_size_bytes'] : 0) / 1024 / 1024;
            $evictionRate = (isset($stats['eviction_rate']) ? $stats['eviction_rate'] : 0) * 100;

            $this->line("Hit Rate: <comment>{$hitRate}%</comment>");
            $this->line("Total Requests: <comment>{$totalRequests}</comment>");
            $this->line("Cache Hits: <comment>{$hits}</comment>");
            $this->line("Cache Misses: <comment>{$misses}</comment>");
            $this->line("Cache Size: <comment>{$cacheSize}MB</comment>");
            $this->line("Eviction Rate: <comment>{$evictionRate}%</comment>");

        } catch (\Exception $e) {
            $this->line("Cache statistics not available: <comment>{$e->getMessage()}</comment>");
        }

        $this->newLine();
    }

    /**
     * Show query statistics.
     */
    protected function showQueryStats(): void
    {
        $this->info('🔍 Query Statistics');
        $this->line('------------------');

        try {
            $stats = QueryOptimizer::getQueryStats();

            $this->line("Queries Tracked: <comment>{$stats['total_queries_tracked']}</comment>");
            $this->line("Total Executions: <comment>{$stats['total_executions']}</comment>");
            $this->line("Average Time: <comment>{$stats['avg_execution_time_ms']}ms</comment>");
            $this->line("Cache Hit Rate: <comment>" . ($stats['cache_hit_rate'] * 100) . "%</comment>");

            if (!empty($stats['slow_queries'])) {
                $this->line("\nSlow Queries:");
                foreach (array_slice($stats['slow_queries'], 0, 5) as $hash => $query) {
                    $this->line("  - Hash: <comment>{$hash}</comment>");
                    $this->line("    Avg Time: <comment>{$query['avg_time_ms']}ms</comment>");
                    $this->line("    Executions: <comment>{$query['executions']}</comment>");
                }
            }

        } catch (\Exception $e) {
            $this->line("Error: <error>{$e->getMessage()}</error>");
        }

        $this->newLine();
    }

    /**
     * Show index suggestions.
     */
    protected function showIndexSuggestions(): void
    {
        $this->info('📋 Index Suggestions');
        $this->line('-------------------');

        try {
            $suggestions = QueryOptimizer::getIndexSuggestions();

            if (empty($suggestions)) {
                $this->line("No index suggestions available.");
            } else {
                foreach ($suggestions as $suggestion) {
                    $this->line("Collection: <comment>{$suggestion['collection']}</comment>");
                    $this->line("Priority: <comment>{$suggestion['priority']}</comment>");
                    $this->line("Benefit: <comment>{$suggestion['estimated_benefit']}</comment>");
                    $this->line("Fields:");
                    foreach ($suggestion['fields'] as $field) {
                        $this->line("  - {$field['field']} ({$field['order']})");
                    }
                    $this->line("Firebase URL: <comment>{$suggestion['firebase_url']}</comment>");
                    $this->line("");
                }
            }

        } catch (\Exception $e) {
            $this->line("Error: <error>{$e->getMessage()}</error>");
        }

        $this->newLine();
    }

    /**
     * Perform comprehensive health check.
     */
    protected function performHealthCheck(): void
    {
        $this->info('🏥 Health Check');
        $this->line('---------------');

        $issues = [];
        $warnings = [];

        // Check connection
        try {
            FirestoreDB::getProjectId();
            $this->line("✓ Firestore connection: <info>OK</info>");
        } catch (\Exception $e) {
            $issues[] = "Firestore connection failed: {$e->getMessage()}";
        }

        // Check memory usage
        $memoryStats = MemoryManager::getMemoryStats();
        if ($memoryStats['usage_percentage'] > 90) {
            $issues[] = "High memory usage: {$memoryStats['usage_percentage']}%";
        } elseif ($memoryStats['usage_percentage'] > 75) {
            $warnings[] = "Elevated memory usage: {$memoryStats['usage_percentage']}%";
        } else {
            $this->line("✓ Memory usage: <info>OK ({$memoryStats['usage_percentage']}%)</info>");
        }

        // Check query performance
        $queryStats = QueryOptimizer::getQueryStats();
        if ($queryStats['avg_execution_time_ms'] > 1000) {
            $issues[] = "Slow average query time: {$queryStats['avg_execution_time_ms']}ms";
        } elseif ($queryStats['avg_execution_time_ms'] > 500) {
            $warnings[] = "Elevated query time: {$queryStats['avg_execution_time_ms']}ms";
        } else {
            $this->line("✓ Query performance: <info>OK ({$queryStats['avg_execution_time_ms']}ms avg)</info>");
        }

        // Display issues and warnings
        if (!empty($issues)) {
            $this->line("\n<error>Issues Found:</error>");
            foreach ($issues as $issue) {
                $this->line("  ✗ {$issue}");
            }
        }

        if (!empty($warnings)) {
            $this->line("\n<comment>Warnings:</comment>");
            foreach ($warnings as $warning) {
                $this->line("  ⚠ {$warning}");
            }
        }

        if (empty($issues) && empty($warnings)) {
            $this->line("\n<info>✓ All systems healthy!</info>");
        }

        $this->newLine();
    }

    /**
     * Check if any option is set.
     */
    protected function hasAnyOption(): bool
    {
        return $this->option('connection') ||
               $this->option('performance') ||
               $this->option('memory') ||
               $this->option('cache') ||
               $this->option('queries') ||
               $this->option('indexes') ||
               $this->option('config') ||
               $this->option('health');
    }
}
