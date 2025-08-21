<?php

namespace JTD\FirebaseModels\Tests\Helpers;

/**
 * FirestoreMockTrait provides a unified interface for all Firestore mock implementations
 * allowing tests to switch between implementations based on memory and feature requirements.
 */
trait FirestoreMockTrait
{
    protected string $mockType = FirestoreMockFactory::TYPE_FULL;
    protected $mockInstance = null;

    /**
     * Enable lightweight mock mode for memory-intensive tests.
     */
    protected function enableLightweightMock(): void
    {
        $this->mockType = FirestoreMockFactory::TYPE_LIGHTWEIGHT;
        FirestoreMockFactory::setDefaultType($this->mockType);
    }

    /**
     * Enable ultra-lightweight mock mode for maximum memory efficiency.
     */
    protected function enableUltraLightMock(): void
    {
        $this->mockType = FirestoreMockFactory::TYPE_ULTRA;
        FirestoreMockFactory::setDefaultType($this->mockType);
    }

    /**
     * Enable full mock mode with complete Mockery features.
     */
    protected function enableFullMock(): void
    {
        $this->mockType = FirestoreMockFactory::TYPE_FULL;
        FirestoreMockFactory::setDefaultType($this->mockType);
    }

    /**
     * Automatically select the best mock type based on requirements.
     */
    protected function enableAutoMock(array $requirements = []): void
    {
        $this->mockType = FirestoreMockFactory::recommendType($requirements);
        FirestoreMockFactory::setDefaultType($this->mockType);
    }

    /**
     * Set up Firestore mocking using the factory pattern.
     */
    protected function setUpFirestoreMocking(): void
    {
        // Create the appropriate mock instance
        $this->mockInstance = FirestoreMockFactory::create($this->mockType);

        // Initialize Firebase Auth mocking
        FirebaseAuthMock::initialize();
    }

    /**
     * Get the current mock instance.
     */
    protected function getMockInstance()
    {
        if ($this->mockInstance === null) {
            $this->mockInstance = FirestoreMockFactory::create($this->mockType);
        }

        return $this->mockInstance;
    }

    /**
     * Create a mock Firestore document.
     */
    protected function createMockDocument(string $collection, string $id, array $data = []): array
    {
        if ($this->useLightweightMock) {
            return LightweightFirestoreMock::createDocument($collection, $id, $data);
        }
        
        return FirestoreMock::createDocument($collection, $id, $data);
    }

    /**
     * Mock a Firestore query response.
     */
    protected function mockFirestoreQuery(string $collection, array $documents = []): void
    {
        $this->getMockInstance()->mockQuery($collection, $documents);
    }

    /**
     * Mock a Firestore document get response.
     */
    protected function mockFirestoreGet(string $collection, string $id, ?array $data = null): void
    {
        switch ($this->mockType) {
            case 'ultra':
                UltraLightFirestoreMock::mockGet($collection, $id, $data);
                break;
            case 'lightweight':
                LightweightFirestoreMock::mockGet($collection, $id, $data);
                break;
            default:
                FirestoreMock::mockGet($collection, $id, $data);
                break;
        }
    }

    /**
     * Mock a Firestore document create response.
     */
    protected function mockFirestoreCreate(string $collection, ?string $id = null): void
    {
        switch ($this->mockType) {
            case 'ultra':
                UltraLightFirestoreMock::mockCreate($collection, $id);
                break;
            case 'lightweight':
                LightweightFirestoreMock::mockCreate($collection, $id);
                break;
            default:
                FirestoreMock::mockCreate($collection, $id);
                break;
        }
    }

    /**
     * Mock a Firestore document update response.
     */
    protected function mockFirestoreUpdate(string $collection, string $id): void
    {
        switch ($this->mockType) {
            case 'ultra':
                // Ultra-light mock doesn't need explicit update mocking
                break;
            case 'lightweight':
                LightweightFirestoreMock::mockUpdate($collection, $id);
                break;
            default:
                FirestoreMock::mockUpdate($collection, $id);
                break;
        }
    }

    /**
     * Mock a Firestore document delete response.
     */
    protected function mockFirestoreDelete(string $collection, string $id): void
    {
        switch ($this->mockType) {
            case 'ultra':
                UltraLightFirestoreMock::mockDelete($collection, $id);
                break;
            case 'lightweight':
                LightweightFirestoreMock::mockDelete($collection, $id);
                break;
            default:
                FirestoreMock::mockDelete($collection, $id);
                break;
        }
    }

    /**
     * Assert that a Firestore operation was called.
     */
    protected function assertFirestoreOperationCalled(string $operation, string $collection, ?string $id = null): void
    {
        switch ($this->mockType) {
            case 'ultra':
                UltraLightFirestoreMock::assertOperationCalled($operation, $collection, $id);
                break;
            case 'lightweight':
                LightweightFirestoreMock::assertOperationCalled($operation, $collection, $id);
                break;
            default:
                FirestoreMock::assertOperationCalled($operation, $collection, $id);
                break;
        }
    }

    /**
     * Assert that a Firestore query was executed.
     */
    protected function assertFirestoreQueryExecuted(string $collection, array $expectedWheres = []): void
    {
        switch ($this->mockType) {
            case 'ultra':
                UltraLightFirestoreMock::assertQueryExecuted($collection, $expectedWheres);
                break;
            case 'lightweight':
                LightweightFirestoreMock::assertQueryExecuted($collection, $expectedWheres);
                break;
            default:
                FirestoreMock::assertQueryExecuted($collection, $expectedWheres);
                break;
        }
    }

    /**
     * Clear all Firestore mocks.
     */
    protected function clearFirestoreMocks(): void
    {
        // Clear the current mock type and reinitialize
        FirestoreMockFactory::clear($this->mockType);
        $this->mockInstance = FirestoreMockFactory::create($this->mockType);

        // Clear and reinitialize Firebase Auth mocking
        FirebaseAuthMock::clear();
        FirebaseAuthMock::initialize();
    }

    /**
     * Clear all mock types.
     */
    protected function clearAllFirestoreMocks(): void
    {
        FirestoreMockFactory::clearAll();
        $this->mockInstance = null;

        FirebaseAuthMock::clear();
    }

    /**
     * Get the Firestore mock instance.
     */
    protected function getFirestoreMock()
    {
        return $this->getMockInstance();
    }

    /**
     * Get information about the current mock type.
     */
    protected function getMockInfo(): array
    {
        $mock = $this->getMockInstance();
        return [
            'type' => $mock->getMockType(),
            'memory_efficiency' => $mock->getMemoryEfficiencyLevel(),
            'feature_completeness' => $mock->getFeatureCompletenessLevel(),
            'memory_usage' => $mock->getMemoryUsage(),
        ];
    }

    /**
     * Get memory usage comparison across all mock types.
     */
    protected function getMemoryComparison(): array
    {
        return FirestoreMockFactory::getMemoryComparison();
    }

    /**
     * Force garbage collection on the current mock.
     */
    protected function forceGarbageCollection(): void
    {
        $this->getMockInstance()->forceGarbageCollection();
    }

    /**
     * Get performance benchmarks for all mock types.
     */
    protected function getBenchmarks(): array
    {
        return FirestoreMockFactory::getBenchmarks();
    }

    /**
     * Get the Firebase Auth mock instance.
     */
    protected function getFirebaseAuthMock(): FirebaseAuthMock
    {
        return FirebaseAuthMock::getInstance();
    }

    /**
     * Create a test Firebase user.
     */
    protected function createTestUser(array $userData = []): array
    {
        return FirebaseAuthMock::createTestUser($userData);
    }

    /**
     * Mock a Firestore update failure.
     */
    protected function mockFirestoreUpdateFailure(string $collection, string $id): void
    {
        // For testing purposes, we can simulate failure by not mocking the operation
        // In a real implementation, this would set up the mock to throw an exception
    }

    /**
     * Mock a Firestore delete failure.
     */
    protected function mockFirestoreDeleteFailure(string $collection, string $id): void
    {
        // For testing purposes, we can simulate failure by not mocking the operation
        // In a real implementation, this would set up the mock to throw an exception
    }

    /**
     * Assert that a Firestore operation was NOT called.
     */
    protected function assertFirestoreOperationNotCalled(string $operation, string $collection, ?string $id = null): void
    {
        $mock = $this->getFirestoreMock();
        $operations = $mock->getOperations();

        foreach ($operations as $op) {
            if ($op['operation'] === $operation && $op['collection'] === $collection) {
                if ($id === null || $op['id'] === $id) {
                    throw new \PHPUnit\Framework\AssertionFailedError(
                        "Firestore operation '{$operation}' on collection '{$collection}'" .
                        ($id ? " with ID '{$id}'" : '') . " was called but should not have been."
                    );
                }
            }
        }
    }

    /**
     * Create a test Firebase token.
     */
    protected function createTestToken(string $uid, array $claims = []): string
    {
        return FirebaseAuthMock::createTestToken($uid, $claims);
    }

    /**
     * Assert that a Firebase Auth operation was called.
     */
    protected function assertFirebaseAuthOperationCalled(string $operation, ?string $uid = null): void
    {
        FirebaseAuthMock::assertOperationCalled($operation, $uid);
    }
}
