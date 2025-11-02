<?php

namespace AngelitoSystems\FilamentTenancy\Facades;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\DatabaseManager;
use AngelitoSystems\FilamentTenancy\Support\TenantManager;
use AngelitoSystems\FilamentTenancy\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void initialize(Request $request)
 * @method static void switchToTenant(Tenant $tenant)
 * @method static void switchToCentral()
 * @method static Tenant|null current()
 * @method static bool isTenant()
 * @method static bool isCentral()
 * @method static Tenant createTenant(array $attributes)
 * @method static bool deleteTenant(Tenant $tenant)
 * @method static mixed runForTenant(Tenant $tenant, callable $callback)
 * @method static mixed runForCentral(callable $callback)
 * @method static \Illuminate\Database\Eloquent\Collection getAllTenants()
 * @method static Tenant|null findTenant(int $id)
 * @method static Tenant|null findTenantBySlug(string $slug)
 * @method static Tenant|null findTenantByDomain(string $domain)
 * @method static TenantResolver resolver()
 * @method static DatabaseManager database()
 *
 * @see TenantManager
 */
class Tenancy extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TenantManager::class;
    }
}