<?php

namespace JTD\FirebaseModels\Tests\Unit\Sync;

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Test;

/**
 * Test per-model sync configuration functionality.
 */
class PerModelSyncConfigTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any previous config
        config(['firebase-models.mode' => 'cloud']);
    }

    #[Test]
    public function it_uses_global_config_when_model_property_is_null()
    {
        // Test with global sync mode enabled
        config(['firebase-models.mode' => 'sync']);

        $model = new TestModelWithDefaultSync();
        expect($model->isSyncModeEnabled())->toBeTrue();

        // Test with global sync mode disabled
        config(['firebase-models.mode' => 'cloud']);

        $model = new TestModelWithDefaultSync();
        expect($model->isSyncModeEnabled())->toBeFalse();
    }

    #[Test]
    public function it_respects_model_level_sync_enabled_true()
    {
        // Test with global sync disabled but model sync enabled
        config(['firebase-models.mode' => 'cloud']);

        $model = new TestModelWithSyncEnabled();
        expect($model->isSyncModeEnabled())->toBeTrue();

        // Test with global sync enabled and model sync enabled
        config(['firebase-models.mode' => 'sync']);

        $model = new TestModelWithSyncEnabled();
        expect($model->isSyncModeEnabled())->toBeTrue();
    }

    #[Test]
    public function it_respects_model_level_sync_enabled_false()
    {
        // Test with global sync enabled but model sync disabled
        config(['firebase-models.mode' => 'sync']);

        $model = new TestModelWithSyncDisabled();
        expect($model->isSyncModeEnabled())->toBeFalse();

        // Test with global sync disabled and model sync disabled
        config(['firebase-models.mode' => 'cloud']);

        $model = new TestModelWithSyncDisabled();
        expect($model->isSyncModeEnabled())->toBeFalse();
    }

    #[Test]
    public function it_handles_inheritance_correctly()
    {
        config(['firebase-models.mode' => 'cloud']);

        // Child inherits parent's sync setting
        $childWithInheritedEnabled = new TestChildModelInheritingEnabled();
        expect($childWithInheritedEnabled->isSyncModeEnabled())->toBeTrue();

        // Child overrides parent's sync setting
        $childWithOverride = new TestChildModelOverridingParent();
        expect($childWithOverride->isSyncModeEnabled())->toBeFalse();
    }

    #[Test]
    public function it_affects_supports_sync_mode_method()
    {
        config(['firebase-models.mode' => 'sync']);

        // Create models with mocked hasLocalTable method
        $enabledModel = new class() extends TestModelWithSyncEnabled
        {
            public function hasLocalTable(): bool
            {
                return true;
            }
        };
        expect($enabledModel->supportsSyncMode())->toBeTrue();

        $disabledModel = new class() extends TestModelWithSyncDisabled
        {
            public function hasLocalTable(): bool
            {
                return true;
            }
        };
        expect($disabledModel->supportsSyncMode())->toBeFalse();
    }

    #[Test]
    public function it_affects_sync_status_reporting()
    {
        config(['firebase-models.mode' => 'cloud']);

        $enabledModel = new TestModelWithSyncEnabled();
        $status = $enabledModel->getSyncStatus();

        expect($status['sync_mode_enabled'])->toBeTrue();

        $disabledModel = new TestModelWithSyncDisabled();
        $status = $disabledModel->getSyncStatus();

        expect($status['sync_mode_enabled'])->toBeFalse();
    }

    #[Test]
    public function it_affects_read_and_write_strategies()
    {
        config(['firebase-models.mode' => 'sync']);
        config(['firebase-models.sync.read_strategy' => 'local_first']);
        config(['firebase-models.sync.write_strategy' => 'both']);

        // Create models with mocked hasLocalTable method
        $enabledModel = new class() extends TestModelWithSyncEnabled
        {
            public function hasLocalTable(): bool
            {
                return true;
            }
        };

        $disabledModel = new class() extends TestModelWithSyncDisabled
        {
            public function hasLocalTable(): bool
            {
                return true;
            }
        };

        // Enabled model should use sync strategies
        expect($enabledModel->shouldReadFromLocal())->toBeTrue();
        expect($enabledModel->shouldWriteToLocal())->toBeTrue();
        expect($enabledModel->shouldWriteToFirestore())->toBeTrue();

        // Disabled model should not use sync strategies
        expect($disabledModel->shouldReadFromLocal())->toBeFalse();
        expect($disabledModel->shouldWriteToLocal())->toBeFalse();
        expect($disabledModel->shouldWriteToFirestore())->toBeTrue(); // Still writes to Firestore
    }
}

/**
 * Test model with default sync configuration (null).
 */
class TestModelWithDefaultSync extends FirestoreModel
{
    protected ?string $collection = 'test_default';

    protected array $fillable = ['name', 'email'];
    // $syncEnabled is null by default
}

/**
 * Test model with sync explicitly enabled.
 */
class TestModelWithSyncEnabled extends FirestoreModel
{
    protected ?string $collection = 'test_enabled';

    protected array $fillable = ['name', 'email'];

    protected ?bool $syncEnabled = true;
}

/**
 * Test model with sync explicitly disabled.
 */
class TestModelWithSyncDisabled extends FirestoreModel
{
    protected ?string $collection = 'test_disabled';

    protected array $fillable = ['name', 'email'];

    protected ?bool $syncEnabled = false;
}

/**
 * Parent model with sync enabled.
 */
class TestParentModelWithSyncEnabled extends FirestoreModel
{
    protected ?string $collection = 'test_parent';

    protected array $fillable = ['name'];

    protected ?bool $syncEnabled = true;
}

/**
 * Child model inheriting parent's sync setting.
 */
class TestChildModelInheritingEnabled extends TestParentModelWithSyncEnabled
{
    protected array $fillable = ['name', 'child_field'];
    // Inherits $syncEnabled = true from parent
}

/**
 * Child model overriding parent's sync setting.
 */
class TestChildModelOverridingParent extends TestParentModelWithSyncEnabled
{
    protected array $fillable = ['name', 'child_field'];

    protected ?bool $syncEnabled = false; // Overrides parent
}
