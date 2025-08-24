<?php

namespace JTD\FirebaseModels\Firestore\Batch;

/**
 * Result object for batch operations.
 */
class BatchResult
{
    protected bool $success = false;

    protected mixed $data = null;

    protected ?string $error = null;

    protected ?\Exception $exception = null;

    protected float $duration = 0.0;

    protected array $metadata = [];

    /**
     * Set the success status.
     */
    public function setSuccess(bool $success): static
    {
        $this->success = $success;

        return $this;
    }

    /**
     * Check if the batch operation was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Set the batch data.
     */
    public function setData(mixed $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get the batch data.
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Set the error message.
     */
    public function setError(?string $error): static
    {
        $this->error = $error;

        return $this;
    }

    /**
     * Get the error message.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Set the exception.
     */
    public function setException(?\Exception $exception): static
    {
        $this->exception = $exception;

        return $this;
    }

    /**
     * Get the exception.
     */
    public function getException(): ?\Exception
    {
        return $this->exception;
    }

    /**
     * Set the batch duration.
     */
    public function setDuration(float $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Get the batch duration in seconds.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Get the batch duration in milliseconds.
     */
    public function getDurationMs(): float
    {
        return round($this->duration * 1000, 2);
    }

    /**
     * Set metadata.
     */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Add metadata.
     */
    public function addMetadata(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Get metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get specific metadata value.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if the batch operation failed.
     */
    public function isFailed(): bool
    {
        return !$this->success;
    }

    /**
     * Get the number of operations processed.
     */
    public function getOperationCount(): int
    {
        if (is_array($this->data)) {
            return $this->data['operation_count'] ?? 0;
        }

        return 0;
    }

    /**
     * Get the batch type (single or chunked).
     */
    public function getBatchType(): ?string
    {
        if (is_array($this->data)) {
            return $this->data['batch_type'] ?? null;
        }

        return null;
    }

    /**
     * Get the number of batches executed.
     */
    public function getBatchCount(): int
    {
        if (is_array($this->data)) {
            return $this->data['batch_count'] ?? 1;
        }

        return 1;
    }

    /**
     * Get a summary of the batch result.
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->success,
            'duration_ms' => $this->getDurationMs(),
            'operation_count' => $this->getOperationCount(),
            'batch_type' => $this->getBatchType(),
            'batch_count' => $this->getBatchCount(),
            'error' => $this->error,
            'has_data' => $this->data !== null,
            'metadata_count' => count($this->metadata),
        ];
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'duration' => $this->duration,
            'duration_ms' => $this->getDurationMs(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get the data or throw exception if failed.
     */
    public function getDataOrFail(): mixed
    {
        if (!$this->success) {
            throw $this->exception ?? new \Exception($this->error ?? 'Batch operation failed');
        }

        return $this->data;
    }

    /**
     * Execute a callback if successful.
     */
    public function onSuccess(callable $callback): static
    {
        if ($this->success) {
            $callback($this->data, $this);
        }

        return $this;
    }

    /**
     * Execute a callback if failed.
     */
    public function onFailure(callable $callback): static
    {
        if (!$this->success) {
            $callback($this->error, $this->exception, $this);
        }

        return $this;
    }

    /**
     * Create a successful result.
     */
    public static function success(mixed $data = null, float $duration = 0.0): static
    {
        return (new static())
            ->setSuccess(true)
            ->setData($data)
            ->setDuration($duration);
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $error, ?\Exception $exception = null, float $duration = 0.0): static
    {
        return (new static())
            ->setSuccess(false)
            ->setError($error)
            ->setException($exception)
            ->setDuration($duration);
    }

    /**
     * Get performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        $operationCount = $this->getOperationCount();
        $duration = $this->getDuration();

        return [
            'total_operations' => $operationCount,
            'duration_seconds' => $duration,
            'duration_ms' => $this->getDurationMs(),
            'operations_per_second' => $duration > 0 ? round($operationCount / $duration, 2) : 0,
            'average_operation_time_ms' => $operationCount > 0 ? round($this->getDurationMs() / $operationCount, 2) : 0,
            'batch_type' => $this->getBatchType(),
            'batch_count' => $this->getBatchCount(),
        ];
    }
}
