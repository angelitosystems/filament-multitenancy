<?php

namespace AngelitoSystems\FilamentTenancy\Support;

use AngelitoSystems\FilamentTenancy\Events\TenantCreated;
use AngelitoSystems\FilamentTenancy\Events\TenantDeleted;
use AngelitoSystems\FilamentTenancy\Events\TenantSwitched;
use AngelitoSystems\FilamentTenancy\Models\Tenant;
use Illuminate\Http\Request;

class TenantManager
{
    protected TenantResolver $tenantResolver;
    protected DatabaseManager $databaseManager;
    protected ?Tenant $currentTenant = null;

    public function __construct(TenantResolver $tenantResolver, DatabaseManager $databaseManager)
    {
        $this->tenantResolver = $tenantResolver;
        $this->databaseManager = $databaseManager;
    }

    /**
     * Initialize tenancy for the current request.
     */
    public function initialize(Request $request): void
    {
        $tenant = $this->tenantResolver->resolve($request);

        if ($tenant) {
            $this->switchToTenant($tenant);
        }
    }

    /**
     * Switch to a specific tenant.
     */
    public function switchToTenant(Tenant $tenant): void
    {
        $previousTenant = $this->currentTenant;

        // Set current tenant
        $this->currentTenant = $tenant;
        $this->tenantResolver->setCurrent($tenant);

        // Switch database connection
        $this->databaseManager->switchToTenant($tenant);

        // Fire event
        event(new TenantSwitched($tenant, $previousTenant));
    }

    /**
     * Switch back to central/landlord context.
     */
    public function switchToCentral(): void
    {
        $previousTenant = $this->currentTenant;

        // Clear current tenant
        $this->currentTenant = null;
        $this->tenantResolver->setCurrent(null);

        // Switch to central database
        $this->databaseManager->switchToCentral();

        // Fire event
        event(new TenantSwitched(null, $previousTenant));
    }

    /**
     * Get the current tenant.
     */
    public function current(): ?Tenant
    {
        return $this->currentTenant ?? $this->tenantResolver->current();
    }

    /**
     * Check if we're currently in a tenant context.
     */
    public function isTenant(): bool
    {
        return $this->current() !== null;
    }

    /**
     * Check if we're currently in the central/landlord context.
     */
    public function isCentral(): bool
    {
        return ! $this->isTenant();
    }

    /**
     * Create a new tenant.
     */
    public function createTenant(array $attributes): Tenant
    {
        // Ensure we're in central context
        $this->switchToCentral();

        // Create tenant record
        $tenant = Tenant::create($attributes);

        // Create tenant database if configured
        if (config('filament-tenancy.database.auto_create_tenant_database', true)) {
            $this->databaseManager->createTenantDatabase($tenant);
        }

        // Run tenant migrations if configured
        if (config('filament-tenancy.migrations.auto_run', true)) {
            $this->databaseManager->runTenantMigrations($tenant);
        }

        // Fire event
        event(new TenantCreated($tenant));

        return $tenant;
    }

    /**
     * Delete a tenant.
     */
    public function deleteTenant(Tenant $tenant): bool
    {
        // Ensure we're in central context
        $this->switchToCentral();

        // Delete tenant database if configured
        if (config('filament-tenancy.database.auto_delete_tenant_database', false)) {
            $this->databaseManager->deleteTenantDatabase($tenant);
        }

        // Delete tenant record
        $deleted = $tenant->delete();

        if ($deleted) {
            // Clear cache
            $this->tenantResolver->clearCache();

            // Fire event
            event(new TenantDeleted($tenant));
        }

        return $deleted;
    }

    /**
     * Execute a callback in tenant context.
     */
    public function runForTenant(Tenant $tenant, callable $callback)
    {
        $previousTenant = $this->current();

        try {
            $this->switchToTenant($tenant);
            return $callback($tenant);
        } finally {
            if ($previousTenant) {
                $this->switchToTenant($previousTenant);
            } else {
                $this->switchToCentral();
            }
        }
    }

    /**
     * Execute a callback in central context.
     */
    public function runForCentral(callable $callback)
    {
        $previousTenant = $this->current();

        try {
            $this->switchToCentral();
            return $callback();
        } finally {
            if ($previousTenant) {
                $this->switchToTenant($previousTenant);
            }
        }
    }

    /**
     * Get all tenants.
     */
    public function getAllTenants(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->runForCentral(function () {
            return Tenant::all();
        });
    }

    /**
     * Find tenant by ID.
     */
    public function findTenant(int $id): ?Tenant
    {
        return $this->runForCentral(function () use ($id) {
            return Tenant::find($id);
        });
    }

    /**
     * Find tenant by slug.
     */
    public function findTenantBySlug(string $slug): ?Tenant
    {
        return $this->runForCentral(function () use ($slug) {
            return Tenant::where('slug', $slug)->first();
        });
    }

    /**
     * Find tenant by domain.
     */
    public function findTenantByDomain(string $domain): ?Tenant
    {
        return $this->runForCentral(function () use ($domain) {
            return Tenant::where('domain', $domain)->first();
        });
    }

    /**
     * Get tenant resolver instance.
     */
    public function resolver(): TenantResolver
    {
        return $this->tenantResolver;
    }

    /**
     * Get database manager instance.
     */
    public function database(): DatabaseManager
    {
        return $this->databaseManager;
    }
}