<?php

namespace JTD\FirebaseModels\Tests\Helpers;

/**
 * FirestoreMockFactory provides a centralized way to create and manage
 * different types of Firestore mocks based on testing requirements.
 */
class FirestoreMockFactory
{
    public const TYPE_FULL = 'full';
    public const TYPE_LIGHTWEIGHT = 'lightweight';
    public const TYPE_ULTRA = 'ultra';

    private static array $instances = [];
    private static string $defaultType = self::TYPE_FULL;

    /**
     * Create or get a mock instance of the specified type.
     */
    public static function create(?string $type = null)
    {
        $type = $type ?? self::$defaultType;

        if (!isset(self::$instances[$type])) {
            self::$instances[$type] = self::createMockInstance($type);
        }

        return self::$instances[$type];
    }

    /**
     * Set the default mock type for new instances.
     */
    public static function setDefaultType(string $type): void
    {
        if (!in_array($type, [self::TYPE_FULL, self::TYPE_LIGHTWEIGHT, self::TYPE_ULTRA])) {
            throw new \InvalidArgumentException("Invalid mock type: {$type}");
        }
        
        self::$defaultType = $type;
    }

    /**
     * Get the current default mock type.
     */
    public static function getDefaultType(): string
    {
        return self::$defaultType;
    }

    /**
     * Clear all mock instances.
     */
    public static function clearAll(): void
    {
        foreach (self::$instances as $instance) {
            $instance::clear();
        }
        self::$instances = [];
    }

    /**
     * Clear a specific mock type.
     */
    public static function clear(string $type): void
    {
        if (isset(self::$instances[$type])) {
            self::$instances[$type]::clear();
            unset(self::$instances[$type]);
        }
    }

    /**
     * Get information about all available mock types.
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_FULL => [
                'class' => FirestoreMock::class,
                'description' => 'Full-featured mock with Mockery support',
                'memory_efficiency' => 1,
                'feature_completeness' => 3,
                'use_case' => 'Comprehensive testing with full Mockery features'
            ],
            self::TYPE_LIGHTWEIGHT => [
                'class' => LightweightFirestoreMock::class,
                'description' => 'Lightweight mock without heavy Mockery usage',
                'memory_efficiency' => 2,
                'feature_completeness' => 2,
                'use_case' => 'Memory-conscious testing with good feature coverage'
            ],
            self::TYPE_ULTRA => [
                'class' => UltraLightFirestoreMock::class,
                'description' => 'Ultra-lightweight mock with minimal memory footprint',
                'memory_efficiency' => 3,
                'feature_completeness' => 2,
                'use_case' => 'High-volume testing with maximum memory efficiency'
            ]
        ];
    }

    /**
     * Get memory usage comparison across all mock types.
     */
    public static function getMemoryComparison(): array
    {
        $comparison = [];
        
        foreach ([self::TYPE_FULL, self::TYPE_LIGHTWEIGHT, self::TYPE_ULTRA] as $type) {
            if (isset(self::$instances[$type])) {
                $comparison[$type] = self::$instances[$type]->getMemoryUsage();
            }
        }
        
        return $comparison;
    }

    /**
     * Recommend the best mock type based on testing requirements.
     */
    public static function recommendType(array $requirements): string
    {
        $documentCount = $requirements['document_count'] ?? 0;
        $needsFullMockery = $requirements['needs_full_mockery'] ?? false;
        $memoryConstraint = $requirements['memory_constraint'] ?? false;

        // If full Mockery features are required
        if ($needsFullMockery) {
            return self::TYPE_FULL;
        }

        // If memory is constrained or high document count
        if ($memoryConstraint || $documentCount > 1000) {
            return self::TYPE_ULTRA;
        }

        // If moderate document count
        if ($documentCount > 100) {
            return self::TYPE_LIGHTWEIGHT;
        }

        // Default to full for small tests
        return self::TYPE_FULL;
    }

    /**
     * Create a mock instance of the specified type.
     */
    private static function createMockInstance(string $type)
    {
        switch ($type) {
            case self::TYPE_FULL:
                FirestoreMock::initialize();
                return FirestoreMock::getInstance();

            case self::TYPE_LIGHTWEIGHT:
                LightweightFirestoreMock::initialize();
                return LightweightFirestoreMock::getInstance();

            case self::TYPE_ULTRA:
                UltraLightFirestoreMock::initialize();
                return UltraLightFirestoreMock::getInstance();

            default:
                throw new \InvalidArgumentException("Unknown mock type: {$type}");
        }
    }

    /**
     * Get performance benchmarks for different mock types.
     */
    public static function getBenchmarks(): array
    {
        return [
            'memory_usage' => [
                self::TYPE_FULL => 'High (Mockery overhead)',
                self::TYPE_LIGHTWEIGHT => 'Medium (Anonymous classes)',
                self::TYPE_ULTRA => 'Low (Concrete classes only)'
            ],
            'initialization_speed' => [
                self::TYPE_FULL => 'Slow (Mockery setup)',
                self::TYPE_LIGHTWEIGHT => 'Medium (Class creation)',
                self::TYPE_ULTRA => 'Fast (Direct instantiation)'
            ],
            'feature_support' => [
                self::TYPE_FULL => 'Complete (All Mockery features)',
                self::TYPE_LIGHTWEIGHT => 'Good (Core features)',
                self::TYPE_ULTRA => 'Basic (Essential features)'
            ]
        ];
    }
}
