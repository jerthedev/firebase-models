<?php

namespace JTD\FirebaseModels\Tests\Unit;

use JTD\FirebaseModels\Tests\TestCase;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMockFactory;
use JTD\FirebaseModels\Tests\Helpers\UltraLightFirestoreMock;

/**
 * Test the consolidated mock system architecture.
 */
class MockSystemConsolidationTest extends TestCase
{
    protected function setUp(): void
    {
        // Skip parent setUp to avoid automatic mock setup
        $this->createApplication();
    }

    protected function tearDown(): void
    {
        FirestoreMockFactory::clearAll();
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_different_mock_types()
    {
        // Test that factory can create different mock types
        $ultraMock = FirestoreMockFactory::create(FirestoreMockFactory::TYPE_ULTRA);

        expect($ultraMock)->toBeInstanceOf(UltraLightFirestoreMock::class);
        expect($ultraMock->getMockType())->toBe('ultra');
        expect($ultraMock->getMemoryEfficiencyLevel())->toBe(3);
        expect($ultraMock->getFeatureCompletenessLevel())->toBe(2);
    }

    /** @test */
    public function it_provides_consistent_interface_for_ultra_mock()
    {
        $mock = FirestoreMockFactory::create(FirestoreMockFactory::TYPE_ULTRA);

        // Test basic document operations
        $mock->storeDocument('users', 'user1', ['name' => 'John', 'email' => 'john@example.com']);

        $document = $mock->getDocument('users', 'user1');
        expect($document)->toBe(['name' => 'John', 'email' => 'john@example.com']);

        expect($mock->documentExists('users', 'user1'))->toBeTrue();
        expect($mock->documentExists('users', 'nonexistent'))->toBeFalse();

        expect($mock->getCollectionCount('users'))->toBe(1);

        $mock->deleteDocument('users', 'user1');
        expect($mock->documentExists('users', 'user1'))->toBeFalse();
    }

    /** @test */
    public function it_provides_factory_functionality()
    {
        // Test factory can recommend mock types
        $recommendation = FirestoreMockFactory::recommendType([
            'document_count' => 2000,
            'memory_constraint' => true
        ]);
        expect($recommendation)->toBe(FirestoreMockFactory::TYPE_ULTRA);

        // Test factory provides type information
        $availableTypes = FirestoreMockFactory::getAvailableTypes();
        expect($availableTypes)->toHaveKeys([
            FirestoreMockFactory::TYPE_FULL,
            FirestoreMockFactory::TYPE_LIGHTWEIGHT,
            FirestoreMockFactory::TYPE_ULTRA
        ]);

        // Test factory provides benchmarks
        $benchmarks = FirestoreMockFactory::getBenchmarks();
        expect($benchmarks)->toHaveKeys(['memory_usage', 'initialization_speed', 'feature_support']);
    }
}
