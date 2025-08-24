<?php

namespace JTD\FirebaseModels\Tests\Unit\Restructured;

use Google\Cloud\Firestore\FirestoreClient;
use JTD\FirebaseModels\Facades\FirestoreDB;
use JTD\FirebaseModels\JtdFirebaseModelsServiceProvider;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Test;

/**
 * Comprehensive Service Provider Test
 *
 * Migrated from:
 * - tests/Unit/ServiceProviderTest.php
 *
 * Uses new UnitTestSuite for optimized performance and memory management.
 */
class ServiceProviderTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for service provider operations
        $this->setTestRequirements([
            'document_count' => 10,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();
    }

    // ========================================
    // SERVICE REGISTRATION TESTS
    // ========================================

    #[Test]
    public function it_registers_services_correctly_in_container()
    {
        // Test FirestoreClient registration
        expect(app()->bound(FirestoreClient::class))->toBeTrue();

        $client = app(FirestoreClient::class);
        expect($client)->toBeInstanceOf(FirestoreClient::class);

        // Test FirestoreDB facade registration
        expect(class_exists('FirestoreDB'))->toBeTrue();

        $facade = app('FirestoreDB');
        expect($facade)->not->toBeNull();

        // Test service provider provides method
        $provider = new JtdFirebaseModelsServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toContain(FirestoreClient::class);
        expect($provides)->toContain('FirestoreDB');

        // Performance test for service resolution
        $executionTime = $this->benchmark(function () {
            $client = app(FirestoreClient::class);
            $facade = app('FirestoreDB');

            return [$client, $facade];
        });

        expect($executionTime)->toBeLessThan(0.01); // Service resolution should be fast
    }

    // ========================================
    // CONFIGURATION AND PUBLISHING TESTS
    // ========================================

    #[Test]
    public function it_handles_configuration_and_publishing_correctly()
    {
        $provider = new JtdFirebaseModelsServiceProvider(app());

        // Test that publishes method exists and can be called
        expect(method_exists($provider, 'publishes'))->toBeTrue();

        // Test configuration loading
        $configPath = config_path('firebase.php');

        // In testing, we check that the config is loaded
        expect(config('firebase.project_id'))->toBe('test-project');

        // Test configuration access performance
        $executionTime = $this->benchmark(function () {
            $projectId = config('firebase.project_id');
            $credentials = config('firebase.credentials');
            $database = config('firebase.database');

            return [$projectId, $credentials, $database];
        });

        expect($executionTime)->toBeLessThan(0.005); // Config access should be very fast
    }

    // ========================================
    // SERVICE PROVIDER LIFECYCLE TESTS
    // ========================================

    #[Test]
    public function it_boots_and_registers_correctly()
    {
        // Verify that the service provider boots without errors
        $provider = new JtdFirebaseModelsServiceProvider(app());

        expect(method_exists($provider, 'boot'))->toBeTrue();
        expect(method_exists($provider, 'register'))->toBeTrue();

        // Test that event dispatcher is properly registered
        $dispatcher = app('events');
        expect($dispatcher)->toBeInstanceOf(\Illuminate\Contracts\Events\Dispatcher::class);

        // Performance test for provider instantiation
        $executionTime = $this->benchmark(function () {
            return new JtdFirebaseModelsServiceProvider(app());
        });

        expect($executionTime)->toBeLessThan(0.005); // Provider creation should be very fast
    }

    // ========================================
    // ERROR HANDLING AND EDGE CASES
    // ========================================

    #[Test]
    public function it_handles_missing_configuration_gracefully()
    {
        // Test with minimal configuration
        $originalProjectId = config('firebase.project_id');
        config(['firebase.project_id' => null]);

        expect(fn () => app(FirestoreClient::class))
            ->not->toThrow(\Exception::class);

        // Test with empty configuration
        config(['firebase' => []]);

        expect(fn () => app(FirestoreClient::class))
            ->not->toThrow(\Exception::class);

        // Restore original configuration
        config(['firebase.project_id' => $originalProjectId]);

        // Test configuration fallbacks
        $client = app(FirestoreClient::class);
        expect($client)->toBeInstanceOf(FirestoreClient::class);
    }

    // ========================================
    // FACADE AND BINDING TESTS
    // ========================================

    #[Test]
    public function it_provides_correct_facade_functionality()
    {
        // Test that FirestoreDB facade works correctly
        $table = FirestoreDB::table('test_collection');
        expect($table)->not->toBeNull();

        // Test that facade methods are accessible
        expect(method_exists(FirestoreDB::class, 'table'))->toBeTrue();
        expect(method_exists(FirestoreDB::class, 'collection'))->toBeTrue();
        expect(method_exists(FirestoreDB::class, 'document'))->toBeTrue();
        expect(method_exists(FirestoreDB::class, 'client'))->toBeTrue();

        // Test facade performance
        $executionTime = $this->benchmark(function () {
            $table1 = FirestoreDB::table('collection1');
            $table2 = FirestoreDB::table('collection2');
            $client = FirestoreDB::client();

            return [$table1, $table2, $client];
        });

        expect($executionTime)->toBeLessThan(0.01); // Facade operations should be fast
    }

    // ========================================
    // DEPENDENCY INJECTION TESTS
    // ========================================

    #[Test]
    public function it_handles_dependency_injection_correctly()
    {
        // Test singleton binding
        $client1 = app(FirestoreClient::class);
        $client2 = app(FirestoreClient::class);

        expect($client1)->toBe($client2); // Should be the same instance (singleton)

        // Test that dependencies are properly resolved
        expect($client1)->toBeInstanceOf(FirestoreClient::class);

        // Test memory efficiency of singleton pattern
        $this->enableMemoryMonitoring();

        // Create multiple references to the same service
        $clients = [];
        for ($i = 0; $i < 10; $i++) {
            $clients[] = app(FirestoreClient::class);
        }

        // All should be the same instance
        foreach ($clients as $client) {
            expect($client)->toBe($client1);
        }

        $this->assertMemoryUsageWithinThreshold(1 * 1024 * 1024); // 1MB threshold
    }

    // ========================================
    // INTEGRATION WITH LARAVEL FEATURES
    // ========================================

    #[Test]
    public function it_integrates_correctly_with_laravel_features()
    {
        // Test that service provider is properly registered in Laravel
        $providers = app()->getLoadedProviders();
        expect($providers)->toHaveKey(JtdFirebaseModelsServiceProvider::class);

        // Test that aliases are properly registered
        $aliases = app()->getAlias('FirestoreDB');
        expect($aliases)->toBe(\JTD\FirebaseModels\Facades\FirestoreDB::class);

        // Test configuration publishing
        $provider = new JtdFirebaseModelsServiceProvider(app());

        // Verify that the provider can be instantiated with the app
        expect($provider)->toBeInstanceOf(JtdFirebaseModelsServiceProvider::class);

        // Test that the provider integrates with Laravel's service container
        expect(app()->resolved(FirestoreClient::class))->toBeTrue();
    }

    // ========================================
    // PERFORMANCE AND MEMORY TESTS
    // ========================================

    #[Test]
    public function it_optimizes_performance_and_memory_usage()
    {
        $this->enableMemoryMonitoring();

        // Test multiple service resolutions
        $executionTime = $this->benchmark(function () {
            for ($i = 0; $i < 50; $i++) {
                $client = app(FirestoreClient::class);
                $facade = app('FirestoreDB');
            }
        });

        expect($executionTime)->toBeLessThan(0.05); // 50 resolutions should be fast

        // Test memory usage
        $this->assertMemoryUsageWithinThreshold(2 * 1024 * 1024); // 2MB threshold

        // Test that services are properly cleaned up
        $initialMemory = memory_get_usage();

        // Create and resolve services
        for ($i = 0; $i < 10; $i++) {
            $provider = new JtdFirebaseModelsServiceProvider(app());
            $client = app(FirestoreClient::class);
        }

        // Force garbage collection
        unset($provider, $client);
        gc_collect_cycles();

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be minimal due to singleton pattern
        expect($memoryIncrease)->toBeLessThan(512 * 1024); // 512KB threshold
    }

    #[Test]
    public function it_cleans_up_test_data_properly()
    {
        // Test service provider functionality
        $provider = new JtdFirebaseModelsServiceProvider(app());
        $client = app(FirestoreClient::class);
        $facade = app('FirestoreDB');

        // Verify services work
        expect($provider)->toBeInstanceOf(JtdFirebaseModelsServiceProvider::class);
        expect($client)->toBeInstanceOf(FirestoreClient::class);
        expect($facade)->not->toBeNull();

        // Clear test data
        $this->clearTestData();

        // Verify cleanup
        $operations = $this->getPerformedOperations();
        expect($operations)->toBeEmpty();
    }
}
