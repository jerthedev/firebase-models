<?php

/**
 * Test Runner for Restructured Test Organization
 *
 * This script demonstrates the new test organization structure
 * and provides optimized test execution based on suite types.
 */

require_once __DIR__.'/../vendor/autoload.php';

use JTD\FirebaseModels\Tests\Utilities\TestConfigManager;

class RestructuredTestRunner
{
    private TestConfigManager $config;

    private array $suiteConfigs;

    private string $baseCommand;

    public function __construct()
    {
        $this->config = TestConfigManager::getInstance();
        $this->config->applyMemoryLimit();
        $this->baseCommand = 'php vendor/bin/phpunit -c tests/phpunit-restructured.xml';

        $this->setupSuiteConfigurations();
    }

    /**
     * Setup configurations for different test suites.
     */
    private function setupSuiteConfigurations(): void
    {
        $this->suiteConfigs = [
            'Unit-UltraLight' => [
                'description' => 'Ultra-fast unit tests with minimal memory usage',
                'memory_limit' => '128M',
                'timeout' => 30,
                'parallel' => true,
                'mock_type' => 'ultra',
            ],
            'Integration-Lightweight' => [
                'description' => 'Integration tests with balanced performance',
                'memory_limit' => '256M',
                'timeout' => 60,
                'parallel' => false,
                'mock_type' => 'lightweight',
            ],
            'Feature-Full' => [
                'description' => 'Feature tests with full mock capabilities',
                'memory_limit' => '512M',
                'timeout' => 120,
                'parallel' => false,
                'mock_type' => 'full',
            ],
            'Performance' => [
                'description' => 'Performance and memory tests',
                'memory_limit' => '256M',
                'timeout' => 180,
                'parallel' => false,
                'mock_type' => 'ultra',
            ],
            'Legacy' => [
                'description' => 'Legacy tests (to be migrated)',
                'memory_limit' => '512M',
                'timeout' => 120,
                'parallel' => false,
                'mock_type' => 'full',
            ],
        ];
    }

    /**
     * Run all test suites in optimized order.
     */
    public function runAll(): void
    {
        $this->printHeader();
        $this->printConfiguration();

        $totalStartTime = microtime(true);
        $results = [];

        // Run suites in order of speed (fastest first)
        $suiteOrder = ['Unit-UltraLight', 'Performance', 'Integration-Lightweight', 'Feature-Full', 'Legacy'];

        foreach ($suiteOrder as $suite) {
            $results[$suite] = $this->runSuite($suite);
        }

        $totalTime = microtime(true) - $totalStartTime;
        $this->printSummary($results, $totalTime);
    }

    /**
     * Run a specific test suite.
     */
    public function runSuite(string $suiteName): array
    {
        if (!isset($this->suiteConfigs[$suiteName])) {
            throw new InvalidArgumentException("Unknown test suite: {$suiteName}");
        }

        $config = $this->suiteConfigs[$suiteName];

        echo "\n".str_repeat('=', 80)."\n";
        echo "Running Test Suite: {$suiteName}\n";
        echo "Description: {$config['description']}\n";
        echo "Memory Limit: {$config['memory_limit']}\n";
        echo "Mock Type: {$config['mock_type']}\n";
        echo str_repeat('=', 80)."\n";

        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Set environment variables for this suite
        putenv("TEST_MOCK_TYPE={$config['mock_type']}");
        putenv("TEST_MEMORY_LIMIT={$config['memory_limit']}");
        putenv("TEST_TIMEOUT={$config['timeout']}");

        // Build and execute command
        $command = $this->buildCommand($suiteName, $config);
        $output = [];
        $returnCode = 0;

        exec($command.' 2>&1', $output, $returnCode);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();

        $result = [
            'suite' => $suiteName,
            'success' => $returnCode === 0,
            'duration' => $endTime - $startTime,
            'memory_used' => $endMemory - $startMemory,
            'output' => $output,
            'return_code' => $returnCode,
        ];

        $this->printSuiteResult($result);

        return $result;
    }

    /**
     * Build command for specific suite.
     */
    private function buildCommand(string $suiteName, array $config): string
    {
        $command = $this->baseCommand;
        $command .= " --testsuite={$suiteName}";

        // Add memory limit
        $command = "php -d memory_limit={$config['memory_limit']} ".substr($command, 4);

        // Add timeout if supported
        if (function_exists('pcntl_alarm')) {
            $command = "timeout {$config['timeout']} ".$command;
        }

        return $command;
    }

    /**
     * Print header information.
     */
    private function printHeader(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                    RESTRUCTURED TEST ORGANIZATION RUNNER                    ║\n";
        echo "║                                                                              ║\n";
        echo "║  This runner demonstrates the new test organization with optimized          ║\n";
        echo "║  execution based on test suite types and memory requirements.              ║\n";
        echo "╚══════════════════════════════════════════════════════════════════════════════╝\n";
    }

    /**
     * Print current configuration.
     */
    private function printConfiguration(): void
    {
        $config = $this->config->getConfigSummary();

        echo "\nCurrent Configuration:\n";
        echo "  Environment: {$config['environment']}\n";
        echo "  Default Mock Type: {$config['mock_type']}\n";
        echo "  Memory Limit: {$config['memory_limit']}\n";
        echo "  Timeout: {$config['timeout']}s\n";
        echo '  CI Mode: '.($config['is_ci'] ? 'Yes' : 'No')."\n";
        echo '  Logging: '.($config['logging_enabled'] ? 'Enabled' : 'Disabled')."\n";
        echo '  Profiling: '.($config['profiling_enabled'] ? 'Enabled' : 'Disabled')."\n";
    }

    /**
     * Print result for a single suite.
     */
    private function printSuiteResult(array $result): void
    {
        $status = $result['success'] ? '✅ PASSED' : '❌ FAILED';
        $duration = number_format($result['duration'], 2);
        $memory = $this->formatBytes($result['memory_used']);

        echo "\nResult: {$status}\n";
        echo "Duration: {$duration}s\n";
        echo "Memory Used: {$memory}\n";

        if (!$result['success']) {
            echo "\nError Output:\n";
            echo implode("\n", array_slice($result['output'], -10)); // Last 10 lines
        }
    }

    /**
     * Print summary of all results.
     */
    private function printSummary(array $results, float $totalTime): void
    {
        echo "\n".str_repeat('=', 80)."\n";
        echo "TEST EXECUTION SUMMARY\n";
        echo str_repeat('=', 80)."\n";

        $passed = 0;
        $failed = 0;

        foreach ($results as $result) {
            $status = $result['success'] ? '✅' : '❌';
            $duration = number_format($result['duration'], 2);
            $memory = $this->formatBytes($result['memory_used']);

            echo sprintf(
                "%s %-20s %6ss %10s\n",
                $status,
                $result['suite'],
                $duration,
                $memory
            );

            if ($result['success']) {
                $passed++;
            } else {
                $failed++;
            }
        }

        echo str_repeat('-', 80)."\n";
        echo sprintf(
            "Total: %d suites, %d passed, %d failed in %.2fs\n",
            count($results),
            $passed,
            $failed,
            $totalTime
        );

        if ($failed > 0) {
            echo "\n❌ Some test suites failed. Check the output above for details.\n";
            exit(1);
        } else {
            echo "\n✅ All test suites passed successfully!\n";
        }
    }

    /**
     * Format bytes for human-readable output.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 1).$units[$unitIndex];
    }

    /**
     * List available test suites.
     */
    public function listSuites(): void
    {
        echo "Available Test Suites:\n\n";

        foreach ($this->suiteConfigs as $name => $config) {
            echo "  {$name}\n";
            echo "    Description: {$config['description']}\n";
            echo "    Mock Type: {$config['mock_type']}\n";
            echo "    Memory Limit: {$config['memory_limit']}\n";
            echo "\n";
        }
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $runner = new RestructuredTestRunner();

    $command = $argv[1] ?? 'all';

    switch ($command) {
        case 'all':
            $runner->runAll();
            break;

        case 'list':
            $runner->listSuites();
            break;

        default:
            if (in_array($command, ['Unit-UltraLight', 'Integration-Lightweight', 'Feature-Full', 'Performance', 'Legacy'])) {
                $result = $runner->runSuite($command);
                exit($result['success'] ? 0 : 1);
            } else {
                echo "Usage: php run-restructured-tests.php [all|list|suite-name]\n";
                echo "Available suites: Unit-UltraLight, Integration-Lightweight, Feature-Full, Performance, Legacy\n";
                exit(1);
            }
    }
}
