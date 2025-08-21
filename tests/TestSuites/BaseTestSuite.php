<?php

namespace JTD\FirebaseModels\Tests\TestSuites;

use JTD\FirebaseModels\Tests\TestCase;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMockFactory;

/**
 * BaseTestSuite provides a standardized foundation for all test suites
 * with consistent setup, teardown, and resource management.
 */
abstract class BaseTestSuite extends TestCase
{
    protected string $mockType = FirestoreMockFactory::TYPE_FULL;
    protected array $testRequirements = [];
    protected bool $autoCleanup = true;
    protected array $memorySnapshots = [];

    /**
     * Set up the test suite with appropriate mock type and configuration.
     */
    protected function setUp(): void
    {
        // Take initial memory snapshot
        $this->memorySnapshots['setup_start'] = memory_get_usage();

        // Call parent setup first
        parent::setUp();

        // Configure mock type based on test requirements
        $this->configureMockType();

        // Take post-setup memory snapshot
        $this->memorySnapshots['setup_end'] = memory_get_usage();
    }

    /**
     * Clean up resources after each test.
     */
    protected function tearDown(): void
    {
        // Take pre-cleanup memory snapshot
        $this->memorySnapshots['teardown_start'] = memory_get_usage();

        // Perform automatic cleanup if enabled
        if ($this->autoCleanup) {
            $this->performCleanup();
        }

        // Call parent teardown
        parent::tearDown();

        // Take final memory snapshot and log if needed
        $this->memorySnapshots['teardown_end'] = memory_get_usage();
        $this->logMemoryUsageIfNeeded();
    }

    /**
     * Configure the appropriate mock type based on test requirements.
     */
    protected function configureMockType(): void
    {
        // Auto-select mock type if not explicitly set
        if ($this->mockType === FirestoreMockFactory::TYPE_FULL && !empty($this->testRequirements)) {
            $this->mockType = FirestoreMockFactory::recommendType($this->testRequirements);
        }

        // Apply the mock type
        switch ($this->mockType) {
            case FirestoreMockFactory::TYPE_ULTRA:
                $this->enableUltraLightMock();
                break;
            case FirestoreMockFactory::TYPE_LIGHTWEIGHT:
                $this->enableLightweightMock();
                break;
            default:
                $this->enableFullMock();
                break;
        }
    }

    /**
     * Perform cleanup operations.
     */
    protected function performCleanup(): void
    {
        // Clear Firestore mocks
        $this->clearFirestoreMocks();

        // Force garbage collection for memory-intensive tests
        if ($this->shouldForceGarbageCollection()) {
            $this->forceGarbageCollection();
        }
    }

    /**
     * Determine if garbage collection should be forced.
     */
    protected function shouldForceGarbageCollection(): bool
    {
        $memoryUsage = memory_get_usage();
        $memoryLimit = $this->getMemoryLimit();
        
        // Force GC if using more than 70% of available memory
        return $memoryUsage > ($memoryLimit * 0.7);
    }

    /**
     * Get the memory limit in bytes.
     */
    protected function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        return $this->convertToBytes($limit);
    }

    /**
     * Convert memory limit string to bytes.
     */
    protected function convertToBytes(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Log memory usage if it exceeds thresholds.
     */
    protected function logMemoryUsageIfNeeded(): void
    {
        $peakUsage = memory_get_peak_usage();
        $currentUsage = memory_get_usage();
        $memoryLimit = $this->getMemoryLimit();
        
        // Log if peak usage exceeds 50% of limit
        if ($peakUsage > ($memoryLimit * 0.5)) {
            error_log(sprintf(
                'High memory usage in %s: Peak=%s, Current=%s, Limit=%s',
                static::class,
                $this->formatBytes($peakUsage),
                $this->formatBytes($currentUsage),
                $this->formatBytes($memoryLimit)
            ));
        }
    }

    /**
     * Format bytes for human-readable output.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Get memory usage statistics for this test.
     */
    protected function getMemoryStats(): array
    {
        return [
            'snapshots' => $this->memorySnapshots,
            'current_usage' => memory_get_usage(),
            'peak_usage' => memory_get_peak_usage(),
            'mock_type' => $this->mockType,
        ];
    }

    /**
     * Set test requirements for automatic mock selection.
     */
    protected function setTestRequirements(array $requirements): void
    {
        $this->testRequirements = $requirements;
    }

    /**
     * Disable automatic cleanup for tests that need manual control.
     */
    protected function disableAutoCleanup(): void
    {
        $this->autoCleanup = false;
    }

    /**
     * Get the recommended mock type for the current test requirements.
     */
    protected function getRecommendedMockType(): string
    {
        return FirestoreMockFactory::recommendType($this->testRequirements);
    }
}
