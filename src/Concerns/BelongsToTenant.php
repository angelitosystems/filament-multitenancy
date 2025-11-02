<?php

namespace AngelitoSystems\FilamentTenancy\Concerns;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant()
    {
        // Automatically set tenant_id when creating
        static::creating(function ($model) {
            if (! $model->tenant_id && Tenancy::current()) {
                $model->tenant_id = Tenancy::current()->id;
            }
        });

        // Global scope to filter by current tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Tenancy::current()) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', Tenancy::current()->id);
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant(Builder $query, Tenant $tenant): Builder
    {
        return $query->where('tenant_id', $tenant->id);
    }

    /**
     * Scope to filter by tenant ID.
     */
    public function scopeForTenantId(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope without tenant filtering.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Get the tenant ID column name.
     */
    public function getTenantIdColumn(): string
    {
        return 'tenant_id';
    }

    /**
     * Check if the model belongs to the current tenant.
     */
    public function belongsToCurrentTenant(): bool
    {
        if (! Tenancy::current()) {
            return false;
        }

        return $this->tenant_id === Tenancy::current()->id;
    }

    /**
     * Check if the model belongs to a specific tenant.
     */
    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenant_id === $tenant->id;
    }

    /**
     * Set the tenant for this model.
     */
    public function setTenant(Tenant $tenant): self
    {
        $this->tenant_id = $tenant->id;
        return $this;
    }

    /**
     * Clear the tenant for this model.
     */
    public function clearTenant(): self
    {
        $this->tenant_id = null;
        return $this;
    }
}