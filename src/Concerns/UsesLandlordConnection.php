<?php

namespace AngelitoSystems\FilamentTenancy\Concerns;

/**
 * Trait for models that should always use the landlord/central database connection.
 * 
 * This trait ensures that models using it will always connect to the central database,
 * regardless of the current tenant context.
 */
trait UsesLandlordConnection
{
    /**
     * Get the database connection name for this model.
     */
    public function getConnectionName(): string
    {
        return config('filament-tenancy.database.default_connection', 'mysql');
    }
}