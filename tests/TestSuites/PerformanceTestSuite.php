<?php

namespace JTD\FirebaseModels\Tests\TestSuites;

use JTD\FirebaseModels\Tests\Helpers\FirestoreMockFactory;

/**
 * PerformanceTestSuite is specialized for performance and memory testing
 * with comprehensive monitoring and optimization features.
 */
abstract class PerformanceTestSuite extends BaseTestSuite
{
    protected string $mockType = FirestoreMockFactory::TYPE_ULTRA;
    protected bool $autoCleanup = true;
    protected array $performanceMetrics = [];
    protected array $memoryThresholds = [];

    /**
     * Configure performance test requirements.
     */
    protected function setUp(): void
    {
        // Set requirements for performance testing
        $this->setTestRequirements([
            'document_count' => 1000,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        // Set default memory thresholds
        $this->memoryThresholds = [
            'warning' => 50 * 1024 * 1024,  // 50MB
            'critical' => 100 * 1024 * 1024, // 100MB
        ];

        parent::setUp();
        
        // Start performance monitoring
        $this->startPerformanceMonitoring();
    }

    /**
     * Enhanced teardown with performance reporting.
     */
    protected function tearDown(): void
    {
        // Stop performance monitoring
        $this->stopPerformanceMonitoring();
        
        // Report performance metrics if needed
        $this->reportPerformanceMetrics();
        
        parent::tearDown();
    }

    /**
     * Start performance monitoring.
     */
    protected function startPerformanceMonitoring(): void
    {
        $this->performanceMetrics = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(),
            'start_peak_memory' => memory_get_peak_usage(),
            'operations' => [],
            'checkpoints' => [],
        ];
    }

    /**
     * Stop performance monitoring.
     */
    protected function stopPerformanceMonitoring(): void
    {
        $this->performanceMetrics['end_time'] = microtime(true);
        $this->performanceMetrics['end_memory'] = memory_get_usage();
        $this->performanceMetrics['end_peak_memory'] = memory_get_peak_usage();
        
        // Calculate totals
        $this->performanceMetrics['total_time'] = 
            $this->performanceMetrics['end_time'] - $this->performanceMetrics['start_time'];
        $this->performanceMetrics['memory_delta'] = 
            $this->performanceMetrics['end_memory'] - $this->performanceMetrics['start_memory'];
        $this->performanceMetrics['peak_memory_delta'] = 
            $this->performanceMetrics['end_peak_memory'] - $this->performanceMetrics['start_peak_memory'];
    }

    /**
     * Add a performance checkpoint.
     */
    protected function addPerformanceCheckpoint(string $name): void
    {
        $this->performanceMetrics['checkpoints'][$name] = [
            'time' => microtime(true),
            'memory' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage(),
        ];
    }

    /**
     * Measure the performance of an operation.
     */
    protected function measureOperation(string $name, callable $operation): mixed
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $result = $operation();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $this->performanceMetrics['operations'][$name] = [
            'duration' => $endTime - $startTime,
            'memory_delta' => $endMemory - $startMemory,
            'start_memory' => $startMemory,
            'end_memory' => $endMemory,
        ];
        
        return $result;
    }

    /**
     * Create a large dataset for performance testing.
     */
    protected function createLargeDataset(string $collection, int $count = 1000): array
    {
        return $this->measureOperation("create_large_dataset_{$count}", function() use ($collection, $count) {
            $documents = [];
            
            for ($i = 0; $i < $count; $i++) {
                $data = [
                    'id' => 'perf_test_' . $i,
                    'index' => $i,
                    'name' => 'Performance Test Document ' . $i,
                    'category' => 'category_' . ($i % 10),
                    'status' => $i % 2 === 0 ? 'active' : 'inactive',
                    'score' => rand(1, 100),
                    'created_at' => now()->subMinutes($i),
                    'metadata' => [
                        'batch' => floor($i / 100),
                        'group' => $i % 5,
                        'tags' => array_map(fn($j) => 'tag_' . $j, range(0, $i % 3)),
                        'large_text' => str_repeat('Lorem ipsum dolor sit amet. ', 10),
                    ],
                ];
                
                $this->getFirestoreMock()->storeDocument($collection, $data['id'], $data);
                $documents[] = $data;
                
                // Add checkpoint every 100 documents
                if ($i % 100 === 0) {
                    $this->addPerformanceCheckpoint("created_{$i}_documents");
                }
            }
            
            return $documents;
        });
    }

    /**
     * Perform bulk operations for performance testing.
     */
    protected function performBulkOperations(string $collection, int $count = 500): array
    {
        return $this->measureOperation("bulk_operations_{$count}", function() use ($collection, $count) {
            $results = [];
            
            // Bulk create
            for ($i = 0; $i < $count; $i++) {
                $data = ['id' => 'bulk_' . $i, 'value' => $i];
                $this->getFirestoreMock()->storeDocument($collection, $data['id'], $data);
                $results['created'][] = $data['id'];
            }
            
            // Bulk read
            for ($i = 0; $i < $count; $i++) {
                $doc = $this->getFirestoreMock()->getDocument($collection, 'bulk_' . $i);
                $results['read'][] = $doc;
            }
            
            // Bulk update
            for ($i = 0; $i < $count; $i++) {
                $existing = $this->getFirestoreMock()->getDocument($collection, 'bulk_' . $i);
                $existing['updated'] = true;
                $this->getFirestoreMock()->storeDocument($collection, 'bulk_' . $i, $existing);
                $results['updated'][] = 'bulk_' . $i;
            }
            
            // Bulk delete
            for ($i = 0; $i < $count; $i++) {
                $this->getFirestoreMock()->deleteDocument($collection, 'bulk_' . $i);
                $results['deleted'][] = 'bulk_' . $i;
            }
            
            return $results;
        });
    }

    /**
     * Assert that operation performance is within acceptable limits.
     */
    protected function assertPerformanceWithinLimits(string $operationName, float $maxSeconds, int $maxMemoryBytes): void
    {
        $operation = $this->performanceMetrics['operations'][$operationName] ?? null;
        
        $this->assertNotNull($operation, "Operation {$operationName} was not measured");
        
        $this->assertLessThanOrEqual(
            $maxSeconds,
            $operation['duration'],
            "Operation {$operationName} took {$operation['duration']}s, exceeding limit of {$maxSeconds}s"
        );
        
        $this->assertLessThanOrEqual(
            $maxMemoryBytes,
            $operation['memory_delta'],
            "Operation {$operationName} used {$this->formatBytes($operation['memory_delta'])}, exceeding limit of {$this->formatBytes($maxMemoryBytes)}"
        );
    }

    /**
     * Assert that memory usage stays within thresholds.
     */
    protected function assertMemoryWithinThresholds(): void
    {
        $currentMemory = memory_get_usage();
        $peakMemory = memory_get_peak_usage();
        
        if (isset($this->memoryThresholds['critical'])) {
            $this->assertLessThan(
                $this->memoryThresholds['critical'],
                $peakMemory,
                "Peak memory usage ({$this->formatBytes($peakMemory)}) exceeded critical threshold ({$this->formatBytes($this->memoryThresholds['critical'])})"
            );
        }
        
        if (isset($this->memoryThresholds['warning']) && $currentMemory > $this->memoryThresholds['warning']) {
            $this->addWarning(
                "Current memory usage ({$this->formatBytes($currentMemory)}) exceeded warning threshold ({$this->formatBytes($this->memoryThresholds['warning'])})"
            );
        }
    }

    /**
     * Report performance metrics if they exceed thresholds.
     */
    protected function reportPerformanceMetrics(): void
    {
        $totalTime = $this->performanceMetrics['total_time'] ?? 0;
        $memoryDelta = $this->performanceMetrics['memory_delta'] ?? 0;
        $peakMemoryDelta = $this->performanceMetrics['peak_memory_delta'] ?? 0;
        
        // Report if test took longer than 5 seconds or used more than 10MB
        if ($totalTime > 5.0 || abs($memoryDelta) > 10 * 1024 * 1024) {
            error_log(sprintf(
                'Performance report for %s: Time=%.3fs, Memory=%s, Peak=%s',
                static::class,
                $totalTime,
                $this->formatBytes($memoryDelta),
                $this->formatBytes($peakMemoryDelta)
            ));
        }
    }

    /**
     * Set custom memory thresholds for the test.
     */
    protected function setMemoryThresholds(array $thresholds): void
    {
        $this->memoryThresholds = array_merge($this->memoryThresholds, $thresholds);
    }

    /**
     * Get detailed performance report.
     */
    protected function getPerformanceReport(): array
    {
        return [
            'summary' => [
                'total_time' => $this->performanceMetrics['total_time'] ?? 0,
                'memory_delta' => $this->performanceMetrics['memory_delta'] ?? 0,
                'peak_memory_delta' => $this->performanceMetrics['peak_memory_delta'] ?? 0,
            ],
            'operations' => $this->performanceMetrics['operations'] ?? [],
            'checkpoints' => $this->performanceMetrics['checkpoints'] ?? [],
            'mock_type' => $this->mockType,
            'memory_stats' => $this->getMemoryStats(),
        ];
    }
}
