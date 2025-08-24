<?php

namespace JTD\FirebaseModels\Sync\Conflicts;

use JTD\FirebaseModels\Contracts\Sync\ConflictResolutionInterface;

/**
 * Implementation of conflict resolution results.
 */
class ConflictResolution implements ConflictResolutionInterface
{
    protected array $resolvedData;

    protected string $action;

    protected string $winningSource;

    protected string $description;

    protected bool $requiresManualIntervention;

    protected array $metadata;

    /**
     * Create a new conflict resolution.
     */
    public function __construct(
        array $resolvedData,
        string $action,
        string $winningSource,
        string $description,
        bool $requiresManualIntervention = false,
        array $metadata = []
    ) {
        $this->resolvedData = $resolvedData;
        $this->action = $action;
        $this->winningSource = $winningSource;
        $this->description = $description;
        $this->requiresManualIntervention = $requiresManualIntervention;
        $this->metadata = $metadata;
    }

    /**
     * Get the resolved data.
     */
    public function getResolvedData(): array
    {
        return $this->resolvedData;
    }

    /**
     * Get the resolution action taken.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Get the source of the winning data.
     */
    public function getWinningSource(): string
    {
        return $this->winningSource;
    }

    /**
     * Check if the resolution requires manual intervention.
     */
    public function requiresManualIntervention(): bool
    {
        return $this->requiresManualIntervention;
    }

    /**
     * Get any additional metadata about the resolution.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a human-readable description of the resolution.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set additional metadata.
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
    }

    /**
     * Add a single metadata item.
     */
    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * Mark as requiring manual intervention.
     */
    public function requireManualIntervention(string $reason = ''): void
    {
        $this->requiresManualIntervention = true;
        if ($reason) {
            $this->addMetadata('manual_intervention_reason', $reason);
        }
    }

    /**
     * Get a summary of the resolution.
     */
    public function getSummary(): array
    {
        return [
            'action' => $this->action,
            'winning_source' => $this->winningSource,
            'description' => $this->description,
            'requires_manual_intervention' => $this->requiresManualIntervention,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'resolved_data' => $this->resolvedData,
            'action' => $this->action,
            'winning_source' => $this->winningSource,
            'description' => $this->description,
            'requires_manual_intervention' => $this->requiresManualIntervention,
            'metadata' => $this->metadata,
        ];
    }
}
