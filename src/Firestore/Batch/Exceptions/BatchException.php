<?php

namespace JTD\FirebaseModels\Firestore\Batch\Exceptions;

/**
 * Exception for batch operation errors.
 */
class BatchException extends \Exception
{
    protected array $context = [];
    protected int $operationCount = 0;
    protected ?string $batchType = null;

    /**
     * Create a new batch exception.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = [],
        int $operationCount = 0,
        ?string $batchType = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->operationCount = $operationCount;
        $this->batchType = $batchType;
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
     * Get the number of operations that were attempted.
     */
    public function getOperationCount(): int
    {
        return $this->operationCount;
    }

    /**
     * Set the operation count.
     */
    public function setOperationCount(int $count): static
    {
        $this->operationCount = $count;
        return $this;
    }

    /**
     * Get the batch type.
     */
    public function getBatchType(): ?string
    {
        return $this->batchType;
    }

    /**
     * Set the batch type.
     */
    public function setBatchType(?string $type): static
    {
        $this->batchType = $type;
        return $this;
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
            'operation_count' => $this->operationCount,
            'batch_type' => $this->batchType,
            'previous' => $this->getPrevious() ? $this->getPrevious()->getMessage() : null,
        ];
    }
}
