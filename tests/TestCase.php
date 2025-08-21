<?php

namespace JTD\FirebaseModels\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use JTD\FirebaseModels\JtdFirebaseModelsServiceProvider;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMockTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase, FirestoreMockTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up Firestore mocking
        $this->setUpFirestoreMocking();
        
        // Set up test database if needed
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            JtdFirebaseModelsServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'FirestoreDB' => \JTD\FirebaseModels\Facades\FirestoreDB::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Configure the application environment for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure Firebase for testing
        $app['config']->set('firebase.project_id', 'test-project');
        $app['config']->set('firebase.credentials', [
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'test-key-id',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKB\ntest-key-content\n-----END PRIVATE KEY-----\n",
            'client_email' => 'test@test-project.iam.gserviceaccount.com',
            'client_id' => '123456789',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => 'https://www.googleapis.com/robot/v1/metadata/x509/test%40test-project.iam.gserviceaccount.com',
        ]);

        // Enable mock mode for testing
        $app['config']->set('firebase.mock_mode', true);

        // Configure cache for testing
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        // Configure firebase-models cache
        $app['config']->set('firebase-models.cache.store', 'array');
    }

    // setUpFirestoreMocking is now provided by FirestoreMockTrait

    protected function setUpDatabase(): void
    {
        // Set up any database tables needed for testing
        // This can be used for sync mode testing or local caching
    }

    // Mock helper methods are now provided by FirestoreMockTrait

    /**
     * Create a test model instance.
     */
    protected function createTestModel(string $modelClass, array $attributes = []): mixed
    {
        return new $modelClass($attributes);
    }

    /**
     * Assert that a model has the expected attributes.
     */
    protected function assertModelHasAttributes($model, array $expectedAttributes): void
    {
        foreach ($expectedAttributes as $key => $value) {
            $this->assertEquals($value, $model->getAttribute($key), "Model attribute '{$key}' does not match expected value");
        }
    }

    /**
     * Assert that a model is dirty for the given attributes.
     */
    protected function assertModelIsDirty($model, array|string|null $attributes = null): void
    {
        $this->assertTrue($model->isDirty($attributes), 'Model should be dirty');
    }

    /**
     * Assert that a model is clean for the given attributes.
     */
    protected function assertModelIsClean($model, array|string|null $attributes = null): void
    {
        $this->assertTrue($model->isClean($attributes), 'Model should be clean');
    }

    /**
     * Assert that a model exists in Firestore.
     */
    protected function assertModelExists($model): void
    {
        $this->assertTrue($model->exists, 'Model should exist in Firestore');
    }

    /**
     * Assert that a model was recently created.
     */
    protected function assertModelWasRecentlyCreated($model): void
    {
        $this->assertTrue($model->wasRecentlyCreated, 'Model should be recently created');
    }

    /**
     * Assert that a model has the expected cast type for an attribute.
     */
    protected function assertModelHasCast($model, string $attribute, string $castType): void
    {
        $this->assertTrue($model->hasCast($attribute, $castType), "Model should have cast '{$castType}' for attribute '{$attribute}'");
    }

    /**
     * Create a Carbon instance for testing.
     */
    protected function createTestDate(string $date = 'now'): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::parse($date);
    }

    /**
     * Create a Firestore Timestamp for testing.
     */
    protected function createTestTimestamp(string $date = 'now'): \Google\Cloud\Firestore\Timestamp
    {
        return new \Google\Cloud\Firestore\Timestamp(\Illuminate\Support\Carbon::parse($date));
    }

    /**
     * Freeze time for testing.
     */
    protected function freezeTimeAt(string $date = 'now'): void
    {
        \Illuminate\Support\Carbon::setTestNow($date);
    }

    /**
     * Unfreeze time after testing.
     */
    protected function unfreezeTimeAt(): void
    {
        \Illuminate\Support\Carbon::setTestNow();
    }

    protected function tearDown(): void
    {
        // Clear Firestore mocks
        $this->clearFirestoreMocks();

        // Unfreeze time
        $this->unfreezeTimeAt();

        // Force garbage collection to free memory
        $this->forceGarbageCollection();

        parent::tearDown();
    }

    /**
     * Force garbage collection to free memory between tests.
     */
    protected function forceGarbageCollection(): void
    {
        // Close Mockery to free mock objects
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }

        // Clear Laravel container instances (skip for now to avoid issues)

        // Force PHP garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Clear any static caches
        \Illuminate\Support\Facades\Cache::flush();
    }
}
