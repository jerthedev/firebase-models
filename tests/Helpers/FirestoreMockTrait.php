<?php

namespace JTD\FirebaseModels\Tests\Helpers;

/**
 * FirestoreMockTrait provides a unified interface for both FirestoreMock and LightweightFirestoreMock
 * allowing tests to switch between implementations based on memory requirements.
 */
trait FirestoreMockTrait
{
    protected bool $useLightweightMock = false;

    /**
     * Enable lightweight mock mode for memory-intensive tests.
     */
    protected function enableLightweightMock(): void
    {
        $this->useLightweightMock = true;
    }

    /**
     * Set up Firestore mocking.
     */
    protected function setUpFirestoreMocking(): void
    {
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::initialize();
        } else {
            FirestoreMock::initialize();
        }
        
        // Initialize Firebase Auth mocking
        FirebaseAuthMock::initialize();
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
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::mockQuery($collection, $documents);
        } else {
            FirestoreMock::mockQuery($collection, $documents);
        }
    }

    /**
     * Mock a Firestore document get response.
     */
    protected function mockFirestoreGet(string $collection, string $id, ?array $data = null): void
    {
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::mockGet($collection, $id, $data);
        } else {
            FirestoreMock::mockGet($collection, $id, $data);
        }
    }

    /**
     * Mock a Firestore document create response.
     */
    protected function mockFirestoreCreate(string $collection, ?string $id = null): void
    {
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::mockCreate($collection, $id);
        } else {
            FirestoreMock::mockCreate($collection, $id);
        }
    }

    /**
     * Mock a Firestore document update response.
     */
    protected function mockFirestoreUpdate(string $collection, string $id): void
    {
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::mockUpdate($collection, $id);
        } else {
            FirestoreMock::mockUpdate($collection, $id);
        }
    }

    /**
     * Mock a Firestore document delete response.
     */
    protected function mockFirestoreDelete(string $collection, string $id): void
    {
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::mockDelete($collection, $id);
        } else {
            FirestoreMock::mockDelete($collection, $id);
        }
    }

    /**
     * Assert that a Firestore operation was called.
     */
    protected function assertFirestoreOperationCalled(string $operation, string $collection, ?string $id = null): void
    {
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::assertOperationCalled($operation, $collection, $id);
        } else {
            FirestoreMock::assertOperationCalled($operation, $collection, $id);
        }
    }

    /**
     * Assert that a Firestore query was executed.
     */
    protected function assertFirestoreQueryExecuted(string $collection, array $expectedWheres = []): void
    {
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::assertQueryExecuted($collection, $expectedWheres);
        } else {
            FirestoreMock::assertQueryExecuted($collection, $expectedWheres);
        }
    }

    /**
     * Clear all Firestore mocks.
     */
    protected function clearFirestoreMocks(): void
    {
        if ($this->useLightweightMock) {
            LightweightFirestoreMock::clear();
        } else {
            FirestoreMock::clear();
        }
        
        FirebaseAuthMock::clear();
    }

    /**
     * Get the Firestore mock instance.
     */
    protected function getFirestoreMock()
    {
        if ($this->useLightweightMock) {
            return LightweightFirestoreMock::getInstance();
        }
        
        return FirestoreMock::getInstance();
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
