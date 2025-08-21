<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

/**
 * Trait for handling version tracking in Firestore models.
 * This is useful for conflict resolution in sync mode.
 */
trait HasVersioning
{
    /**
     * The version field name.
     */
    protected string $versionField = '_version';

    /**
     * Whether to auto-increment version on save.
     */
    protected bool $autoIncrementVersion = true;

    /**
     * Boot the versioning trait.
     */
    public static function bootHasVersioning(): void
    {
        // Increment version before saving
        static::saving(function ($model) {
            if ($model->shouldIncrementVersion()) {
                $model->incrementVersion();
            }
        });

        // Set initial version on creating
        static::creating(function ($model) {
            if ($model->getVersion() === null) {
                $model->setVersion(1);
            }
        });
    }

    /**
     * Get the current version.
     */
    public function getVersion(): ?int
    {
        $version = $this->getAttribute($this->versionField);
        return $version !== null ? (int) $version : null;
    }

    /**
     * Set the version.
     */
    public function setVersion(int $version): static
    {
        $this->setAttribute($this->versionField, $version);
        return $this;
    }

    /**
     * Increment the version.
     */
    public function incrementVersion(): static
    {
        $currentVersion = $this->getVersion() ?? 0;
        $this->setVersion($currentVersion + 1);
        return $this;
    }

    /**
     * Check if the version should be incremented.
     */
    protected function shouldIncrementVersion(): bool
    {
        if (!$this->autoIncrementVersion) {
            return false;
        }

        // Don't increment if this is a new model being created
        if (!$this->exists) {
            return false;
        }

        // Don't increment if version field was manually set
        if ($this->isDirty($this->versionField)) {
            return false;
        }

        // Increment if any other attributes have changed
        return $this->isDirty();
    }

    /**
     * Get the version field name.
     */
    public function getVersionField(): string
    {
        return $this->versionField;
    }

    /**
     * Set the version field name.
     */
    public function setVersionField(string $field): static
    {
        $this->versionField = $field;
        return $this;
    }

    /**
     * Enable or disable auto-increment versioning.
     */
    public function setAutoIncrementVersion(bool $autoIncrement): static
    {
        $this->autoIncrementVersion = $autoIncrement;
        return $this;
    }

    /**
     * Check if auto-increment versioning is enabled.
     */
    public function isAutoIncrementVersionEnabled(): bool
    {
        return $this->autoIncrementVersion;
    }

    /**
     * Compare versions with another model or version number.
     */
    public function compareVersion($other): int
    {
        $thisVersion = $this->getVersion() ?? 0;
        
        if ($other instanceof static) {
            $otherVersion = $other->getVersion() ?? 0;
        } elseif (is_numeric($other)) {
            $otherVersion = (int) $other;
        } else {
            throw new \InvalidArgumentException('Version comparison requires a model instance or numeric value');
        }

        return $thisVersion <=> $otherVersion;
    }

    /**
     * Check if this model has a newer version than another.
     */
    public function isNewerThan($other): bool
    {
        return $this->compareVersion($other) > 0;
    }

    /**
     * Check if this model has an older version than another.
     */
    public function isOlderThan($other): bool
    {
        return $this->compareVersion($other) < 0;
    }

    /**
     * Check if this model has the same version as another.
     */
    public function hasSameVersionAs($other): bool
    {
        return $this->compareVersion($other) === 0;
    }

    /**
     * Get version information for debugging.
     */
    public function getVersionInfo(): array
    {
        return [
            'version' => $this->getVersion(),
            'version_field' => $this->versionField,
            'auto_increment' => $this->autoIncrementVersion,
            'is_dirty' => $this->isDirty(),
            'version_is_dirty' => $this->isDirty($this->versionField),
        ];
    }

    /**
     * Force set version without triggering auto-increment logic.
     */
    public function forceSetVersion(int $version): static
    {
        $this->attributes[$this->versionField] = $version;
        return $this;
    }

    /**
     * Reset version to 1.
     */
    public function resetVersion(): static
    {
        $this->setVersion(1);
        return $this;
    }
}
