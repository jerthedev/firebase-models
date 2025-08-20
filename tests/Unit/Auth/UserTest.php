<?php

use JTD\FirebaseModels\Auth\User;

describe('User Model', function () {
    beforeEach(function () {
        $this->clearFirestoreMocks();
    });

    describe('Model Configuration', function () {
        it('has correct collection name', function () {
            $user = new User();
            
            expect($user->getCollection())->toBe('users');
        });

        it('has correct fillable attributes', function () {
            $user = new User([
                'uid' => 'test-uid',
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'timezone' => 'America/New_York',
                'locale' => 'en_US',
                'preferences' => ['theme' => 'dark']
            ]);

            expect($user->uid)->toBe('test-uid');
            expect($user->email)->toBe('test@example.com');
            expect($user->first_name)->toBe('John');
            expect($user->last_name)->toBe('Doe');
            expect($user->timezone)->toBe('America/New_York');
            expect($user->locale)->toBe('en_US');
            expect($user->preferences)->toBe(['theme' => 'dark']);
        });
    });

    describe('Computed Attributes', function () {
        it('generates full name from first and last name', function () {
            $user = new User([
                'first_name' => 'John',
                'last_name' => 'Doe'
            ]);

            expect($user->full_name)->toBe('John Doe');
        });

        it('falls back to name when no first/last name', function () {
            $user = new User([
                'name' => 'John Doe'
            ]);

            expect($user->full_name)->toBe('John Doe');
        });

        it('generates initials from full name', function () {
            $user = new User([
                'first_name' => 'John',
                'last_name' => 'Doe'
            ]);

            expect($user->initials)->toBe('JD');
        });

        it('generates initials from email when no name', function () {
            $user = new User([
                'email' => 'john.doe@example.com'
            ]);

            expect($user->initials)->toBe('JO');
        });

        it('generates avatar URL from photo_url', function () {
            $user = new User([
                'photo_url' => 'https://example.com/photo.jpg'
            ]);

            expect($user->avatar_url)->toBe('https://example.com/photo.jpg');
        });

        it('generates Gravatar URL when no photo_url', function () {
            $user = new User([
                'email' => 'test@example.com'
            ]);

            $expectedHash = md5('test@example.com');
            $expectedUrl = "https://www.gravatar.com/avatar/{$expectedHash}?d=identicon&s=200";

            expect($user->avatar_url)->toBe($expectedUrl);
        });
    });

    describe('Preferences Management', function () {
        it('can get and set preferences', function () {
            $user = new User();

            $user->setPreference('theme', 'dark');
            $user->setPreference('language', 'en');

            expect($user->getPreference('theme'))->toBe('dark');
            expect($user->getPreference('language'))->toBe('en');
            expect($user->getPreference('nonexistent', 'default'))->toBe('default');
        });

        it('maintains preferences array', function () {
            $user = new User();

            $user->setPreference('theme', 'dark');
            $user->setPreference('notifications', true);

            $preferences = $user->getAttribute('preferences');
            expect($preferences)->toBe([
                'theme' => 'dark',
                'notifications' => true
            ]);
        });
    });

    describe('Role and Permission Helpers', function () {
        it('can check if user is admin', function () {
            $adminUser = new User([
                'custom_claims' => ['roles' => ['admin']]
            ]);
            
            $regularUser = new User([
                'custom_claims' => ['roles' => ['user']]
            ]);

            expect($adminUser->isAdmin())->toBeTrue();
            expect($regularUser->isAdmin())->toBeFalse();
        });

        it('can check admin via custom claim', function () {
            $adminUser = new User([
                'custom_claims' => ['admin' => true]
            ]);

            expect($adminUser->isAdmin())->toBeTrue();
        });

        it('can check if user is moderator', function () {
            $moderatorUser = new User([
                'custom_claims' => ['roles' => ['moderator']]
            ]);

            expect($moderatorUser->isModerator())->toBeTrue();
        });
    });

    describe('Timezone and Locale', function () {
        it('returns user timezone or default', function () {
            $userWithTimezone = new User(['timezone' => 'Europe/London']);
            $userWithoutTimezone = new User();

            expect($userWithTimezone->getTimezone())->toBe('Europe/London');
            expect($userWithoutTimezone->getTimezone())->toBe(config('app.timezone', 'UTC'));
        });

        it('returns user locale or default', function () {
            $userWithLocale = new User(['locale' => 'fr_FR']);
            $userWithoutLocale = new User();

            expect($userWithLocale->getLocale())->toBe('fr_FR');
            expect($userWithoutLocale->getLocale())->toBe(config('app.locale', 'en'));
        });
    });

    describe('Array Conversion', function () {
        it('includes computed attributes in array', function () {
            $user = new User([
                'uid' => 'test-uid',
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email_verified_at' => now(),
                'custom_claims' => ['roles' => ['admin']]
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
        });

        it('hides sensitive data', function () {
            $user = new User([
                'uid' => 'test-uid',
                'custom_claims' => ['secret' => 'data']
            ]);

            $array = $user->toArray();

            expect($array)->not->toHaveKey('password');
            expect($array)->not->toHaveKey('remember_token');
            expect($array)->not->toHaveKey('firebase_token');
            expect($array)->not->toHaveKey('custom_claims');
        });
    });
});
