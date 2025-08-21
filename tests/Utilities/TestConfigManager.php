<?php

namespace JTD\FirebaseModels\Tests\Utilities;

use JTD\FirebaseModels\Tests\Helpers\FirestoreMockFactory;

/**
 * TestConfigManager provides centralized configuration management
 * for test suites with environment-aware settings.
 */
class TestConfigManager
{
    private static ?self $instance = null;
    private array $config = [];
    private array $environmentDefaults = [];

    private function __construct()
    {
        $this->loadEnvironmentDefaults();
        $this->loadConfiguration();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Load environment-specific defaults.
     */
    private function loadEnvironmentDefaults(): void
    {
        $this->environmentDefaults = [
            'local' => [
                'mock_type' => FirestoreMockFactory::TYPE_FULL,
                'memory_limit' => '512M',
                'timeout' => 30,
                'enable_logging' => true,
                'enable_profiling' => true,
            ],
            'ci' => [
                'mock_type' => FirestoreMockFactory::TYPE_ULTRA,
                'memory_limit' => '256M',
                'timeout' => 60,
                'enable_logging' => false,
                'enable_profiling' => false,
            ],
            'github_actions' => [
                'mock_type' => FirestoreMockFactory::TYPE_ULTRA,
                'memory_limit' => '256M',
                'timeout' => 120,
                'enable_logging' => false,
                'enable_profiling' => false,
            ],
        ];
    }

    /**
     * Load configuration from environment and defaults.
     */
    private function loadConfiguration(): void
    {
        $environment = $this->detectEnvironment();
        $defaults = $this->environmentDefaults[$environment] ?? $this->environmentDefaults['local'];
        
        $this->config = array_merge($defaults, [
            'environment' => $environment,
            'mock_type' => $this->getEnvValue('TEST_MOCK_TYPE', $defaults['mock_type']),
            'memory_limit' => $this->getEnvValue('TEST_MEMORY_LIMIT', $defaults['memory_limit']),
            'timeout' => (int) $this->getEnvValue('TEST_TIMEOUT', $defaults['timeout']),
            'enable_logging' => $this->getBoolEnvValue('TEST_ENABLE_LOGGING', $defaults['enable_logging']),
            'enable_profiling' => $this->getBoolEnvValue('TEST_ENABLE_PROFILING', $defaults['enable_profiling']),
            'parallel_processes' => (int) $this->getEnvValue('TEST_PARALLEL_PROCESSES', 1),
            'memory_threshold_warning' => $this->parseMemoryValue($this->getEnvValue('TEST_MEMORY_WARNING', '50M')),
            'memory_threshold_critical' => $this->parseMemoryValue($this->getEnvValue('TEST_MEMORY_CRITICAL', '100M')),
        ]);
    }

    /**
     * Detect the current testing environment.
     */
    private function detectEnvironment(): string
    {
        if (getenv('GITHUB_ACTIONS') === 'true') {
            return 'github_actions';
        }
        
        if (getenv('CI') === 'true') {
            return 'ci';
        }
        
        return 'local';
    }

    /**
     * Get environment variable value with fallback.
     */
    private function getEnvValue(string $key, $default = null)
    {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }

    /**
     * Get boolean environment variable value.
     */
    private function getBoolEnvValue(string $key, bool $default = false): bool
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        
        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }

    /**
     * Parse memory value string to bytes.
     */
    private function parseMemoryValue(string $value): int
    {
        $unit = strtolower(substr($value, -1));
        $number = (int) substr($value, 0, -1);
        
        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }

    /**
     * Get configuration value.
     */
    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set configuration value.
     */
    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Get all configuration.
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * Get recommended mock type for current environment.
     */
    public function getRecommendedMockType(array $testRequirements = []): string
    {
        // If specific requirements provided, use factory recommendation
        if (!empty($testRequirements)) {
            return FirestoreMockFactory::recommendType($testRequirements);
        }
        
        // Otherwise use environment default
        return $this->get('mock_type');
    }

    /**
     * Get memory thresholds for current environment.
     */
    public function getMemoryThresholds(): array
    {
        return [
            'warning' => $this->get('memory_threshold_warning'),
            'critical' => $this->get('memory_threshold_critical'),
        ];
    }

    /**
     * Check if logging is enabled.
     */
    public function isLoggingEnabled(): bool
    {
        return $this->get('enable_logging', false);
    }

    /**
     * Check if profiling is enabled.
     */
    public function isProfilingEnabled(): bool
    {
        return $this->get('enable_profiling', false);
    }

    /**
     * Get test timeout in seconds.
     */
    public function getTimeout(): int
    {
        return $this->get('timeout', 30);
    }

    /**
     * Get number of parallel processes for testing.
     */
    public function getParallelProcesses(): int
    {
        return $this->get('parallel_processes', 1);
    }

    /**
     * Check if running in CI environment.
     */
    public function isCI(): bool
    {
        return in_array($this->get('environment'), ['ci', 'github_actions']);
    }

    /**
     * Get environment-specific test configuration.
     */
    public function getTestSuiteConfig(string $suiteType): array
    {
        $baseConfig = [
            'mock_type' => $this->getRecommendedMockType(),
            'memory_thresholds' => $this->getMemoryThresholds(),
            'timeout' => $this->getTimeout(),
            'logging_enabled' => $this->isLoggingEnabled(),
            'profiling_enabled' => $this->isProfilingEnabled(),
        ];

        // Suite-specific configurations
        $suiteConfigs = [
            'unit' => [
                'mock_type' => FirestoreMockFactory::TYPE_ULTRA,
                'document_count' => 50,
                'memory_constraint' => true,
                'needs_full_mockery' => false,
            ],
            'integration' => [
                'mock_type' => FirestoreMockFactory::TYPE_LIGHTWEIGHT,
                'document_count' => 200,
                'memory_constraint' => false,
                'needs_full_mockery' => false,
            ],
            'performance' => [
                'mock_type' => FirestoreMockFactory::TYPE_ULTRA,
                'document_count' => 1000,
                'memory_constraint' => true,
                'needs_full_mockery' => false,
            ],
            'feature' => [
                'mock_type' => $this->isCI() ? FirestoreMockFactory::TYPE_LIGHTWEIGHT : FirestoreMockFactory::TYPE_FULL,
                'document_count' => 100,
                'memory_constraint' => $this->isCI(),
                'needs_full_mockery' => !$this->isCI(),
            ],
        ];

        return array_merge($baseConfig, $suiteConfigs[$suiteType] ?? []);
    }

    /**
     * Apply memory limit for current environment.
     */
    public function applyMemoryLimit(): void
    {
        $memoryLimit = $this->get('memory_limit');
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
        }
    }

    /**
     * Get configuration summary for debugging.
     */
    public function getConfigSummary(): array
    {
        return [
            'environment' => $this->get('environment'),
            'mock_type' => $this->get('mock_type'),
            'memory_limit' => $this->get('memory_limit'),
            'timeout' => $this->get('timeout'),
            'is_ci' => $this->isCI(),
            'logging_enabled' => $this->isLoggingEnabled(),
            'profiling_enabled' => $this->isProfilingEnabled(),
        ];
    }

    /**
     * Reset configuration to defaults.
     */
    public function reset(): void
    {
        $this->loadConfiguration();
    }
}
