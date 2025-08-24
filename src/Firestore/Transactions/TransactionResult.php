<?php

namespace JTD\FirebaseModels\Firestore\Transactions;

/**
 * Result object for transaction operations.
 */
class TransactionResult
{
    protected bool $success = false;

    protected mixed $data = null;

    protected ?string $error = null;

    protected ?\Exception $exception = null;

    protected float $duration = 0.0;

    protected int $attempts = 0;

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
     * Check if the transaction was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Set the transaction data.
     */
    public function setData(mixed $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get the transaction data.
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
     * Set the transaction duration.
     */
    public function setDuration(float $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    /**
     * Get the transaction duration in seconds.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Get the transaction duration in milliseconds.
     */
    public function getDurationMs(): float
    {
        return round($this->duration * 1000, 2);
    }

    /**
     * Set the number of attempts.
     */
    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;

        return $this;
    }

    /**
     * Get the number of attempts.
     */
    public function getAttempts(): int
    {
        return $this->attempts;
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
     * Check if the transaction failed.
     */
    public function isFailed(): bool
    {
        return !$this->success;
    }

    /**
     * Check if the transaction was retried.
     */
    public function wasRetried(): bool
    {
        return $this->attempts > 1;
    }

    /**
     * Get a summary of the transaction result.
     */
    public function getSummary(): array
    {
        return [
            'success' => $this->success,
            'duration_ms' => $this->getDurationMs(),
            'attempts' => $this->attempts,
            'retried' => $this->wasRetried(),
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
            'attempts' => $this->attempts,
            'retried' => $this->wasRetried(),
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
            throw $this->exception ?? new \Exception($this->error ?? 'Transaction failed');
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
    public static function success(mixed $data = null, float $duration = 0.0, int $attempts = 1): static
    {
        return (new static())
            ->setSuccess(true)
            ->setData($data)
            ->setDuration($duration)
            ->setAttempts($attempts);
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $error, ?\Exception $exception = null, float $duration = 0.0, int $attempts = 1): static
    {
        return (new static())
            ->setSuccess(false)
            ->setError($error)
            ->setException($exception)
            ->setDuration($duration)
            ->setAttempts($attempts);
    }
}
