<?php

namespace JTD\FirebaseModels\Tests\E2E\Sync;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\E2E\BaseE2ETestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * E2E tests for per-model sync configuration with real Firebase.
 */
#[Group('e2e')]
#[Group('sync')]
class PerModelSyncE2ETest extends BaseE2ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure sync mode for E2E testing
        config([
            'firebase-models.mode' => 'sync',
            'firebase-models.sync.enabled' => true,
            'firebase-models.sync.read_strategy' => 'local_first',
            'firebase-models.sync.write_strategy' => 'both',
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestCollections();
        parent::tearDown();
    }

    #[Test]
    public function it_respects_per_model_sync_with_real_firebase()
    {
        // Create models with different sync settings
        $syncEnabledModel = new TestE2ESyncEnabledModel([
            'name' => 'Sync Enabled Model',
            'description' => 'This model should sync',
        ]);

        $syncDisabledModel = new TestE2ESyncDisabledModel([
            'name' => 'Sync Disabled Model',
            'description' => 'This model should not sync',
        ]);

        // Verify sync configuration
        expect($syncEnabledModel->isSyncModeEnabled())->toBeTrue();
        expect($syncDisabledModel->isSyncModeEnabled())->toBeFalse();

        // Test that models can be created in Firestore
        $syncEnabledModel->save();
        $syncDisabledModel->save();

        // Verify models exist in Firestore
        expect($syncEnabledModel->exists)->toBeTrue();
        expect($syncDisabledModel->exists)->toBeTrue();
        expect($syncEnabledModel->getKey())->not()->toBeNull();
        expect($syncDisabledModel->getKey())->not()->toBeNull();

        // Test retrieval from Firestore
        $retrievedEnabledModel = TestE2ESyncEnabledModel::find($syncEnabledModel->getKey());
        $retrievedDisabledModel = TestE2ESyncDisabledModel::find($syncDisabledModel->getKey());

        expect($retrievedEnabledModel)->not()->toBeNull();
        expect($retrievedDisabledModel)->not()->toBeNull();
        expect($retrievedEnabledModel->name)->toBe('Sync Enabled Model');
        expect($retrievedDisabledModel->name)->toBe('Sync Disabled Model');
    }

    #[Test]
    public function it_handles_inheritance_with_real_firebase()
    {
        // Create parent model with sync enabled
        $parentModel = new TestE2EParentSyncModel([
            'name' => 'Parent Model',
            'type' => 'parent',
        ]);

        // Create child model inheriting sync setting
        $childInheritingModel = new TestE2EChildInheritingModel([
            'name' => 'Child Inheriting',
            'type' => 'child',
            'child_field' => 'inherited',
        ]);

        // Create child model overriding sync setting
        $childOverridingModel = new TestE2EChildOverridingModel([
            'name' => 'Child Overriding',
            'type' => 'child',
            'child_field' => 'overridden',
        ]);

        // Verify sync settings
        expect($parentModel->isSyncModeEnabled())->toBeTrue();
        expect($childInheritingModel->isSyncModeEnabled())->toBeTrue();
        expect($childOverridingModel->isSyncModeEnabled())->toBeFalse();

        // Save all models to Firestore
        $parentModel->save();
        $childInheritingModel->save();
        $childOverridingModel->save();

        // Verify all models exist in Firestore
        expect($parentModel->exists)->toBeTrue();
        expect($childInheritingModel->exists)->toBeTrue();
        expect($childOverridingModel->exists)->toBeTrue();

        // Test retrieval
        $retrievedParent = TestE2EParentSyncModel::find($parentModel->getKey());
        $retrievedChildInheriting = TestE2EChildInheritingModel::find($childInheritingModel->getKey());
        $retrievedChildOverriding = TestE2EChildOverridingModel::find($childOverridingModel->getKey());

        expect($retrievedParent->name)->toBe('Parent Model');
        expect($retrievedChildInheriting->name)->toBe('Child Inheriting');
        expect($retrievedChildOverriding->name)->toBe('Child Overriding');
    }

    #[Test]
    public function it_works_in_cloud_mode_with_per_model_overrides()
    {
        // Switch to cloud mode
        config(['firebase-models.mode' => 'cloud']);

        $forceEnabledModel = new TestE2ESyncEnabledModel([
            'name' => 'Force Enabled in Cloud',
            'description' => 'Should still have sync enabled',
        ]);

        $defaultModel = new TestE2EDefaultSyncModel([
            'name' => 'Default in Cloud',
            'description' => 'Should follow global config',
        ]);

        // Verify sync settings in cloud mode
        expect($forceEnabledModel->isSyncModeEnabled())->toBeTrue();
        expect($defaultModel->isSyncModeEnabled())->toBeFalse();

        // Test that models still work in Firestore
        $forceEnabledModel->save();
        $defaultModel->save();

        expect($forceEnabledModel->exists)->toBeTrue();
        expect($defaultModel->exists)->toBeTrue();

        // Test retrieval
        $retrievedForceEnabled = TestE2ESyncEnabledModel::find($forceEnabledModel->getKey());
        $retrievedDefault = TestE2EDefaultSyncModel::find($defaultModel->getKey());

        expect($retrievedForceEnabled->name)->toBe('Force Enabled in Cloud');
        expect($retrievedDefault->name)->toBe('Default in Cloud');
    }

    #[Test]
    public function it_handles_query_operations_with_mixed_sync_settings()
    {
        // Create multiple models with different sync settings
        $models = [
            new TestE2ESyncEnabledModel(['name' => 'Enabled 1', 'description' => 'First enabled']),
            new TestE2ESyncEnabledModel(['name' => 'Enabled 2', 'description' => 'Second enabled']),
            new TestE2ESyncDisabledModel(['name' => 'Disabled 1', 'description' => 'First disabled']),
            new TestE2EDefaultSyncModel(['name' => 'Default 1', 'description' => 'First default']),
        ];

        // Save all models
        foreach ($models as $model) {
            $model->save();
        }

        // Test queries on each model type
        $enabledModels = TestE2ESyncEnabledModel::where('description', 'like', '%enabled%')->get();
        $disabledModels = TestE2ESyncDisabledModel::where('description', 'like', '%disabled%')->get();
        $defaultModels = TestE2EDefaultSyncModel::where('description', 'like', '%default%')->get();

        expect($enabledModels)->toHaveCount(2);
        expect($disabledModels)->toHaveCount(1);
        expect($defaultModels)->toHaveCount(1);

        // Verify sync settings are maintained during queries
        foreach ($enabledModels as $model) {
            expect($model->isSyncModeEnabled())->toBeTrue();
        }

        foreach ($disabledModels as $model) {
            expect($model->isSyncModeEnabled())->toBeFalse();
        }
    }

    /**
     * Clean up test collections.
     */
    protected function cleanupTestCollections(): void
    {
        $collections = [
            'e2e_sync_enabled_models',
            'e2e_sync_disabled_models',
            'e2e_default_sync_models',
            'e2e_parent_sync_models',
            'e2e_child_inheriting_models',
            'e2e_child_overriding_models',
        ];

        foreach ($collections as $collection) {
            try {
                $this->clearFirestoreCollection($collection);
            } catch (\Exception $e) {
                // Ignore cleanup errors in E2E tests
            }
        }
    }
}

/**
 * E2E test model with sync explicitly enabled.
 */
class TestE2ESyncEnabledModel extends FirestoreModel
{
    protected ?string $collection = 'e2e_sync_enabled_models';

    protected array $fillable = ['name', 'description'];

    protected ?bool $syncEnabled = true;
}

/**
 * E2E test model with sync explicitly disabled.
 */
class TestE2ESyncDisabledModel extends FirestoreModel
{
    protected ?string $collection = 'e2e_sync_disabled_models';

    protected array $fillable = ['name', 'description'];

    protected ?bool $syncEnabled = false;
}

/**
 * E2E test model with default sync configuration.
 */
class TestE2EDefaultSyncModel extends FirestoreModel
{
    protected ?string $collection = 'e2e_default_sync_models';

    protected array $fillable = ['name', 'description'];
    // $syncEnabled is null by default
}

/**
 * E2E parent model with sync enabled.
 */
class TestE2EParentSyncModel extends FirestoreModel
{
    protected ?string $collection = 'e2e_parent_sync_models';

    protected array $fillable = ['name', 'type'];

    protected ?bool $syncEnabled = true;
}

/**
 * E2E child model inheriting parent's sync setting.
 */
class TestE2EChildInheritingModel extends TestE2EParentSyncModel
{
    protected ?string $collection = 'e2e_child_inheriting_models';

    protected array $fillable = ['name', 'type', 'child_field'];
    // Inherits $syncEnabled = true from parent
}

/**
 * E2E child model overriding parent's sync setting.
 */
class TestE2EChildOverridingModel extends TestE2EParentSyncModel
{
    protected ?string $collection = 'e2e_child_overriding_models';

    protected array $fillable = ['name', 'type', 'child_field'];

    protected ?bool $syncEnabled = false; // Overrides parent
}
