<?php

namespace JTD\FirebaseModels\Tests\E2E;

/**
 * Configuration helper for E2E testing.
 */
class E2ETestConfig
{
    /**
     * Check if E2E testing is available.
     */
    public static function isAvailable(): bool
    {
        return file_exists(self::getCredentialsPath());
    }

    /**
     * Get the path to E2E credentials.
     */
    public static function getCredentialsPath(): string
    {
        return base_path('tests/credentials/e2e-credentials.json');
    }

    /**
     * Load E2E credentials.
     */
    public static function getCredentials(): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        $credentials = json_decode(file_get_contents(self::getCredentialsPath()), true);

        if (!$credentials || !isset($credentials['project_id'])) {
            return null;
        }

        return $credentials;
    }

    /**
     * Get the project ID from credentials.
     */
    public static function getProjectId(): ?string
    {
        $credentials = self::getCredentials();

        return $credentials['project_id'] ?? null;
    }

    /**
     * Get test configuration for different modes.
     */
    public static function getTestModeConfig(string $mode): array
    {
        $baseConfig = [
            'firebase.mock_mode' => false,
            'cache.default' => 'array',
            'firebase-models.cache.store' => 'array',
            'firebase-models.cache.enabled' => true,
        ];

        return match ($mode) {
            'cloud' => array_merge($baseConfig, [
                'firebase-models.mode' => 'cloud',
                'firebase-models.sync.enabled' => false,
            ]),
            'sync' => array_merge($baseConfig, [
                'firebase-models.mode' => 'sync',
                'firebase-models.sync.enabled' => true,
                'firebase-models.sync.read_strategy' => 'local_first',
                'firebase-models.sync.write_strategy' => 'both',
            ]),
            default => $baseConfig,
        };
    }

    /**
     * Get test collections configuration.
     */
    public static function getTestCollections(): array
    {
        return [
            'users' => 'e2e_users',
            'posts' => 'e2e_posts',
            'comments' => 'e2e_comments',
            'categories' => 'e2e_categories',
            'tags' => 'e2e_tags',
        ];
    }

    /**
     * Get test user data templates.
     */
    public static function getTestUserTemplates(): array
    {
        return [
            'basic' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'active' => true,
            ],
            'admin' => [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'active' => true,
                'permissions' => ['read', 'write', 'delete'],
            ],
            'inactive' => [
                'name' => 'Inactive User',
                'email' => 'inactive@example.com',
                'active' => false,
            ],
        ];
    }

    /**
     * Get performance test configuration.
     */
    public static function getPerformanceConfig(): array
    {
        return [
            'batch_sizes' => [10, 50, 100],
            'query_limits' => [10, 25, 50, 100],
            'cache_ttl_values' => [60, 300, 900], // 1min, 5min, 15min
            'concurrent_operations' => [1, 5, 10],
        ];
    }

    /**
     * Get cleanup configuration.
     */
    public static function getCleanupConfig(): array
    {
        return [
            'max_cleanup_attempts' => 3,
            'cleanup_batch_size' => 100,
            'cleanup_delay_ms' => 100,
            'preserve_collections' => [], // Collections to never delete
        ];
    }
}
