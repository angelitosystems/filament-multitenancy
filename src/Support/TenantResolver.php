<?php

namespace AngelitoSystems\FilamentTenancy\Support;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TenantResolver
{
    /**
     * Resolve tenant from the current request.
     */
    public function resolve(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $resolver = config('filament-tenancy.resolver', 'domain');

        // Check if this is a central domain - if so, don't resolve tenant
        if ($this->isCentralDomain($host)) {
            return null;
        }

        // Use cache if enabled
        if (config('filament-tenancy.cache.enabled', true)) {
            $cacheKey = $this->getCacheKey($host, $resolver);
            return Cache::remember($cacheKey, config('filament-tenancy.cache.ttl', 3600), function () use ($host, $resolver, $request) {
                return $this->resolveTenant($host, $resolver, $request);
            });
        }

        return $this->resolveTenant($host, $resolver, $request);
    }

    /**
     * Resolve tenant based on the configured strategy.
     */
    protected function resolveTenant(string $host, string $resolver, Request $request): ?Tenant
    {
        $result = match ($resolver) {
            'subdomain' => $this->resolveBySubdomain($host),
            'domain' => $this->resolveByDomain($host),
            'path' => $this->resolveByPath($request),
            default => null,
        };

        // If resolver is 'domain' but host has 3+ parts (subdomain.domain.tld),
        // also try subdomain resolution as fallback
        if (!$result && $resolver === 'domain') {
            $parts = explode('.', $host);
            if (count($parts) >= 3) {
                $result = $this->resolveBySubdomain($host);
            }
        }

        return $result;
    }

    /**
     * Resolve tenant by subdomain.
     * Example: "cdc.dental.test" → tenant with slug "cdc"
     * 
     * The base domain (APP_DOMAIN) should not resolve a tenant.
     */
    protected function resolveBySubdomain(string $host): ?Tenant
    {
        $parts = explode('.', $host);
        
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];
        
        // Extract base domain (everything after the first dot)
        $baseDomain = implode('.', array_slice($parts, 1));
        
        // If the base domain matches APP_DOMAIN, it's a tenant subdomain - always resolve
        $appDomain = env('APP_DOMAIN');
        if ($appDomain && $baseDomain === $appDomain) {
            // This is a subdomain of APP_DOMAIN, so it's a tenant subdomain
            // Continue with resolution
        } else {
            // If base domain is a central domain (but not APP_DOMAIN), don't resolve
            // Exception: if APP_DOMAIN is not set, allow resolution if not explicitly central
            if ($this->isCentralDomain($baseDomain)) {
                // Only block if APP_DOMAIN is set and different from baseDomain
                // If APP_DOMAIN is not set, allow resolution
                if ($appDomain && $baseDomain !== $appDomain) {
                    return null;
                }
                // If APP_DOMAIN is not set, continue with resolution
            }
        }

        // Try to find tenant by subdomain field first
        $tenant = Tenant::where('subdomain', $subdomain)
            ->where('is_active', true)
            ->first();

        // Fallback to slug if subdomain field is not set
        if (!$tenant) {
            $tenant = Tenant::where('slug', $subdomain)
                ->where('is_active', true)
                ->first();
        }

        return $tenant;
    }

    /**
     * Resolve tenant by domain.
     * Example: "clinicaabc.com" → tenant with domain "clinicaabc.com"
     */
    protected function resolveByDomain(string $host): ?Tenant
    {
        return Tenant::where('domain', $host)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Resolve tenant by path.
     * Example: "/tenant/abc" → tenant with slug "abc"
     */
    protected function resolveByPath(Request $request): ?Tenant
    {
        $path = $request->path();
        $segments = explode('/', $path);

        if (count($segments) < 2 || $segments[0] !== 'tenant') {
            return null;
        }

        $tenantSlug = $segments[1];

        return Tenant::where('slug', $tenantSlug)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if the given host is a central domain.
     * 
     * APP_DOMAIN is always considered a central domain.
     */
    public function isCentralDomain(string $host): bool
    {
        // APP_DOMAIN is always considered a central domain
        $appDomain = env('APP_DOMAIN');
        if ($appDomain && $host === $appDomain) {
            return true;
        }

        // Check against configured central domains
        $centralDomains = config('filament-tenancy.central_domains', []);

        return in_array($host, $centralDomains, true);
    }

    /**
     * Get cache key for tenant resolution.
     */
    protected function getCacheKey(string $host, string $resolver): string
    {
        $prefix = config('filament-tenancy.cache.prefix', 'tenancy');
        
        return "{$prefix}:tenant:{$resolver}:{$host}";
    }

    /**
     * Clear tenant cache.
     */
    public function clearCache(?string $host = null): void
    {
        if (! config('filament-tenancy.cache.enabled', true)) {
            return;
        }

        $prefix = config('filament-tenancy.cache.prefix', 'tenancy');

        if ($host) {
            $resolvers = ['domain', 'subdomain', 'path'];
            foreach ($resolvers as $resolver) {
                Cache::forget("{$prefix}:tenant:{$resolver}:{$host}");
            }
        } else {
            // Clear all tenant cache
            Cache::flush();
        }
    }

    /**
     * Get the current tenant from the application context.
     */
    public function current(): ?Tenant
    {
        try {
            return app('current-tenant');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the current tenant in the application context.
     */
    public function setCurrent(?Tenant $tenant): void
    {
        app()->instance('current-tenant', $tenant);
    }
}