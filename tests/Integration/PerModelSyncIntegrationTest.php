<?php

namespace JTD\FirebaseModels\Tests\Integration;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\IntegrationTestSuite;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for per-model sync configuration.
 */
class PerModelSyncIntegrationTest extends IntegrationTestSuite
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure global sync mode
        config([
            'firebase-models.mode' => 'sync',
            'firebase-models.sync.enabled' => true,
            'firebase-models.sync.read_strategy' => 'local_first',
            'firebase-models.sync.write_strategy' => 'both',
        ]);
    }

    #[Test]
    public function it_respects_per_model_sync_configuration()
    {
        // Create models with different sync settings
        $syncEnabledUser = new TestSyncEnabledUser([
            'name' => 'Sync User',
            'email' => 'sync@example.com',
        ]);

        $syncDisabledUser = new TestSyncDisabledUser([
            'name' => 'No Sync User',
            'email' => 'nosync@example.com',
        ]);

        $defaultSyncUser = new TestDefaultSyncUser([
            'name' => 'Default User',
            'email' => 'default@example.com',
        ]);

        // Verify sync settings in sync mode
        expect($syncEnabledUser->isSyncModeEnabled())->toBeTrue();
        expect($syncDisabledUser->isSyncModeEnabled())->toBeFalse();
        expect($defaultSyncUser->isSyncModeEnabled())->toBeTrue(); // Uses global config

        // Test supportsSyncMode method (mocking hasLocalTable)
        $syncEnabledUserWithTable = new class() extends TestSyncEnabledUser
        {
            public function hasLocalTable(): bool
            {
                return true;
            }
        };
        $syncDisabledUserWithTable = new class() extends TestSyncDisabledUser
        {
            public function hasLocalTable(): bool
            {
                return true;
            }
        };

        expect($syncEnabledUserWithTable->supportsSyncMode())->toBeTrue();
        expect($syncDisabledUserWithTable->supportsSyncMode())->toBeFalse();
    }

    #[Test]
    public function it_handles_model_inheritance_correctly()
    {
        // Create parent and child models
        $parentModel = new TestParentSyncModel([
            'name' => 'Parent Model',
            'type' => 'parent',
        ]);

        $childInheritingModel = new TestChildInheritingSyncModel([
            'name' => 'Child Inheriting',
            'type' => 'child',
            'child_field' => 'inherited sync',
        ]);

        $childOverridingModel = new TestChildOverridingSyncModel([
            'name' => 'Child Overriding',
            'type' => 'child',
            'child_field' => 'no sync',
        ]);

        // Verify sync settings
        expect($parentModel->isSyncModeEnabled())->toBeTrue();
        expect($childInheritingModel->isSyncModeEnabled())->toBeTrue();
        expect($childOverridingModel->isSyncModeEnabled())->toBeFalse();
    }

    #[Test]
    public function it_respects_per_model_sync_in_cloud_mode()
    {
        // Switch to cloud mode globally
        config(['firebase-models.mode' => 'cloud']);

        $forceEnabledModel = new TestSyncEnabledUser([
            'name' => 'Force Sync User',
            'email' => 'force@example.com',
        ]);

        $defaultModel = new TestDefaultSyncUser([
            'name' => 'Default User',
            'email' => 'default@example.com',
        ]);

        // Verify sync settings in cloud mode
        expect($forceEnabledModel->isSyncModeEnabled())->toBeTrue();
        expect($defaultModel->isSyncModeEnabled())->toBeFalse();

        // Test supportsSyncMode with mocked hasLocalTable
        $forceEnabledWithTable = new class() extends TestSyncEnabledUser
        {
            public function hasLocalTable(): bool
            {
                return true;
            }
        };
        $defaultWithTable = new class() extends TestDefaultSyncUser
        {
            public function hasLocalTable(): bool
            {
                return true;
            }
        };

        expect($forceEnabledWithTable->supportsSyncMode())->toBeTrue();
        expect($defaultWithTable->supportsSyncMode())->toBeFalse();
    }
}

/**
 * Test model with sync explicitly enabled.
 */
class TestSyncEnabledUser extends FirestoreModel
{
    protected ?string $collection = 'sync_enabled_users';

    protected array $fillable = ['name', 'email'];

    protected ?bool $syncEnabled = true;
}

/**
 * Test model with sync explicitly disabled.
 */
class TestSyncDisabledUser extends FirestoreModel
{
    protected ?string $collection = 'sync_disabled_users';

    protected array $fillable = ['name', 'email'];

    protected ?bool $syncEnabled = false;
}

/**
 * Test model with default sync configuration.
 */
class TestDefaultSyncUser extends FirestoreModel
{
    protected ?string $collection = 'default_sync_users';

    protected array $fillable = ['name', 'email'];
    // $syncEnabled is null by default
}

/**
 * Parent model with sync enabled.
 */
class TestParentSyncModel extends FirestoreModel
{
    protected ?string $collection = 'parent_sync_models';

    protected array $fillable = ['name', 'type'];

    protected ?bool $syncEnabled = true;
}

/**
 * Child model inheriting parent's sync setting.
 */
class TestChildInheritingSyncModel extends TestParentSyncModel
{
    protected ?string $collection = 'child_inheriting_models';

    protected array $fillable = ['name', 'type', 'child_field'];
    // Inherits $syncEnabled = true from parent
}

/**
 * Child model overriding parent's sync setting.
 */
class TestChildOverridingSyncModel extends TestParentSyncModel
{
    protected ?string $collection = 'child_overriding_models';

    protected array $fillable = ['name', 'type', 'child_field'];

    protected ?bool $syncEnabled = false; // Overrides parent
}
