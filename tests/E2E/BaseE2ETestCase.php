<?php

namespace JTD\FirebaseModels\Tests\E2E;

use Orchestra\Testbench\TestCase as Orchestra;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Firestore;
use Kreait\Firebase\Contract\Auth as FirebaseAuthContract;
use Google\Cloud\Firestore\FirestoreClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base test case for End-to-End testing with real Firebase API.
 *
 * This class extends Orchestra TestCase directly (not the mock-enabled TestCase)
 * to avoid any interference from the FirestoreMock system.
 *
 * This provides infrastructure for testing against a real Firebase project
 * using the credentials in tests/credentials/e2e-credentials.json.
 */
abstract class BaseE2ETestCase extends Orchestra
{
    use RefreshDatabase;
    /**
     * Firebase Factory instance for E2E testing.
     */
    protected Factory $firebaseFactory;

    /**
     * Real Firestore client for E2E testing.
     */
    protected Firestore $realFirestore;

    /**
     * Real Firebase Auth client for E2E testing.
     */
    protected FirebaseAuthContract $realFirebaseAuth;

    /**
     * Test collection prefix to avoid conflicts.
     */
    protected string $testCollectionPrefix;

    /**
     * Collections created during testing (for cleanup).
     */
    protected array $testCollections = [];

    /**
     * Documents created during testing (for cleanup).
     */
    protected array $testDocuments = [];

    /**
     * Test users created during testing (for cleanup).
     */
    protected array $testUsers = [];

    protected function setUp(): void
    {
        // Call parent setUp to initialize Laravel application
        parent::setUp();

        // Set up E2E environment and real Firebase
        $this->setUpE2EEnvironment();
        $this->setUpRealFirebase();
        $this->generateTestPrefix();
    }

    /**
     * Set up any database tables needed for testing.
     */
    protected function setUpDatabase(): void
    {
        // Set up any database tables needed for testing
        // This can be used for sync mode testing or local caching
    }

    /**
     * Get the package providers for E2E testing.
     */
    protected function getPackageProviders($app): array
    {
        return [
            \JTD\FirebaseModels\JtdFirebaseModelsServiceProvider::class,
        ];
    }

    /**
     * Get the package aliases for E2E testing.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'FirestoreDB' => \JTD\FirebaseModels\Facades\FirestoreDB::class,
        ];
    }

    /**
     * Define the environment setup for E2E tests.
     */
    protected function defineEnvironment($app): void
    {
        // Configure the application environment for E2E testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Load E2E credentials if available
        $credentialsPath = $this->getCredentialsPath();

        if (file_exists($credentialsPath)) {
            $credentials = json_decode(file_get_contents($credentialsPath), true);

            if ($credentials && isset($credentials['project_id'])) {
                // Configure Firebase for E2E testing (no mock mode)
                $app['config']->set('firebase.project_id', $credentials['project_id']);
                $app['config']->set('firebase.credentials', $credentials);
                $app['config']->set('firebase.mock_mode', false);
            }
        }

        // Configure cache for E2E testing - disable to avoid conflicts
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        // Disable firebase-models cache to avoid conflicts with Firebase SDK internal cache
        $app['config']->set('firebase-models.cache.enabled', false);
        $app['config']->set('firebase-models.cache.request_enabled', false);
        $app['config']->set('firebase-models.cache.persistent_enabled', false);
    }

    /**
     * Set up the environment for E2E testing.
     */
    protected function setUpE2EEnvironment(): void
    {
        // Load E2E credentials using a more reliable path resolution
        $credentialsPath = $this->getCredentialsPath();

        if (!file_exists($credentialsPath)) {
            $this->markTestSkipped('E2E credentials not found. Please set up tests/credentials/e2e-credentials.json');
        }

        $credentials = json_decode(file_get_contents($credentialsPath), true);
        
        if (!$credentials || !isset($credentials['project_id'])) {
            $this->markTestSkipped('Invalid E2E credentials format');
        }

        // Configure for real Firebase (disable mock mode)
        Config::set('firebase.mock_mode', false);
        Config::set('firebase.project_id', $credentials['project_id']);
        Config::set('firebase.credentials', $credentials);
        
        // Configure cache for E2E testing
        Config::set('cache.default', 'array');
        Config::set('firebase-models.cache.store', 'array');
        Config::set('firebase-models.cache.enabled', true);
    }

    /**
     * Get the path to E2E credentials file.
     */
    protected function getCredentialsPath(): string
    {
        // Try multiple path resolution methods
        $possiblePaths = [
            __DIR__ . '/../credentials/e2e-credentials.json',
            dirname(__DIR__) . '/credentials/e2e-credentials.json',
        ];

        // If base_path is available, try that too
        if (function_exists('base_path')) {
            try {
                $possiblePaths[] = base_path('tests/credentials/e2e-credentials.json');
            } catch (\Exception $e) {
                // Ignore if base_path fails
            }
        }

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Return the most likely path even if it doesn't exist
        return __DIR__ . '/../credentials/e2e-credentials.json';
    }

    /**
     * Set up real Firebase clients.
     */
    protected function setUpRealFirebase(): void
    {
        $credentialsPath = $this->getCredentialsPath();

        // Try to configure Firebase factory to avoid cache conflicts
        $this->firebaseFactory = (new Factory())
            ->withServiceAccount($credentialsPath);

        // Try to create Firestore with minimal configuration to avoid cache issues
        try {
            $this->realFirestore = $this->firebaseFactory->createFirestore();
            $this->realFirebaseAuth = $this->firebaseFactory->createAuth();
        } catch (\Exception $e) {
            $this->markTestSkipped('Firebase client creation failed: ' . $e->getMessage());
        }

        // Bind the real Firebase clients to the application container
        $this->app->singleton('firebase.factory', fn() => $this->firebaseFactory);
        $this->app->singleton(Firestore::class, fn() => $this->realFirestore);
        $this->app->singleton(FirebaseAuthContract::class, fn() => $this->realFirebaseAuth);
        $this->app->singleton('firebase.firestore', fn() => $this->realFirestore);
        $this->app->singleton('firebase.auth', fn() => $this->realFirebaseAuth);

        // Bind FirestoreClient to use the real one
        $this->app->singleton(FirestoreClient::class, fn() => $this->realFirestore->database());

        // Bind FirestoreDatabase to use the real client
        $this->app->singleton('firestore.db', function ($app) {
            return new \JTD\FirebaseModels\Firestore\FirestoreDatabase($this->realFirestore);
        });
    }

    /**
     * Generate a unique test prefix to avoid conflicts.
     */
    protected function generateTestPrefix(): void
    {
        $this->testCollectionPrefix = 'e2e_test_' . date('Ymd_His') . '_' . Str::random(8);
    }

    /**
     * Get a test collection name with prefix.
     */
    protected function getTestCollection(string $baseName): string
    {
        $collectionName = $this->testCollectionPrefix . '_' . $baseName;
        $this->testCollections[] = $collectionName;
        return $collectionName;
    }

    /**
     * Create a test document and track it for cleanup.
     */
    protected function createTestDocument(string $collection, array $data, ?string $id = null): array
    {
        $collection = $this->getTestCollection($collection);
        
        if ($id) {
            $docRef = $this->realFirestore->database()->collection($collection)->document($id);
            $docRef->set($data);
            $documentId = $id;
        } else {
            $docRef = $this->realFirestore->database()->collection($collection)->add($data);
            $documentId = $docRef->id();
        }

        $this->testDocuments[] = [
            'collection' => $collection,
            'id' => $documentId
        ];

        return array_merge($data, ['id' => $documentId]);
    }

    /**
     * Create a test user and track it for cleanup.
     */
    protected function createTestUser(array $properties = []): array
    {
        $defaultProperties = [
            'email' => 'test_' . Str::random(8) . '@example.com',
            'password' => 'testpassword123',
            'displayName' => 'Test User ' . Str::random(4),
            'emailVerified' => false,
        ];

        $userProperties = array_merge($defaultProperties, $properties);
        
        $userRecord = $this->realFirebaseAuth->createUser($userProperties);
        
        $this->testUsers[] = $userRecord->uid;

        return [
            'uid' => $userRecord->uid,
            'email' => $userRecord->email,
            'displayName' => $userRecord->displayName,
            'emailVerified' => $userRecord->emailVerified,
        ];
    }

    /**
     * Clean up test data after each test.
     */
    protected function tearDown(): void
    {
        $this->cleanupTestDocuments();
        $this->cleanupTestUsers();

        // Clear any Mockery instances
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }

        parent::tearDown();
    }

    /**
     * Clean up test documents from Firestore.
     */
    protected function cleanupTestDocuments(): void
    {
        foreach ($this->testDocuments as $document) {
            try {
                $this->realFirestore->database()
                    ->collection($document['collection'])
                    ->document($document['id'])
                    ->delete();
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Clean up collections (delete any remaining documents)
        foreach (array_unique($this->testCollections) as $collection) {
            try {
                $documents = $this->realFirestore->database()
                    ->collection($collection)
                    ->limit(100)
                    ->documents();

                foreach ($documents as $document) {
                    $document->reference()->delete();
                }
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        $this->testDocuments = [];
        $this->testCollections = [];
    }

    /**
     * Clean up test users from Firebase Auth.
     */
    protected function cleanupTestUsers(): void
    {
        foreach ($this->testUsers as $uid) {
            try {
                $this->realFirebaseAuth->deleteUser($uid);
            } catch (\Exception $e) {
                // Ignore cleanup errors (user might not exist)
            }
        }

        $this->testUsers = [];
    }

    /**
     * Assert that a document exists in Firestore.
     */
    protected function assertDocumentExists(string $collection, string $id): void
    {
        $document = $this->realFirestore->database()
            ->collection($collection)
            ->document($id)
            ->snapshot();

        $this->assertTrue($document->exists(), "Document {$id} should exist in collection {$collection}");
    }

    /**
     * Assert that a document does not exist in Firestore.
     */
    protected function assertDocumentNotExists(string $collection, string $id): void
    {
        $document = $this->realFirestore->database()
            ->collection($collection)
            ->document($id)
            ->snapshot();

        $this->assertFalse($document->exists(), "Document {$id} should not exist in collection {$collection}");
    }

    /**
     * Assert that a document has specific data.
     */
    protected function assertDocumentHasData(string $collection, string $id, array $expectedData): void
    {
        $document = $this->realFirestore->database()
            ->collection($collection)
            ->document($id)
            ->snapshot();

        $this->assertTrue($document->exists(), "Document {$id} should exist in collection {$collection}");

        $actualData = $document->data();
        
        foreach ($expectedData as $key => $value) {
            $this->assertEquals($value, $actualData[$key] ?? null, "Document field {$key} should match expected value");
        }
    }

    /**
     * Get the real Firestore client for direct operations.
     */
    protected function getFirestore(): Firestore
    {
        return $this->realFirestore;
    }

    /**
     * Get the real Firebase Auth client for direct operations.
     */
    protected function getFirebaseAuth(): FirebaseAuthContract
    {
        return $this->realFirebaseAuth;
    }

    /**
     * Skip test if E2E testing is not available.
     */
    protected function skipIfE2ENotAvailable(): void
    {
        $credentialsPath = $this->getCredentialsPath();

        if (!file_exists($credentialsPath)) {
            $this->markTestSkipped('E2E testing requires credentials in tests/credentials/e2e-credentials.json');
        }
    }
}
