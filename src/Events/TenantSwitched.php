<?php

namespace AngelitoSystems\FilamentTenancy\Events;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantSwitched
{
    use Dispatchable, SerializesModels;

    public ?Tenant $tenant;
    public ?Tenant $previousTenant;

    public function __construct(?Tenant $tenant, ?Tenant $previousTenant = null)
    {
        $this->tenant = $tenant;
        $this->previousTenant = $previousTenant;
    }
}