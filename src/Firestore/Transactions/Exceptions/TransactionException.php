<?php

namespace JTD\FirebaseModels\Firestore\Transactions\Exceptions;

/**
 * Base exception for transaction-related errors.
 */
class TransactionException extends \Exception
{
    protected array $context = [];

    /**
     * Create a new transaction exception.
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the exception context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set the exception context.
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context data.
     */
    public function addContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get context value.
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Convert to array for logging.
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->context,
            'previous' => $this->getPrevious() ? $this->getPrevious()->getMessage() : null,
        ];
    }
}
