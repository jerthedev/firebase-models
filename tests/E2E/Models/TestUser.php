<?php

namespace JTD\FirebaseModels\Tests\E2E\Models;

use JTD\FirebaseModels\Firestore\FirestoreModel;

/**
 * Test User model for E2E testing.
 */
class TestUser extends FirestoreModel
{
    protected ?string $collection = null; // Will be set dynamically in tests

    protected array $fillable = [
        'name',
        'email',
        'active',
        'role',
        'permissions',
        'profile_data',
        'settings',
        'last_login',
        'email_verified_at',
    ];

    protected array $casts = [
        'active' => 'boolean',
        'permissions' => 'array',
        'profile_data' => 'array',
        'settings' => 'array',
        'last_login' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    protected array $hidden = [
        'password',
        'remember_token',
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
     * Accessor for formatted name.
     */
    public function getFormattedNameAttribute(): string
    {
        return ucwords($this->name ?? 'Unknown User');
    }

    /**
     * Mutator for email (always lowercase).
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower($value);
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
        $this->permissions = array_values(array_filter($permissions, fn ($p) => $p !== $permission));

        return $this;
    }
}
