<?php

namespace AngelitoSystems\FilamentTenancy\Models\Core;

use AngelitoSystems\FilamentTenancy\Concerns\UsesLandlordConnection;
use AngelitoSystems\FilamentTenancy\Support\DomainResolver;
use AngelitoSystems\FilamentTenancy\Support\TenantUrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Tenant Core Class
 * 
 * Contains all business logic for the Tenant model.
 * This class is internal to the package and should not be used directly.
 * Use the Tenant model instead.
 */
abstract class TenantCore extends Model
{
    use SoftDeletes, UsesLandlordConnection;

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($tenant) {
            // Auto-generate slug if not provided
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /**
     * Get database configuration for this tenant.
     */
    public function getDatabaseConfig(): array
    {
        $connectionManager = app(\AngelitoSystems\FilamentTenancy\Support\Contracts\ConnectionManagerInterface::class);
        return $connectionManager->getTenantDatabaseConfig($this);
    }

    /**
     * Get the connection name for this tenant.
     * 
     * Tenant model uses landlord connection (via UsesLandlordConnection trait).
     * This method ensures the trait's behavior is preserved.
     */
    public function getConnectionName(): ?string
    {
        // Tenant model should use landlord connection for queries
        // This allows UsesLandlordConnection trait to work properly
        return config('filament-tenancy.database.default_connection', 'mysql');
    }
    
    /**
     * Get the tenant-specific connection name (for switching connections).
     */
    public function getTenantConnectionName(): string
    {
        $connectionManager = app(\AngelitoSystems\FilamentTenancy\Support\Contracts\ConnectionManagerInterface::class);
        return $connectionManager->getTenantConnectionName($this);
    }

    /**
     * Check if tenant is active.
     */
    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if tenant is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the domain resolver instance.
     */
    protected function getDomainResolver(): DomainResolver
    {
        return app(DomainResolver::class);
    }

    /**
     * Get the URL generator instance.
     */
    protected function getUrlGenerator(): TenantUrlGenerator
    {
        return app(TenantUrlGenerator::class);
    }

    /**
     * Get the full domain for this tenant.
     */
    public function getFullDomain(): string
    {
        return $this->getDomainResolver()->buildFullDomain(
            $this->domain,
            $this->subdomain
        );
    }

    /**
     * Get the URL for this tenant.
     */
    public function getUrl(string $path = ''): string
    {
        return $this->getUrlGenerator()->generateUrl(
            $this->domain,
            $this->subdomain,
            $path
        );
    }

    /**
     * Scope to active tenants only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Scope to expired tenants only.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to tenants by domain.
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * Scope to tenants by subdomain.
     */
    public function scopeBySubdomain($query, string $subdomain)
    {
        return $query->where('subdomain', $subdomain);
    }

    /**
     * Scope to tenants by slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Get data attribute value.
     */
    public function getData(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Set data attribute value.
     */
    public function setData(string $key, $value): void
    {
        $data = $this->data ?? [];
        data_set($data, $key, $value);
        $this->data = $data;
    }

    /**
     * Remove data attribute value.
     */
    public function removeData(string $key): void
    {
        $data = $this->data ?? [];
        data_forget($data, $key);
        $this->data = $data;
    }

    /**
     * Check if tenant has specific data key.
     */
    public function hasData(string $key): bool
    {
        return data_get($this->data, $key) !== null;
    }

    /**
     * Get the plan for this tenant (if using plan_id).
     */
    public function planModel(): BelongsTo
    {
        return $this->belongsTo(\AngelitoSystems\FilamentTenancy\Models\Plan::class, 'plan_id');
    }

    /**
     * Get all subscriptions for this tenant.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(\AngelitoSystems\FilamentTenancy\Models\Subscription::class);
    }

    /**
     * Get the active subscription for this tenant.
     * 
     * Note: This method uses the subscriptions() relationship defined in the child class.
     */
    public function activeSubscription(): ?\AngelitoSystems\FilamentTenancy\Models\Subscription
    {
        return $this->subscriptions()
            ->where('status', \AngelitoSystems\FilamentTenancy\Models\Subscription::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();
    }

    /**
     * Get the current plan (from plan_id or plan string).
     * 
     * Note: This method uses the planModel() relationship defined in the child class.
     */
    public function getCurrentPlan()
    {
        if ($this->plan_id) {
            return $this->planModel;
        }

        // Fallback to string plan name for backward compatibility
        return $this->plan;
    }
}

