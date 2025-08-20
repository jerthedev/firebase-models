<?php

namespace JTD\FirebaseModels\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Foundation\Auth\Access\Authorizable as AuthorizableTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Notifications\Notifiable;
use JTD\FirebaseModels\Firestore\FirestoreModel;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\JWT\IdToken;

/**
 * Abstract base class for Firebase-authenticated user models.
 * 
 * This class provides integration between Firebase Authentication and Laravel's
 * Auth system, allowing Firebase users to be used seamlessly with Laravel's
 * authentication guards and middleware.
 */
abstract class FirebaseAuthenticatable extends FirestoreModel implements Authenticatable, Authorizable, CanResetPassword
{
    use AuthorizableTrait, CanResetPasswordTrait, Notifiable;

    /**
     * The Firebase user ID (UID).
     */
    protected string $primaryKey = 'uid';

    /**
     * The Firebase ID token for this user.
     */
    protected $firebaseToken = null;

    /**
     * The Firebase user record.
     */
    protected ?UserRecord $firebaseUserRecord = null;

    /**
     * Attributes that should be cast to native types.
     */
    protected array $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_sign_in_at' => 'datetime',
        'custom_claims' => 'array',
        'provider_data' => 'array',
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'name',
        'email',
        'email_verified_at',
        'photo_url',
        'phone_number',
        'custom_claims',
        'provider_data',
        'last_sign_in_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        'firebase_token',
        'remember_token',
    ];

    /**
     * Create a new Firebase authenticatable user instance.
     */
    public function __construct(array $attributes = [])
    {
        // Set the collection name for user storage
        if (!$this->collection) {
            $this->collection = 'users';
        }

        parent::__construct($attributes);
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get the password for the user (not applicable for Firebase Auth).
     */
    public function getAuthPassword(): string
    {
        throw new \BadMethodCallException('Firebase Auth does not use passwords. Use Firebase ID tokens instead.');
    }

    /**
     * Get the column name for the "password" (not applicable for Firebase Auth).
     */
    public function getAuthPasswordName(): string
    {
        throw new \BadMethodCallException('Firebase Auth does not use passwords. Use Firebase ID tokens instead.');
    }

    /**
     * Get the token value for the "remember me" session (not applicable for Firebase Auth).
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * Set the token value for the "remember me" session (not applicable for Firebase Auth).
     */
    public function setRememberToken($value): void
    {
        // Firebase Auth handles token management
    }

    /**
     * Get the column name for the "remember me" token (not applicable for Firebase Auth).
     */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    /**
     * Set the Firebase ID token for this user.
     */
    public function setFirebaseToken($token): static
    {
        $this->firebaseToken = $token;
        return $this;
    }

    /**
     * Get the Firebase ID token for this user.
     */
    public function getFirebaseToken()
    {
        return $this->firebaseToken;
    }

    /**
     * Set the Firebase user record for this user.
     */
    public function setFirebaseUserRecord(UserRecord $userRecord): static
    {
        $this->firebaseUserRecord = $userRecord;
        $this->hydrateFromFirebaseUserRecord($userRecord);
        return $this;
    }

    /**
     * Get the Firebase user record for this user.
     */
    public function getFirebaseUserRecord(): ?UserRecord
    {
        return $this->firebaseUserRecord;
    }

    /**
     * Hydrate the model from a Firebase UserRecord.
     */
    protected function hydrateFromFirebaseUserRecord(UserRecord $userRecord): void
    {
        $this->fill([
            'uid' => $userRecord->uid,
            'email' => $userRecord->email,
            'email_verified_at' => $userRecord->emailVerified ? now() : null,
            'name' => $userRecord->displayName,
            'photo_url' => $userRecord->photoUrl,
            'phone_number' => $userRecord->phoneNumber,
            'custom_claims' => $userRecord->customClaims,
            'provider_data' => $this->formatProviderData($userRecord->providerData),
            'last_sign_in_at' => $userRecord->metadata->lastSignInTime,
            'created_at' => $userRecord->metadata->creationTime,
        ]);

        // Mark as existing since it comes from Firebase
        $this->exists = true;
    }

    /**
     * Hydrate the model from a Firebase ID token.
     */
    public function hydrateFromFirebaseToken($token): static
    {
        $this->firebaseToken = $token;
        
        $claims = $token->claims();
        
        $this->fill([
            'uid' => $claims->get('sub'),
            'email' => $claims->get('email'),
            'email_verified_at' => $claims->get('email_verified', false) ? now() : null,
            'name' => $claims->get('name'),
            'photo_url' => $claims->get('picture'),
            'phone_number' => $claims->get('phone_number'),
        ]);

        // Add custom claims if present
        $customClaims = [];
        foreach ($claims->all() as $key => $value) {
            if (!in_array($key, ['iss', 'aud', 'auth_time', 'user_id', 'sub', 'iat', 'exp', 'email', 'email_verified', 'name', 'picture', 'phone_number'])) {
                $customClaims[$key] = $value;
            }
        }
        
        if (!empty($customClaims)) {
            $this->setAttribute('custom_claims', $customClaims);
        }

        return $this;
    }

    /**
     * Format provider data for storage.
     */
    protected function formatProviderData(array $providerData): array
    {
        return array_map(function ($provider) {
            return [
                'provider_id' => $provider->providerId ?? null,
                'uid' => $provider->uid ?? null,
                'display_name' => $provider->displayName ?? null,
                'email' => $provider->email ?? null,
                'phone_number' => $provider->phoneNumber ?? null,
                'photo_url' => $provider->photoUrl ?? null,
            ];
        }, $providerData);
    }

    /**
     * Get a custom claim value.
     */
    public function getCustomClaim(string $key, mixed $default = null): mixed
    {
        $claims = $this->getAttribute('custom_claims') ?? [];
        return $claims[$key] ?? $default;
    }

    /**
     * Check if the user has a specific custom claim.
     */
    public function hasCustomClaim(string $key): bool
    {
        $claims = $this->getAttribute('custom_claims') ?? [];
        return array_key_exists($key, $claims);
    }

    /**
     * Get the user's roles from custom claims.
     */
    public function getRoles(): array
    {
        return $this->getCustomClaim('roles', []);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * Get the user's permissions from custom claims.
     */
    public function getPermissions(): array
    {
        return $this->getCustomClaim('permissions', []);
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }

    /**
     * Determine if the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark the given user's email as verified.
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    /**
     * Determine if the user is verified (email verified).
     *
     * @deprecated Use hasVerifiedEmail() instead for Laravel compatibility
     */
    public function isVerified(): bool
    {
        return $this->hasVerifiedEmail();
    }

    /**
     * Get the user's display name or email as fallback.
     */
    public function getDisplayNameAttribute(): ?string
    {
        return $this->attributes['name'] ?? $this->attributes['email'] ?? null;
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // Remove sensitive Firebase-specific data
        unset($array['firebase_token']);
        
        return $array;
    }
}
