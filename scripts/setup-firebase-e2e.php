<?php

/**
 * Firebase E2E Testing Setup Script
 *
 * This script helps set up your Firebase project for comprehensive E2E testing
 * by creating required indexes and verifying configurations.
 */

require_once __DIR__.'/../vendor/autoload.php';

use Google\ApiCore\ApiException;
use Google\Cloud\Firestore\Admin\V1\FirestoreAdminClient;
use Google\Cloud\Firestore\Admin\V1\Index;
use Google\Cloud\Firestore\Admin\V1\Index\IndexField;
use Google\Cloud\Firestore\Admin\V1\Index\IndexField\Order;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Factory;

echo "🔥 Firebase E2E Testing Setup\n";
echo "============================\n\n";

// Check for credentials
$credentialsPath = __DIR__.'/../tests/credentials/e2e-credentials.json';

if (!file_exists($credentialsPath)) {
    echo "❌ E2E credentials not found!\n";
    echo "Please copy your service account JSON to: {$credentialsPath}\n";
    echo "Use the example file as a template: tests/credentials/e2e-credentials.example.json\n";
    exit(1);
}

try {
    // Initialize Firebase
    $factory = (new Factory())->withServiceAccount($credentialsPath);
    $firestore = $factory->createFirestore();
    $auth = $factory->createAuth();

    $credentials = json_decode(file_get_contents($credentialsPath), true);
    $projectId = $credentials['project_id'];

    echo "✅ Connected to Firebase project: {$projectId}\n\n";

    // Test basic connectivity
    echo "🧪 Testing basic connectivity...\n";

    // Test Firestore
    $testCollection = 'setup_test_'.time();
    $testDoc = $firestore->database()->collection($testCollection)->add([
        'test' => true,
        'timestamp' => new DateTime(),
        'setup_script' => true,
    ]);

    echo "✅ Firestore write test passed\n";

    // Read back the document
    $readDoc = $testDoc->snapshot();
    if ($readDoc->exists()) {
        echo "✅ Firestore read test passed\n";
    }

    // Clean up test document
    $testDoc->delete();
    echo "✅ Firestore delete test passed\n";

    // Test Authentication
    try {
        $userProperties = [
            'email' => 'setup-test@example.com',
            'password' => 'setuptest123',
            'displayName' => 'Setup Test User',
        ];

        // Try to create a test user (will fail if already exists, which is fine)
        try {
            $testUser = $auth->createUser($userProperties);
            echo "✅ Firebase Auth create user test passed\n";

            // Clean up test user
            $auth->deleteUser($testUser->uid);
            echo "✅ Firebase Auth delete user test passed\n";
        } catch (FirebaseException $e) {
            if (strpos($e->getMessage(), 'EMAIL_EXISTS') !== false) {
                echo "✅ Firebase Auth test passed (user already exists)\n";
            } else {
                throw $e;
            }
        }
    } catch (FirebaseException $e) {
        echo '⚠️  Firebase Auth test skipped: '.$e->getMessage()."\n";
    }

    // Create Firestore indexes programmatically
    echo "\n🔍 Creating Firestore Indexes...\n";
    echo "===============================\n\n";

    try {
        $adminClient = new FirestoreAdminClient([
            'credentials' => $credentialsPath,
        ]);

        $databaseName = $adminClient->databaseName($projectId, '(default)');

        echo "Connected to Firestore Admin API for project: {$projectId}\n";

        // Define required indexes for E2E testing
        $requiredIndexes = [
            [
                'name' => 'E2E Basic Query Index',
                'collectionGroup' => 'e2e_test_basic_query_test',
                'fields' => [
                    ['field' => 'category', 'order' => Order::ASCENDING],
                    ['field' => 'priority', 'order' => Order::ASCENDING],
                    ['field' => '__name__', 'order' => Order::ASCENDING],
                ],
            ],
            [
                'name' => 'E2E User Query Index',
                'collectionGroup' => 'users_test',
                'fields' => [
                    ['field' => 'active', 'order' => Order::ASCENDING],
                    ['field' => 'role', 'order' => Order::ASCENDING],
                    ['field' => 'created_at', 'order' => Order::DESCENDING],
                ],
            ],
            [
                'name' => 'E2E Posts Query Index',
                'collectionGroup' => 'posts_test',
                'fields' => [
                    ['field' => 'status', 'order' => Order::ASCENDING],
                    ['field' => 'category_id', 'order' => Order::ASCENDING],
                    ['field' => 'published_at', 'order' => Order::DESCENDING],
                ],
            ],
            [
                'name' => 'E2E Categories Query Index',
                'collectionGroup' => 'categories_test',
                'fields' => [
                    ['field' => 'active', 'order' => Order::ASCENDING],
                    ['field' => 'sort_order', 'order' => Order::ASCENDING],
                    ['field' => 'name', 'order' => Order::ASCENDING],
                ],
            ],
        ];

        // Create each index
        $createdIndexes = 0;
        $skippedIndexes = 0;

        foreach ($requiredIndexes as $indexConfig) {
            echo "Creating index: {$indexConfig['name']}...\n";

            try {
                // Create the index object
                $index = new Index();
                $index->setQueryScope(Index\QueryScope::COLLECTION_GROUP);

                // Add fields to the index
                $indexFields = [];
                foreach ($indexConfig['fields'] as $fieldConfig) {
                    $indexField = new IndexField();
                    $indexField->setFieldPath($fieldConfig['field']);
                    $indexField->setOrder($fieldConfig['order']);
                    $indexFields[] = $indexField;
                }
                $index->setFields($indexFields);

                // Set collection group
                $collectionGroupName = $adminClient->collectionGroupName($projectId, '(default)', $indexConfig['collectionGroup']);

                // Create the index
                $operation = $adminClient->createIndex($collectionGroupName, $index);

                echo "  ✅ Index creation started: {$indexConfig['name']}\n";
                echo "     Collection Group: {$indexConfig['collectionGroup']}\n";
                echo '     Fields: '.implode(', ', array_map(function ($f) {
                    return $f['field'].' ('.($f['order'] === Order::ASCENDING ? 'ASC' : 'DESC').')';
                }, $indexConfig['fields']))."\n";
                echo "     Status: Building (this may take a few minutes)\n\n";

                $createdIndexes++;
            } catch (ApiException $e) {
                if ($e->getStatus() === 'ALREADY_EXISTS') {
                    echo "  ⚠️  Index already exists: {$indexConfig['name']}\n\n";
                    $skippedIndexes++;
                } else {
                    echo "  ❌ Failed to create index: {$indexConfig['name']}\n";
                    echo '     Error: '.$e->getMessage()."\n\n";
                }
            } catch (Exception $e) {
                echo "  ❌ Failed to create index: {$indexConfig['name']}\n";
                echo '     Error: '.$e->getMessage()."\n\n";
            }
        }

        echo "📊 Index Creation Summary:\n";
        echo "  ✅ Created: {$createdIndexes} indexes\n";
        echo "  ⚠️  Skipped: {$skippedIndexes} indexes (already exist)\n\n";

        if ($createdIndexes > 0) {
            echo "⏳ Note: Index creation is asynchronous and may take several minutes to complete.\n";
            echo "   You can monitor progress in the Firebase Console:\n";
            echo "   https://console.firebase.google.com/project/{$projectId}/firestore/indexes\n\n";
        }
    } catch (Exception $e) {
        echo '❌ Failed to create indexes automatically: '.$e->getMessage()."\n";
        echo "You'll need to create them manually in the Firebase Console.\n\n";

        // Fall back to manual instructions
        echo "📋 Manual Index Creation Required:\n";
        echo "=================================\n\n";
        echo "Go to: https://console.firebase.google.com/project/{$projectId}/firestore/indexes\n";
        echo "Create these composite indexes:\n\n";

        foreach ($requiredIndexes as $i => $indexConfig) {
            echo ($i + 1).". {$indexConfig['name']}\n";
            echo "   Collection Group: {$indexConfig['collectionGroup']}\n";
            echo '   Fields: '.implode(', ', array_map(function ($f) {
                return $f['field'].' ('.($f['order'] === Order::ASCENDING ? 'Ascending' : 'Descending').')';
            }, $indexConfig['fields']))."\n\n";
        }
    }

    echo "2. 🔐 **Update Firestore Security Rules**\n";
    echo "   Go to: https://console.firebase.google.com/project/{$projectId}/firestore/rules\n";
    echo "   Add these rules for E2E testing:\n\n";
    echo "   ```javascript\n";
    echo "   rules_version = '2';\n";
    echo "   service cloud.firestore {\n";
    echo "     match /databases/{database}/documents {\n";
    echo "       // E2E testing collections\n";
    echo "       match /e2e_test_{suffix=**} {\n";
    echo "         allow read, write: if true;\n";
    echo "       }\n";
    echo "       match /users_{suffix=**} {\n";
    echo "         allow read, write: if true;\n";
    echo "       }\n";
    echo "       match /posts_{suffix=**} {\n";
    echo "         allow read, write: if true;\n";
    echo "       }\n";
    echo "       match /categories_{suffix=**} {\n";
    echo "         allow read, write: if true;\n";
    echo "       }\n";
    echo "     }\n";
    echo "   }\n";
    echo "   ```\n\n";

    echo "3. 🔑 **Enable Authentication Methods**\n";
    echo "   Go to: https://console.firebase.google.com/project/{$projectId}/authentication/providers\n";
    echo "   Enable: Email/Password, Anonymous, Custom Token\n\n";

    echo "4. ⚙️  **Service Account Permissions**\n";
    echo "   Go to: https://console.cloud.google.com/iam-admin/iam?project={$projectId}\n";
    echo "   Your service account needs these roles:\n";
    echo "   - Firebase Admin SDK Administrator Service Agent ✅\n";
    echo "   - Cloud Datastore User ✅\n";
    echo "   - Firebase Authentication Admin ✅\n";
    echo "   - Cloud Datastore Index Admin (for automatic index creation)\n\n";

    echo "💡 **For Automatic Index Creation:**\n";
    echo "   If you want the setup script to create indexes automatically,\n";
    echo "   add this role to your service account:\n";
    echo "   - Cloud Datastore Index Admin\n";
    echo "   Then re-run: php scripts/setup-firebase-e2e.php\n\n";

    echo "🎉 Setup verification complete!\n";
    echo "Your Firebase project is ready for E2E testing!\n\n";
    echo "Next steps:\n";
    echo "1. Create the indexes (manually or by adding permissions)\n";
    echo "2. Update Firestore security rules\n";
    echo "3. Run E2E tests: composer test-e2e\n";
} catch (Exception $e) {
    echo '❌ Setup failed: '.$e->getMessage()."\n";
    echo "Stack trace:\n".$e->getTraceAsString()."\n";
    exit(1);
}
