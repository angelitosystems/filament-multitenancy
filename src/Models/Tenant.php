<?php

namespace AngelitoSystems\FilamentTenancy\Models;

use AngelitoSystems\FilamentTenancy\Models\Core\TenantCore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant Model
 * 
 * This model extends TenantCore which contains all the business logic.
 * This class only adds Eloquent-specific features like factories.
 */
class Tenant extends TenantCore
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'subdomain',
        'database_name',
        'database_host',
        'database_port',
        'database_username',
        'database_password',
        'is_active',
        'plan', // Legacy: string plan name
        'plan_id', // New: foreign key to plans table
        'expires_at',
        'data',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'plan_id' => 'integer',
        'expires_at' => 'datetime',
        'data' => 'array',
        'database_port' => 'integer',
    ];

    protected $dates = [
        'expires_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the plan for this tenant (if using plan_id).
     */
    public function planModel(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Get all subscriptions for this tenant.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}