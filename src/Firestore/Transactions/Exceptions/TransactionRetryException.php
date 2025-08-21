<?php

namespace JTD\FirebaseModels\Firestore\Transactions\Exceptions;

/**
 * Exception thrown when a transaction fails after all retry attempts.
 */
class TransactionRetryException extends TransactionException
{
    protected int $attempts = 0;
    protected array $attemptErrors = [];

    /**
     * Create a new transaction retry exception.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        int $attempts = 0,
        array $attemptErrors = [],
        array $context = []
    ) {
        parent::__construct($message, $code, $previous, $context);
        $this->attempts = $attempts;
        $this->attemptErrors = $attemptErrors;
    }

    /**
     * Get the number of attempts made.
     */
    public function getAttempts(): int
    {
        return $this->attempts;
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
     * Get errors from all attempts.
     */
    public function getAttemptErrors(): array
    {
        return $this->attemptErrors;
    }

    /**
     * Set attempt errors.
     */
    public function setAttemptErrors(array $errors): static
    {
        $this->attemptErrors = $errors;
        return $this;
    }

    /**
     * Add an attempt error.
     */
    public function addAttemptError(int $attempt, string $error): static
    {
        $this->attemptErrors[$attempt] = $error;
        return $this;
    }

    /**
     * Get the last attempt error.
     */
    public function getLastAttemptError(): ?string
    {
        if (empty($this->attemptErrors)) {
            return null;
        }

        $lastAttempt = max(array_keys($this->attemptErrors));
        return $this->attemptErrors[$lastAttempt];
    }

    /**
     * Convert to array for logging.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'attempts' => $this->attempts,
            'attempt_errors' => $this->attemptErrors,
            'last_attempt_error' => $this->getLastAttemptError(),
        ]);
    }
}
