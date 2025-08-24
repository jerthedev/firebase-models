<?php

namespace JTD\FirebaseModels\Auth;

/**
 * Default User model implementation using Firebase Authentication.
 *
 * This is a concrete implementation of FirebaseAuthenticatable that can be used
 * directly or extended for custom user models. It provides sensible defaults
 * for most Firebase Auth use cases.
 */
class User extends FirebaseAuthenticatable
{
    /**
     * The collection associated with the model.
     */
    protected ?string $collection = 'users';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'uid', // Firebase UID
        'name',
        'email',
        'password', // For compatibility, though not used with Firebase
        'email_verified_at',
        'photo_url',
        'phone_number',
        'custom_claims',
        'provider_data',
        'last_sign_in_at',
        // Additional user profile fields
        'first_name',
        'last_name',
        'timezone',
        'locale',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        'password',
        'remember_token',
        'firebase_token',
        'custom_claims', // Hide sensitive claims by default
    ];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // For Laravel compatibility
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_sign_in_at' => 'datetime',
        'custom_claims' => 'array',
        'provider_data' => 'array',
        'preferences' => 'array',
    ];

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): ?string
    {
        $firstName = $this->getAttribute('first_name');
        $lastName = $this->getAttribute('last_name');

        if ($firstName && $lastName) {
            return trim($firstName.' '.$lastName);
        }

        return $this->getAttribute('name');
    }

    /**
     * Get the user's initials.
     */
    public function getInitialsAttribute(): string
    {
        $name = $this->full_name ?? $this->email ?? '';
        $words = explode(' ', $name);

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    /**
     * Get the user's avatar URL with fallback.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getAttribute('photo_url') ?? $this->generateGravatarUrl();
    }

    /**
     * Generate a Gravatar URL for the user's email.
     */
    protected function generateGravatarUrl(): ?string
    {
        $email = $this->getAttribute('email');

        if (!$email) {
            return null;
        }

        $hash = md5(strtolower(trim($email)));

        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }

    /**
     * Get a user preference value.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        $preferences = $this->getAttribute('preferences') ?? [];

        return $preferences[$key] ?? $default;
    }

    /**
     * Set a user preference value.
     */
    public function setPreference(string $key, mixed $value): static
    {
        $preferences = $this->getAttribute('preferences') ?? [];
        $preferences[$key] = $value;
        $this->setAttribute('preferences', $preferences);

        return $this;
    }

    /**
     * Check if the user is an admin (has admin role).
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin') || $this->hasCustomClaim('admin', false);
    }

    /**
     * Check if the user is a moderator.
     */
    public function isModerator(): bool
    {
        return $this->hasRole('moderator') || $this->hasCustomClaim('moderator', false);
    }

    /**
     * Get the user's timezone or default.
     */
    public function getTimezone(): string
    {
        return $this->getAttribute('timezone') ?? config('app.timezone', 'UTC');
    }

    /**
     * Get the user's locale or default.
     */
    public function getLocale(): string
    {
        return $this->getAttribute('locale') ?? config('app.locale', 'en');
    }

    /**
     * Scope a query to only include verified users.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /**
     * Scope a query to only include unverified users.
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('email_verified_at');
    }

    /**
     * Scope a query to only include users with a specific role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('custom_claims.roles', 'array-contains', $role);
    }

    /**
     * Convert the model instance to an array for API responses.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Add computed attributes
        $array['full_name'] = $this->full_name;
        $array['initials'] = $this->initials;
        $array['avatar_url'] = $this->avatar_url;
        $array['is_admin'] = $this->isAdmin();
        $array['has_verified_email'] = $this->hasVerifiedEmail();

        return $array;
    }
}
