<?php

use JTD\FirebaseModels\Firestore\FirestoreModel;
use JTD\FirebaseModels\Tests\Helpers\FirestoreMock;
use Illuminate\Database\Eloquent\Casts\Attribute;

// Test model with legacy accessors
class TestModelWithLegacyAccessors extends FirestoreModel
{
    protected ?string $collection = 'test_models';
    
    protected array $fillable = ['first_name', 'last_name', 'name', 'email', 'age', 'price', 'is_active'];

    protected array $appends = ['full_name', 'display_price'];

    // Legacy accessor
    public function getFullNameAttribute(): string
    {
        return ($this->attributes['first_name'] ?? '') . ' ' . ($this->attributes['last_name'] ?? '');
    }

    // Legacy mutator
    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    // Legacy accessor with transformation
    public function getDisplayPriceAttribute(): string
    {
        return '$' . number_format($this->attributes['price'] ?? 0, 2);
    }

    // Legacy mutator with validation
    public function setAgeAttribute(int $value): void
    {
        $this->attributes['age'] = max(0, min(150, $value));
    }
}

// Test model with modern Attribute accessors
class TestModelWithModernAccessors extends FirestoreModel
{
    protected ?string $collection = 'test_models';
    
    protected array $fillable = ['name', 'email', 'age', 'price', 'is_active', 'metadata'];
    
    protected array $appends = ['formatted_name', 'status_text'];

    // Modern accessor/mutator
    public function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucwords($value),
            set: fn (string $value) => strtolower($value),
        );
    }

    // Modern accessor only
    public function formattedName(): Attribute
    {
        return Attribute::make(
            get: fn () => strtoupper($this->attributes['name'] ?? ''),
        );
    }

    // Modern mutator only
    public function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower(trim($value)),
        );
    }

    // Modern accessor with complex logic
    public function statusText(): Attribute
    {
        return Attribute::make(
            get: function () {
                $isActive = $this->attributes['is_active'] ?? false;
                $age = $this->attributes['age'] ?? 0;
                
                if (!$isActive) {
                    return 'Inactive';
                }
                
                return $age >= 18 ? 'Active Adult' : 'Active Minor';
            }
        );
    }

    // Modern mutator with multiple attributes
    public function metadata(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true),
            set: function ($value) {
                return [
                    'metadata' => json_encode($value),
                    'metadata_updated_at' => now()->toISOString(),
                ];
            }
        );
    }
}

describe('Eloquent Accessors', function () {
    beforeEach(function () {
        FirestoreMock::initialize();
    });

    afterEach(function () {
        FirestoreMock::clear();
    });

    describe('Legacy Accessors', function () {
        it('can use legacy getXAttribute accessors', function () {
            $model = new TestModelWithLegacyAccessors();
            $model->fill([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'price' => 99.99,
            ]);

            expect($model->full_name)->toBe('John Doe');
            expect($model->display_price)->toBe('$99.99');
        });

        it('can use legacy setXAttribute mutators', function () {
            $model = new TestModelWithLegacyAccessors();
            
            $model->email = 'TEST@EXAMPLE.COM';
            expect($model->getAttributes()['email'])->toBe('test@example.com');
            
            $model->age = 200;
            expect($model->getAttributes()['age'])->toBe(150);
            
            $model->age = -5;
            expect($model->getAttributes()['age'])->toBe(0);
        });

        it('includes appended attributes in array conversion', function () {
            $model = new TestModelWithLegacyAccessors();
            $model->fill([
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'price' => 149.50,
            ]);

            $array = $model->toArray();
            
            expect($array)->toHaveKey('full_name');
            expect($array['full_name'])->toBe('Jane Smith');
            expect($array)->toHaveKey('display_price');
            expect($array['display_price'])->toBe('$149.50');
        });

        it('detects legacy accessor methods correctly', function () {
            $model = new TestModelWithLegacyAccessors();
            
            expect($model->hasGetMutator('full_name'))->toBeTrue();
            expect($model->hasGetMutator('display_price'))->toBeTrue();
            expect($model->hasGetMutator('non_existent'))->toBeFalse();
        });

        it('detects legacy mutator methods correctly', function () {
            $model = new TestModelWithLegacyAccessors();
            
            expect($model->hasSetMutator('email'))->toBeTrue();
            expect($model->hasSetMutator('age'))->toBeTrue();
            expect($model->hasSetMutator('non_existent'))->toBeFalse();
        });
    });

    describe('Modern Attribute Accessors', function () {
        it('can use modern Attribute accessors', function () {
            $model = new TestModelWithModernAccessors();
            $model->fill([
                'name' => 'john doe',
                'is_active' => true,
                'age' => 25,
            ]);

            expect($model->name)->toBe('John Doe'); // ucwords applied
            expect($model->formatted_name)->toBe('JOHN DOE'); // uppercase applied
            expect($model->status_text)->toBe('Active Adult');
        });

        it('can use modern Attribute mutators', function () {
            $model = new TestModelWithModernAccessors();
            
            $model->name = 'JANE SMITH';
            expect($model->getAttributes()['name'])->toBe('jane smith'); // strtolower applied
            
            $model->email = '  TEST@EXAMPLE.COM  ';
            expect($model->getAttributes()['email'])->toBe('test@example.com'); // trimmed and lowercased
        });

        it('supports complex modern accessors', function () {
            $model = new TestModelWithModernAccessors();
            
            $model->fill(['is_active' => true, 'age' => 16]);
            expect($model->status_text)->toBe('Active Minor');
            
            $model->fill(['is_active' => false, 'age' => 25]);
            expect($model->status_text)->toBe('Inactive');
            
            $model->fill(['is_active' => true, 'age' => 30]);
            expect($model->status_text)->toBe('Active Adult');
        });

        it('supports mutators that set multiple attributes', function () {
            $model = new TestModelWithModernAccessors();
            
            $metadata = ['key' => 'value', 'count' => 42];
            $model->metadata = $metadata;
            
            expect($model->getAttributes()['metadata'])->toBe(json_encode($metadata));
            expect($model->getAttributes())->toHaveKey('metadata_updated_at');
            
            // Test accessor
            expect($model->metadata)->toBe($metadata);
        });

        it('detects modern accessor methods correctly', function () {
            $model = new TestModelWithModernAccessors();
            
            expect($model->hasGetMutator('name'))->toBeTrue();
            expect($model->hasGetMutator('formatted_name'))->toBeTrue();
            expect($model->hasGetMutator('status_text'))->toBeTrue();
            expect($model->hasGetMutator('non_existent'))->toBeFalse();
        });

        it('detects modern mutator methods correctly', function () {
            $model = new TestModelWithModernAccessors();
            
            expect($model->hasSetMutator('name'))->toBeTrue();
            expect($model->hasSetMutator('email'))->toBeTrue();
            expect($model->hasSetMutator('metadata'))->toBeTrue();
            expect($model->hasSetMutator('non_existent'))->toBeFalse();
        });
    });

    describe('Accessor/Mutator Integration', function () {
        it('can mix legacy and modern accessors in the same model', function () {
            // Create a model that uses both patterns
            $model = new class extends FirestoreModel {
                protected ?string $collection = 'mixed_models';
                protected array $fillable = ['name', 'email'];
                protected array $appends = ['legacy_name', 'modern_name'];

                // Legacy accessor
                public function getLegacyNameAttribute(): string
                {
                    return 'Legacy: ' . ($this->attributes['name'] ?? '');
                }

                // Modern accessor
                public function modernName(): Attribute
                {
                    return Attribute::make(
                        get: fn () => 'Modern: ' . ($this->attributes['name'] ?? ''),
                    );
                }
            };

            $model->name = 'Test User';
            
            expect($model->legacy_name)->toBe('Legacy: Test User');
            expect($model->modern_name)->toBe('Modern: Test User');
            
            $array = $model->toArray();
            expect($array['legacy_name'])->toBe('Legacy: Test User');
            expect($array['modern_name'])->toBe('Modern: Test User');
        });

        it('prioritizes legacy accessors over modern ones', function () {
            $model = new class extends FirestoreModel {
                protected ?string $collection = 'priority_models';
                protected array $fillable = ['name'];

                // Legacy accessor
                public function getNameAttribute(string $value): string
                {
                    return 'Legacy: ' . $value;
                }

                // Modern accessor (should be ignored due to legacy priority)
                public function name(): Attribute
                {
                    return Attribute::make(
                        get: fn (string $value) => 'Modern: ' . $value,
                    );
                }
            };

            $model->fill(['name' => 'Test']);
            
            // Legacy should take priority
            expect($model->name)->toBe('Legacy: Test');
        });

        it('can detect individual accessor methods', function () {
            $model = new TestModelWithModernAccessors();

            // Test individual accessor detection
            expect($model->hasGetMutator('name'))->toBeTrue();
            expect($model->hasGetMutator('formatted_name'))->toBeTrue();
            expect($model->hasGetMutator('status_text'))->toBeTrue();
            expect($model->hasSetMutator('name'))->toBeTrue();
            expect($model->hasSetMutator('email'))->toBeTrue();
            expect($model->hasSetMutator('metadata'))->toBeTrue();

            // Test that non-existent accessors return false
            expect($model->hasGetMutator('non_existent'))->toBeFalse();
            expect($model->hasSetMutator('non_existent'))->toBeFalse();
        });
    });

    describe('Array and JSON Conversion', function () {
        it('applies accessors during array conversion', function () {
            $model = new TestModelWithModernAccessors();
            $model->fill([
                'name' => 'test user',
                'is_active' => true,
                'age' => 25,
            ]);

            $array = $model->toArray();

            expect($array['name'])->toBe('Test User'); // ucwords applied
            expect($array['formatted_name'])->toBe('TEST USER'); // uppercase applied
            expect($array['status_text'])->toBe('Active Adult');
        });

        it('applies accessors during JSON conversion', function () {
            $model = new TestModelWithModernAccessors();
            $model->fill([
                'name' => 'json user',
                'is_active' => false,
                'age' => 30,
            ]);

            $json = json_decode($model->toJson(), true);
            
            expect($json['name'])->toBe('Json User'); // ucwords applied
            expect($json['formatted_name'])->toBe('JSON USER'); // uppercase applied
            expect($json['status_text'])->toBe('Inactive');
        });

        it('respects hidden and visible attributes', function () {
            $model = new class extends FirestoreModel {
                protected ?string $collection = 'visibility_models';
                protected array $fillable = ['name', 'email', 'secret'];
                protected array $hidden = ['secret'];
                protected array $appends = ['display_name'];

                public function getDisplayNameAttribute(): string
                {
                    return 'Display: ' . ($this->attributes['name'] ?? '');
                }
            };

            $model->fill([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'secret' => 'hidden_value',
            ]);

            $array = $model->toArray();
            
            expect($array)->toHaveKey('name');
            expect($array)->toHaveKey('email');
            expect($array)->toHaveKey('display_name');
            expect($array)->not->toHaveKey('secret'); // Should be hidden
            expect($array['display_name'])->toBe('Display: Test User');
        });
    });
});
