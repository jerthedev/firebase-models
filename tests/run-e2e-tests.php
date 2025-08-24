<?php

/**
 * E2E Test Runner for Firebase Models
 *
 * This script runs End-to-End tests against real Firebase API.
 * It requires valid credentials in tests/credentials/e2e-credentials.json
 */

require_once __DIR__.'/../vendor/autoload.php';

use JTD\FirebaseModels\Tests\E2E\E2ETestConfig;

// Check if E2E testing is available
if (!E2ETestConfig::isAvailable()) {
    echo "❌ E2E testing not available.\n";
    echo "Please set up credentials in tests/credentials/e2e-credentials.json\n";
    echo "Copy tests/credentials/e2e-credentials.example.json and add your Firebase service account credentials.\n";
    exit(1);
}

$credentials = E2ETestConfig::getCredentials();
$projectId = $credentials['project_id'] ?? 'unknown';

echo "🔥 Firebase Models E2E Test Runner\n";
echo "=====================================\n";
echo "Project ID: {$projectId}\n";
echo 'Credentials: '.E2ETestConfig::getCredentialsPath()."\n";
echo "\n";

// Warning about real Firebase usage
echo "⚠️  WARNING: These tests will create and delete data in your Firebase project!\n";
echo "Make sure you're using a test project, not production data.\n";
echo "\n";

// Ask for confirmation
echo 'Do you want to proceed? (y/N): ';
$handle = fopen('php://stdin', 'r');
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'y') {
    echo "Aborted.\n";
    exit(0);
}

echo "\n🚀 Running E2E tests...\n\n";

// Set environment variables for E2E testing
putenv('FIREBASE_E2E_MODE=true');
putenv('FIREBASE_PROJECT_ID='.$projectId);

// Run the tests
$testCommand = 'vendor/bin/pest --group=e2e --colors=always';

// Add coverage if requested
if (in_array('--coverage', $argv)) {
    $testCommand .= ' --coverage';
}

// Add specific test filter if provided
$testFilter = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--filter=') === 0) {
        $testFilter = substr($arg, 9);
        $testCommand .= ' --filter="'.$testFilter.'"';
        break;
    }
}

echo "Command: {$testCommand}\n\n";

// Execute the test command
$exitCode = 0;
passthru($testCommand, $exitCode);

echo "\n";

if ($exitCode === 0) {
    echo "✅ All E2E tests passed!\n";
} else {
    echo "❌ Some E2E tests failed. Exit code: {$exitCode}\n";
}

echo "\n📊 Test Summary:\n";
echo "- Project: {$projectId}\n";
echo "- Test collections will be automatically cleaned up\n";
echo "- Check Firebase Console if you need to verify data\n";

exit($exitCode);
