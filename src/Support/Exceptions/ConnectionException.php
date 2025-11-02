<?php

namespace AngelitoSystems\FilamentTenancy\Support\Exceptions;

use Exception;

class ConnectionException extends Exception
{
    protected $tenantId;
    protected $connectionName;

    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null, ?int $tenantId = null, ?string $connectionName = null)
    {
        parent::__construct($message, $code, $previous);
        
        $this->tenantId = $tenantId;
        $this->connectionName = $connectionName;
    }

    /**
     * Get the tenant ID associated with this exception.
     */
    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Get the connection name associated with this exception.
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /**
     * Set the tenant ID for this exception.
     */
    public function setTenantId(int $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Set the connection name for this exception.
     */
    public function setConnectionName(string $connectionName): self
    {
        $this->connectionName = $connectionName;
        return $this;
    }

    /**
     * Get context information for logging.
     */
    public function getContext(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'connection_name' => $this->connectionName,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}