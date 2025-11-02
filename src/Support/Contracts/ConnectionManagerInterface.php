<?php

namespace AngelitoSystems\FilamentTenancy\Support\Contracts;

use AngelitoSystems\FilamentTenancy\Models\Tenant;

interface ConnectionManagerInterface
{
    /**
     * Get secure database configuration for a tenant.
     */
    public function getTenantDatabaseConfig(Tenant $tenant): array;

    /**
     * Establish connection to tenant database.
     */
    public function connectToTenant(Tenant $tenant): string;

    /**
     * Switch to tenant database connection.
     */
    public function switchToTenant(Tenant $tenant): void;

    /**
     * Switch back to central database.
     */
    public function switchToCentral(): void;

    /**
     * Get tenant connection name.
     */
    public function getTenantConnectionName(Tenant $tenant): string;

    /**
     * Close tenant connection.
     */
    public function closeTenantConnection(Tenant $tenant): void;

    /**
     * Close all tenant connections.
     */
    public function closeAllTenantConnections(): void;

    /**
     * Get active connections count.
     */
    public function getActiveConnectionsCount(): int;

    /**
     * Get active connections info.
     */
    public function getActiveConnectionsInfo(): array;

    /**
     * Clear tenant cache.
     */
    public function clearTenantCache(Tenant $tenant): void;

    /**
     * Clear all tenant caches.
     */
    public function clearAllTenantCaches(): void;
}