<?php

namespace JTD\FirebaseModels\Firestore\Concerns;

use Google\Cloud\Firestore\Timestamp;
use Illuminate\Support\Carbon;

/**
 * Trait for handling model timestamps (created_at, updated_at).
 */
trait HasTimestamps
{
    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = true;

    /**
     * Update the model's update timestamp.
     */
    public function touch(): bool
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Update the creation and update timestamps.
     */
    protected function updateTimestamps(): void
    {
        $time = $this->freshTimestamp();

        $updatedAtColumn = $this->getUpdatedAtColumn();

        if (!is_null($updatedAtColumn) && !$this->isDirty($updatedAtColumn)) {
            $this->setUpdatedAt($time);
        }

        $createdAtColumn = $this->getCreatedAtColumn();

        if (!$this->exists && !is_null($createdAtColumn) && !$this->isDirty($createdAtColumn)) {
            $this->setCreatedAt($time);
        }
    }

    /**
     * Set the value of the "created at" attribute.
     */
    public function setCreatedAt(mixed $value): static
    {
        $this->{$this->getCreatedAtColumn()} = $value;

        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     */
    public function setUpdatedAt(mixed $value): static
    {
        $this->{$this->getUpdatedAtColumn()} = $value;

        return $this;
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestamp(): Carbon
    {
        return Carbon::now();
    }

    /**
     * Get a fresh timestamp for the model as a Firestore Timestamp.
     */
    public function freshFirestoreTimestamp(): Timestamp
    {
        return new Timestamp(Carbon::now());
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestampString(): string
    {
        return $this->fromDateTime($this->freshTimestamp());
    }

    /**
     * Determine if the model uses timestamps.
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Get the name of the "created at" column.
     */
    public function getCreatedAtColumn(): ?string
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function getUpdatedAtColumn(): ?string
    {
        return static::UPDATED_AT;
    }

    /**
     * Get the fully qualified "created at" column.
     */
    public function getQualifiedCreatedAtColumn(): string
    {
        return $this->qualifyColumn($this->getCreatedAtColumn());
    }

    /**
     * Get the fully qualified "updated at" column.
     */
    public function getQualifiedUpdatedAtColumn(): string
    {
        return $this->qualifyColumn($this->getUpdatedAtColumn());
    }
}
