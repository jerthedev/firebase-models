<?php

namespace JTD\FirebaseModels\Tests\Unit\Auth;

use JTD\FirebaseModels\Auth\User;
use JTD\FirebaseModels\Tests\TestSuites\UnitTestSuite;
use PHPUnit\Framework\Attributes\Test;

/**
 * User Model Test
 *
 * Updated to use UnitTestSuite for optimized performance and memory management.
 */
class UserTest extends UnitTestSuite
{
    protected function setUp(): void
    {
        // Configure test requirements for user model testing
        $this->setTestRequirements([
            'document_count' => 30,
            'memory_constraint' => true,
            'needs_full_mockery' => false,
        ]);

        parent::setUp();

        $this->clearFirestoreMocks();
    }

    // ========================================
    // MODEL CONFIGURATION TESTS
    // ========================================

    #[Test]
    public function it_has_correct_collection_name()
    {
        $user = new User();

        expect($user->getCollection())->toBe('users');
    }

    #[Test]
    public function it_has_correct_fillable_attributes()
    {
        $user = new User([
            'uid' => 'test-uid',
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'timezone' => 'America/New_York',
            'locale' => 'en_US',
            'preferences' => ['theme' => 'dark'],
        ]);

        expect($user->uid)->toBe('test-uid');
        expect($user->email)->toBe('test@example.com');
        expect($user->first_name)->toBe('John');
        expect($user->last_name)->toBe('Doe');
        expect($user->timezone)->toBe('America/New_York');
        expect($user->locale)->toBe('en_US');
        expect($user->preferences)->toBe(['theme' => 'dark']);
    }

    // ========================================
    // COMPUTED ATTRIBUTES TESTS
    // ========================================

    #[Test]
    public function it_generates_full_name_from_first_and_last_name()
    {
        $user = new User([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        expect($user->full_name)->toBe('John Doe');
    }

    #[Test]
    public function it_falls_back_to_name_when_no_first_last_name()
    {
        $user = new User([
            'name' => 'John Doe',
        ]);

        expect($user->full_name)->toBe('John Doe');
    }

    #[Test]
    public function it_generates_initials_from_full_name()
    {
        $user = new User([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        expect($user->initials)->toBe('JD');
    }

    #[Test]
    public function it_generates_initials_from_email_when_no_name()
    {
        $user = new User([
            'email' => 'john.doe@example.com',
        ]);

        expect($user->initials)->toBe('JO');
    }

    #[Test]
    public function it_generates_avatar_url_from_photo_url()
    {
        $user = new User([
            'photo_url' => 'https://example.com/photo.jpg',
        ]);

        expect($user->avatar_url)->toBe('https://example.com/photo.jpg');
    }

    #[Test]
    public function it_generates_gravatar_url_when_no_photo_url()
    {
        $user = new User([
            'email' => 'test@example.com',
        ]);

        $expectedHash = md5('test@example.com');
        $expectedUrl = "https://www.gravatar.com/avatar/{$expectedHash}?d=identicon&s=200";

        expect($user->avatar_url)->toBe($expectedUrl);
    }

    #[Test]
    public function it_can_get_and_set_preferences()
    {
        $user = new User();

        $user->setPreference('theme', 'dark');
        $user->setPreference('language', 'en');

        expect($user->getPreference('theme'))->toBe('dark');
        expect($user->getPreference('language'))->toBe('en');
        expect($user->getPreference('nonexistent', 'default'))->toBe('default');
    }

    #[Test]
    public function it_can_check_if_user_is_admin()
    {
        $adminUser = new User([
            'custom_claims' => ['roles' => ['admin']],
        ]);

        $regularUser = new User([
            'custom_claims' => ['roles' => ['user']],
        ]);

        expect($adminUser->isAdmin())->toBeTrue();
        expect($regularUser->isAdmin())->toBeFalse();
    }

    #[Test]
    public function it_includes_computed_attributes_in_array()
    {
        $user = new User([
            'uid' => 'test-uid',
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_verified_at' => now(),
            'custom_claims' => ['roles' => ['admin']],
        ]);

        $array = $user->toArray();

        expect($array)->toHaveKey('full_name');
        expect($array)->toHaveKey('initials');
        expect($array)->toHaveKey('avatar_url');
        expect($array)->toHaveKey('is_admin');
        expect($array)->toHaveKey('has_verified_email');

        expect($array['full_name'])->toBe('John Doe');
        expect($array['initials'])->toBe('JD');
        expect($array['is_admin'])->toBeTrue();
        expect($array['has_verified_email'])->toBeTrue();
    }
}
