<?php

namespace JTD\FirebaseModels\Tests\Models;

use JTD\FirebaseModels\Auth\FirebaseAuthenticatable;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use Illuminate\Support\Carbon;

/**
 * Test User model for unit testing.
 */
class TestUser extends FirestoreModel
{
    use FirebaseAuthenticatable;

    protected ?string $collection = 'users';

    protected array $fillable = [
        'uid',
        'name',
        'email',
        'active',
        'role',
        'permissions',
        'profile_data',
        'settings',
        'last_login',
        'email_verified_at',
        'phone',
        'avatar_url',
        'bio',
        'location',
        'website',
        'social_links',
    ];

    protected array $casts = [
        'active' => 'boolean',
        'permissions' => 'array',
        'profile_data' => 'array',
        'settings' => 'array',
        'social_links' => 'array',
        'last_login' => 'datetime',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected array $hidden = [
        'password',
        'remember_token',
        'secret_key',
        'internal_notes',
    ];

    protected array $appends = [
        'formatted_name',
        'is_verified',
    ];

    /**
     * Set the collection name dynamically for testing.
     */
    public function setCollection(string $collection): static
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for users with specific role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope for verified users.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope for users who logged in recently.
     */
    public function scopeRecentlyActive($query, int $days = 30)
    {
        return $query->where('last_login', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Accessor for formatted name.
     */
    public function getFormattedNameAttribute(): string
    {
        return ucwords($this->name ?? 'Unknown User');
    }

    /**
     * Accessor for verification status.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mutator for email (always lowercase).
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Mutator for name (title case).
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = ucwords(strtolower($value));
    }

    /**
     * Check if user has permission.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    /**
     * Add permission to user.
     */
    public function addPermission(string $permission): static
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
        }
        return $this;
    }

    /**
     * Remove permission from user.
     */
    public function removePermission(string $permission): static
    {
        $permissions = $this->permissions ?? [];
        $this->permissions = array_values(array_filter($permissions, fn($p) => $p !== $permission));
        return $this;
    }

    /**
     * Check if user has role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasPermission('admin');
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->active === true;
    }

    /**
     * Check if user is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark user as verified.
     */
    public function markAsVerified(): static
    {
        $this->email_verified_at = Carbon::now();
        return $this;
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin(): static
    {
        $this->last_login = Carbon::now();
        return $this;
    }

    /**
     * Get user's full profile data.
     */
    public function getFullProfile(): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'active' => $this->active,
            'verified' => $this->isVerified(),
            'permissions' => $this->permissions ?? [],
            'profile_data' => $this->profile_data ?? [],
            'last_login' => $this->last_login?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Create user from Firebase user data.
     */
    public static function createFromFirebaseUser(array $firebaseUserData): static
    {
        return new static([
            'uid' => $firebaseUserData['uid'],
            'email' => $firebaseUserData['email'] ?? null,
            'name' => $firebaseUserData['displayName'] ?? null,
            'email_verified_at' => ($firebaseUserData['emailVerified'] ?? false) ? Carbon::now() : null,
            'active' => !($firebaseUserData['disabled'] ?? false),
            'phone' => $firebaseUserData['phoneNumber'] ?? null,
            'avatar_url' => $firebaseUserData['photoURL'] ?? null,
        ]);
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'uid';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->uid;
    }

    /**
     * Get the password for the user (not used with Firebase).
     */
    public function getAuthPassword(): ?string
    {
        return null;
    }

    /**
     * Get the token value for the "remember me" session (not used with Firebase).
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * Set the token value for the "remember me" session (not used with Firebase).
     */
    public function setRememberToken($value): void
    {
        // Not used with Firebase authentication
    }

    /**
     * Get the column name for the "remember me" token (not used with Firebase).
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
