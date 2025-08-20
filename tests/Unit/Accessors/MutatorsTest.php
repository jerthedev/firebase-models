<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use Illuminate\Database\Eloquent\Casts\Attribute;

// Test model with various mutator patterns
class TestModelWithMutators extends FirestoreModel
{
    protected ?string $collection = 'mutator_models';
    
    protected array $fillable = [
        'name', 'email', 'password', 'phone', 'tags', 'settings', 'coordinates'
    ];

    // Legacy mutator - simple transformation
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim(ucwords($value));
    }

    // Legacy mutator - complex validation and transformation
    public function setEmailAttribute(string $value): void
    {
        $email = strtolower(trim($value));
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
        
        $this->attributes['email'] = $email;
        $this->attributes['email_domain'] = substr($email, strpos($email, '@') + 1);
    }

    // Legacy mutator - hashing
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_DEFAULT);
    }

    // Modern mutator - phone formatting
    public function phone(): Attribute
    {
        return Attribute::make(
            set: function (string $value) {
                // Remove all non-digits
                $digits = preg_replace('/\D/', '', $value);
                
                // Format as (XXX) XXX-XXXX if US number
                if (strlen($digits) === 10) {
                    return sprintf('(%s) %s-%s', 
                        substr($digits, 0, 3),
                        substr($digits, 3, 3),
                        substr($digits, 6, 4)
                    );
                }
                
                return $digits;
            }
        );
    }

    // Modern mutator - array handling
    public function tags(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true) ?? [],
            set: function ($value) {
                if (is_string($value)) {
                    $value = explode(',', $value);
                }
                
                $tags = array_map('trim', (array) $value);
                $tags = array_filter($tags); // Remove empty values
                $tags = array_unique($tags); // Remove duplicates
                
                return json_encode(array_values($tags));
            }
        );
    }

    // Modern mutator - multiple attributes
    public function settings(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true) ?? [],
            set: function ($value) {
                return [
                    'settings' => json_encode($value),
                    'settings_updated_at' => now()->toISOString(),
                    'settings_version' => ($this->attributes['settings_version'] ?? 0) + 1,
                ];
            }
        );
    }

    // Modern mutator - coordinate validation
    public function coordinates(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if (is_string($value)) {
                    $parts = explode(',', $value);
                    $value = [
                        'lat' => (float) trim($parts[0] ?? 0),
                        'lng' => (float) trim($parts[1] ?? 0),
                    ];
                }
                
                $lat = $value['lat'] ?? 0;
                $lng = $value['lng'] ?? 0;
                
                // Validate coordinates
                if ($lat < -90 || $lat > 90) {
                    throw new \InvalidArgumentException('Latitude must be between -90 and 90');
                }
                
                if ($lng < -180 || $lng > 180) {
                    throw new \InvalidArgumentException('Longitude must be between -180 and 180');
                }
                
                return json_encode(['lat' => $lat, 'lng' => $lng]);
            }
        );
    }
}

describe('Eloquent Mutators', function () {
    beforeEach(function () {
        FirestoreMock::initialize();
    });

    afterEach(function () {
        FirestoreMock::clear();
    });

    describe('Legacy Mutators', function () {
        it('can use legacy setXAttribute mutators', function () {
            $model = new TestModelWithMutators();
            
            $model->name = '  john doe  ';
            expect($model->getAttributes()['name'])->toBe('John Doe');
            
            $model->password = 'secret123';
            expect($model->getAttributes()['password'])->toMatch('/^\$2y\$/'); // bcrypt hash
            expect(password_verify('secret123', $model->getAttributes()['password']))->toBeTrue();
        });

        it('can use legacy mutators with validation', function () {
            $model = new TestModelWithMutators();
            
            $model->email = '  TEST@EXAMPLE.COM  ';
            expect($model->getAttributes()['email'])->toBe('test@example.com');
            expect($model->getAttributes()['email_domain'])->toBe('example.com');
            
            expect(function () use ($model) {
                $model->email = 'invalid-email';
            })->toThrow(\InvalidArgumentException::class);
        });

        it('can use legacy mutators with side effects', function () {
            $model = new TestModelWithMutators();
            
            $model->email = 'user@domain.org';
            
            // Should set both email and email_domain
            expect($model->getAttributes()['email'])->toBe('user@domain.org');
            expect($model->getAttributes()['email_domain'])->toBe('domain.org');
        });
    });

    describe('Modern Attribute Mutators', function () {
        it('can use modern Attribute mutators', function () {
            $model = new TestModelWithMutators();
            
            $model->phone = '1234567890';
            expect($model->getAttributes()['phone'])->toBe('(123) 456-7890');
            
            $model->phone = '(555) 123-4567';
            expect($model->getAttributes()['phone'])->toBe('(555) 123-4567');
            
            $model->phone = '555.123.4567';
            expect($model->getAttributes()['phone'])->toBe('(555) 123-4567');
        });

        it('can use modern mutators with array handling', function () {
            $model = new TestModelWithMutators();
            
            // Test string input
            $model->tags = 'php, laravel, firestore, testing';
            $storedTags = json_decode($model->getAttributes()['tags'], true);
            expect($storedTags)->toBe(['php', 'laravel', 'firestore', 'testing']);
            
            // Test array input
            $model->tags = ['javascript', 'vue', 'javascript']; // with duplicate
            $storedTags = json_decode($model->getAttributes()['tags'], true);
            expect($storedTags)->toBe(['javascript', 'vue']); // duplicate removed
            
            // Test accessor
            expect($model->tags)->toBe(['javascript', 'vue']);
        });

        it('can use modern mutators that set multiple attributes', function () {
            $model = new TestModelWithMutators();
            
            $settings = ['theme' => 'dark', 'notifications' => true];
            $model->settings = $settings;
            
            expect($model->getAttributes()['settings'])->toBe(json_encode($settings));
            expect($model->getAttributes())->toHaveKey('settings_updated_at');
            expect($model->getAttributes()['settings_version'])->toBe(1);
            
            // Update again to test version increment
            $model->settings = ['theme' => 'light'];
            expect($model->getAttributes()['settings_version'])->toBe(2);
            
            // Test accessor
            expect($model->settings)->toBe(['theme' => 'light']);
        });

        it('can use modern mutators with validation', function () {
            $model = new TestModelWithMutators();
            
            // Valid coordinates
            $model->coordinates = ['lat' => 40.7128, 'lng' => -74.0060]; // NYC
            $stored = json_decode($model->getAttributes()['coordinates'], true);
            expect($stored['lat'])->toBe(40.7128);
            expect($stored['lng'])->toBe(-74.0060);
            
            // String input
            $model->coordinates = '34.0522, -118.2437'; // LA
            $stored = json_decode($model->getAttributes()['coordinates'], true);
            expect($stored['lat'])->toBe(34.0522);
            expect($stored['lng'])->toBe(-118.2437);
            
            // Invalid latitude
            expect(function () use ($model) {
                $model->coordinates = ['lat' => 91, 'lng' => 0];
            })->toThrow(\InvalidArgumentException::class);
            
            // Invalid longitude
            expect(function () use ($model) {
                $model->coordinates = ['lat' => 0, 'lng' => 181];
            })->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('Mutator Detection and Caching', function () {
        it('correctly detects legacy mutators', function () {
            $model = new TestModelWithMutators();
            
            expect($model->hasSetMutator('name'))->toBeTrue();
            expect($model->hasSetMutator('email'))->toBeTrue();
            expect($model->hasSetMutator('password'))->toBeTrue();
            expect($model->hasSetMutator('non_existent'))->toBeFalse();
        });

        it('correctly detects modern mutators', function () {
            $model = new TestModelWithMutators();
            
            expect($model->hasSetMutator('phone'))->toBeTrue();
            expect($model->hasSetMutator('tags'))->toBeTrue();
            expect($model->hasSetMutator('settings'))->toBeTrue();
            expect($model->hasSetMutator('coordinates'))->toBeTrue();
        });

        it('caches mutator methods for performance', function () {
            $class = TestModelWithMutators::class;

            // Clear cache first
            TestModelWithMutators::clearMutatorCache($class);

            $model = new TestModelWithMutators();

            // First call should populate cache
            $hasSetMutator1 = $model->hasSetMutator('name');

            // Trigger cache population by calling getMutatedAttributes
            $model->getMutatedAttributes();
            $cachedMutators1 = TestModelWithMutators::getCachedMutators();

            // Second call should use cache
            $hasSetMutator2 = $model->hasSetMutator('name');
            $cachedMutators2 = TestModelWithMutators::getCachedMutators();

            expect($hasSetMutator1)->toBe($hasSetMutator2);
            expect($cachedMutators1)->toBe($cachedMutators2);
            expect($cachedMutators1['legacy'])->toHaveKey($class);
        });

        it('can detect individual mutator methods', function () {
            $model = new TestModelWithMutators();

            // Test legacy mutator detection
            expect($model->hasSetMutator('name'))->toBeTrue();
            expect($model->hasSetMutator('email'))->toBeTrue();
            expect($model->hasSetMutator('password'))->toBeTrue();

            // Test modern mutator detection
            expect($model->hasSetMutator('phone'))->toBeTrue();
            expect($model->hasSetMutator('tags'))->toBeTrue();
            expect($model->hasSetMutator('settings'))->toBeTrue();
            expect($model->hasSetMutator('coordinates'))->toBeTrue();

            // Test that non-existent mutators return false
            expect($model->hasSetMutator('non_existent'))->toBeFalse();
        });
    });

    describe('Mass Assignment with Mutators', function () {
        it('applies mutators during mass assignment', function () {
            $model = new TestModelWithMutators();
            
            $model->fill([
                'name' => '  jane smith  ',
                'email' => '  JANE@EXAMPLE.COM  ',
                'phone' => '5551234567',
                'tags' => 'laravel, php, testing',
            ]);
            
            expect($model->getAttributes()['name'])->toBe('Jane Smith');
            expect($model->getAttributes()['email'])->toBe('jane@example.com');
            expect($model->getAttributes()['email_domain'])->toBe('example.com');
            expect($model->getAttributes()['phone'])->toBe('(555) 123-4567');
            
            $tags = json_decode($model->getAttributes()['tags'], true);
            expect($tags)->toBe(['laravel', 'php', 'testing']);
        });

        it('can set multiple mutated attributes at once', function () {
            $model = new TestModelWithMutators();
            
            $model->setMutatedAttributes([
                'name' => 'test user',
                'email' => 'test@example.com',
                'phone' => '1234567890',
            ]);
            
            expect($model->getAttributes()['name'])->toBe('Test User');
            expect($model->getAttributes()['email'])->toBe('test@example.com');
            expect($model->getAttributes()['phone'])->toBe('(123) 456-7890');
        });
    });

    describe('Change Tracking with Mutators', function () {
        it('can track changes in mutated attributes', function () {
            $model = new TestModelWithMutators();
            $model->fill(['name' => 'original name']);
            $model->syncOriginal(); // Set as original state
            
            $model->name = 'new name';
            
            expect($model->mutatedAttributeHasChanged('name'))->toBeTrue();
            expect($model->getOriginalMutatedValue('name'))->toBe('Original Name'); // ucwords applied
        });

        it('can get all changed mutated attributes', function () {
            $model = new TestModelWithMutators();
            $model->fill([
                'name' => 'original name',
                'email' => 'original@example.com',
                'phone' => '1234567890',
            ]);
            $model->syncOriginal();
            
            $model->name = 'new name';
            $model->email = 'new@example.com';
            // phone unchanged
            
            $changed = $model->getChangedMutatedAttributes();
            
            expect($changed)->toHaveKey('name');
            expect($changed)->toHaveKey('email');
            expect($changed)->not->toHaveKey('phone');
            expect($changed['name'])->toBe('New Name');
            expect($changed['email'])->toBe('new@example.com');
        });

        it('can reset mutated attributes to original values', function () {
            $model = new TestModelWithMutators();
            $model->fill(['name' => 'original name']);
            $model->syncOriginal();
            
            $model->name = 'changed name';
            expect($model->getAttributes()['name'])->toBe('Changed Name');
            
            $model->resetMutatedAttributes();
            expect($model->getAttributes()['name'])->toBe('Original Name');
        });
    });
});
