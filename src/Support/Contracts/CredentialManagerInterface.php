<?php

namespace AngelitoSystems\FilamentTenancy\Support\Contracts;

interface CredentialManagerInterface
{
    /**
     * Get database credentials for a specific profile.
     */
    public function getCredentials(string $profile = 'default'): array;

    /**
     * Store encrypted credentials for a profile.
     */
    public function storeCredentials(string $profile, array $credentials): void;

    /**
     * Remove credentials for a profile.
     */
    public function removeCredentials(string $profile): void;

    /**
     * Check if credentials exist for a profile.
     */
    public function hasCredentials(string $profile): bool;

    /**
     * Get available credential profiles.
     */
    public function getAvailableProfiles(): array;

    /**
     * Validate credentials by testing connection.
     */
    public function validateCredentials(string $profile): bool;

    /**
     * Rotate encryption keys for all stored credentials.
     */
    public function rotateEncryptionKeys(): void;

    /**
     * Generate secure database name for tenant.
     */
    public function generateSecureDatabaseName(string $tenantSlug, int $tenantId): string;

    /**
     * Get connection pool configuration.
     */
    public function getConnectionPoolConfig(): array;

    /**
     * Clear all credentials from memory.
     */
    public function clearCredentials(): void;

    /**
     * Get masked credentials for display purposes.
     */
    public function getMaskedCredentials(string $profile): array;
}