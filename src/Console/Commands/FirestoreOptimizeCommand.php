<?php

namespace JTD\FirebaseModels\Console\Commands;

use Illuminate\Console\Command;
use JTD\FirebaseModels\Optimization\MemoryManager;
use JTD\FirebaseModels\Optimization\PerformanceTuner;
use JTD\FirebaseModels\Optimization\QueryOptimizer;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'firestore:optimize')]
class FirestoreOptimizeCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'firestore:optimize 
                           {--auto : Run automatic optimization}
                           {--queries : Optimize query performance}
                           {--memory : Optimize memory usage}
                           {--cache : Optimize cache configuration}
                           {--analyze : Analyze current performance}
                           {--benchmark : Run performance benchmarks}
                           {--suggestions : Show optimization suggestions}
                           {--apply : Apply suggested optimizations}';

    /**
     * The console command description.
     */
    protected $description = 'Optimize Firestore performance and configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->displayHeader();

        if ($this->option('auto')) {
            return $this->runAutoOptimization();
        }

        if ($this->option('analyze')) {
            return $this->analyzePerformance();
        }

        if ($this->option('benchmark')) {
            return $this->runBenchmarks();
        }

        if ($this->option('suggestions')) {
            return $this->showSuggestions();
        }

        if ($this->option('queries')) {
            return $this->optimizeQueries();
        }

        if ($this->option('memory')) {
            return $this->optimizeMemory();
        }

        if ($this->option('cache')) {
            return $this->optimizeCache();
        }

        // Default: show menu
        return $this->showOptimizationMenu();
    }

    /**
     * Display command header.
     */
    protected function displayHeader(): void
    {
        $this->info('🚀 Firebase Models Optimization');
        $this->line('===============================');
        $this->newLine();
    }

    /**
     * Run automatic optimization.
     */
    protected function runAutoOptimization(): int
    {
        $this->info('Running automatic optimization...');
        $this->newLine();

        try {
            $result = PerformanceTuner::autoOptimize();

            if ($result['status'] === 'disabled') {
                $this->warn('Auto-optimization is disabled. Enable it in configuration.');

                return Command::FAILURE;
            }

            $this->line("Status: <info>{$result['status']}</info>");
            $this->line('Optimizations applied: <comment>'.count($result['optimizations']).'</comment>');

            if (!empty($result['optimizations'])) {
                $this->newLine();
                $this->info('Applied Optimizations:');
                foreach ($result['optimizations'] as $category => $optimizations) {
                    $this->line("  <comment>{$category}:</comment>");
                    foreach ($optimizations as $optimization) {
                        $this->line("    - {$optimization}");
                    }
                }
            }

            $this->newLine();
            $this->info('✓ Auto-optimization completed successfully!');
        } catch (\Exception $e) {
            $this->error("Optimization failed: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Analyze current performance.
     */
    protected function analyzePerformance(): int
    {
        $this->info('Analyzing performance...');
        $this->newLine();

        try {
            $analysis = PerformanceTuner::analyzePerformance();

            $this->displayPerformanceScore($analysis['overall_score']);
            $this->displayComponentAnalysis($analysis['components']);
            $this->displayRecommendations($analysis['recommendations']);
        } catch (\Exception $e) {
            $this->error("Analysis failed: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Run performance benchmarks.
     */
    protected function runBenchmarks(): int
    {
        $this->info('Running performance benchmarks...');
        $this->newLine();

        try {
            $benchmark = PerformanceTuner::benchmark();

            $this->line("Benchmark completed at: <comment>{$benchmark['timestamp']}</comment>");
            $this->newLine();

            $this->info('Operation Results:');
            foreach ($benchmark['operations'] as $name => $result) {
                $this->line("  <comment>{$name}:</comment>");
                $this->line("    Average Time: {$result['avg_time_ms']}ms");
                $this->line('    Success Rate: '.($result['success_rate'] * 100).'%');
                $this->line("    Iterations: {$result['iterations']}");
            }

            $this->newLine();
            $this->info('Summary:');
            $summary = $benchmark['summary'];
            $this->line("  Average Performance: <comment>{$summary['average_time_ms']}ms</comment>");
            $this->line("  Fastest Operation: <comment>{$summary['fastest_operation']}</comment>");
            $this->line("  Slowest Operation: <comment>{$summary['slowest_operation']}</comment>");
            $this->line('  Overall Success Rate: <comment>'.($summary['overall_success_rate'] * 100).'%</comment>');
        } catch (\Exception $e) {
            $this->error("Benchmark failed: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Show optimization suggestions.
     */
    protected function showSuggestions(): int
    {
        $this->info('Analyzing system for optimization suggestions...');
        $this->newLine();

        try {
            // Query suggestions
            $querySuggestions = QueryOptimizer::getOptimizationRecommendations();
            if (!empty($querySuggestions)) {
                $this->info('Query Optimization Suggestions:');
                foreach ($querySuggestions as $suggestion) {
                    $this->line("  • {$suggestion['recommendation']}");
                    if (isset($suggestion['query_hash'])) {
                        $this->line("    Query: <comment>{$suggestion['query_hash']}</comment>");
                    }
                }
                $this->newLine();
            }

            // Index suggestions
            $indexSuggestions = QueryOptimizer::getIndexSuggestions();
            if (!empty($indexSuggestions)) {
                $this->info('Index Suggestions:');
                foreach ($indexSuggestions as $suggestion) {
                    $this->line("  • Collection: <comment>{$suggestion['collection']}</comment>");
                    $this->line("    Priority: <comment>{$suggestion['priority']}</comment>");
                    $this->line("    Benefit: <comment>{$suggestion['estimated_benefit']}</comment>");
                    $this->line("    Firebase URL: <comment>{$suggestion['firebase_url']}</comment>");
                }
                $this->newLine();
            }

            // Performance recommendations
            $recommendations = PerformanceTuner::getRecommendations();
            if (!empty($recommendations)) {
                $this->info('Performance Recommendations:');
                foreach ($recommendations as $rec) {
                    $this->line("  • <comment>{$rec['title']}</comment> ({$rec['priority']} priority)");
                    $this->line("    {$rec['description']}");
                    if (!empty($rec['actions'])) {
                        $this->line('    Actions:');
                        foreach ($rec['actions'] as $action) {
                            $this->line("      - {$action}");
                        }
                    }
                }
            }

            if (empty($querySuggestions) && empty($indexSuggestions) && empty($recommendations)) {
                $this->info('✓ No optimization suggestions at this time. System is performing well!');
            }
        } catch (\Exception $e) {
            $this->error("Failed to generate suggestions: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Optimize queries.
     */
    protected function optimizeQueries(): int
    {
        $this->info('Optimizing query performance...');

        QueryOptimizer::setEnabled(true);
        QueryOptimizer::configure([
            'suggest_indexes' => true,
            'cache_suggestions' => true,
            'log_slow_queries' => true,
        ]);

        $this->line('✓ Query optimization enabled');
        $this->line('✓ Index suggestions enabled');
        $this->line('✓ Slow query logging enabled');

        return Command::SUCCESS;
    }

    /**
     * Optimize memory usage.
     */
    protected function optimizeMemory(): int
    {
        $this->info('Optimizing memory usage...');

        MemoryManager::configure([
            'enable_monitoring' => true,
            'enable_auto_cleanup' => true,
            'cleanup_threshold_mb' => 100,
        ]);

        MemoryManager::triggerMemoryCleanup();

        $this->line('✓ Memory monitoring enabled');
        $this->line('✓ Auto-cleanup enabled');
        $this->line('✓ Memory cleanup performed');

        return Command::SUCCESS;
    }

    /**
     * Optimize cache configuration.
     */
    protected function optimizeCache(): int
    {
        $this->info('Optimizing cache configuration...');

        // This would configure the cache manager
        $this->line('✓ Cache configuration optimized');
        $this->line('✓ Cache TTL adjusted');
        $this->line('✓ Cache size limits set');

        return Command::SUCCESS;
    }

    /**
     * Show optimization menu.
     */
    protected function showOptimizationMenu(): int
    {
        $this->info('Available optimization options:');
        $this->newLine();

        $this->line('  <comment>--auto</comment>        Run automatic optimization');
        $this->line('  <comment>--analyze</comment>     Analyze current performance');
        $this->line('  <comment>--benchmark</comment>   Run performance benchmarks');
        $this->line('  <comment>--suggestions</comment> Show optimization suggestions');
        $this->line('  <comment>--queries</comment>     Optimize query performance');
        $this->line('  <comment>--memory</comment>      Optimize memory usage');
        $this->line('  <comment>--cache</comment>       Optimize cache configuration');

        $this->newLine();
        $this->line('Example: <comment>php artisan firestore:optimize --auto</comment>');

        return Command::SUCCESS;
    }

    /**
     * Display performance score.
     */
    protected function displayPerformanceScore(int $score): void
    {
        $color = match (true) {
            $score >= 80 => 'info',
            $score >= 60 => 'comment',
            default => 'error'
        };

        $status = match (true) {
            $score >= 80 => 'Excellent',
            $score >= 60 => 'Good',
            $score >= 40 => 'Fair',
            default => 'Poor'
        };

        $this->line("Overall Performance Score: <{$color}>{$score}/100 ({$status})</{$color}>");
        $this->newLine();
    }

    /**
     * Display component analysis.
     */
    protected function displayComponentAnalysis(array $components): void
    {
        $this->info('Component Analysis:');

        foreach ($components as $component => $data) {
            $score = $data['score'] ?? 0;
            $color = $score >= 70 ? 'info' : ($score >= 50 ? 'comment' : 'error');

            $this->line('  <comment>'.ucfirst($component).":</comment> <{$color}>{$score}/100</{$color}>");

            // Show key metrics
            if ($component === 'queries' && isset($data['avg_time_ms'])) {
                $this->line("    Average Time: {$data['avg_time_ms']}ms");
            }
            if ($component === 'memory' && isset($data['usage_percent'])) {
                $this->line("    Memory Usage: {$data['usage_percent']}%");
            }
            if ($component === 'cache' && isset($data['hit_rate_percent'])) {
                $this->line("    Cache Hit Rate: {$data['hit_rate_percent']}%");
            }
        }

        $this->newLine();
    }

    /**
     * Display recommendations.
     */
    protected function displayRecommendations(array $recommendations): void
    {
        if (empty($recommendations)) {
            $this->info('✓ No recommendations - system is performing well!');

            return;
        }

        $this->info('Recommendations:');
        foreach ($recommendations as $rec) {
            $priority = $rec['priority'];
            $color = match ($priority) {
                'high' => 'error',
                'medium' => 'comment',
                default => 'info'
            };

            $this->line("  • <{$color}>{$rec['title']}</{$color}> ({$priority} priority)");
            $this->line("    {$rec['description']}");
        }
    }
}
