<?php

namespace AngelitoSystems\FilamentTenancy\Middleware;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Support\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancy
{
    protected TenantResolver $tenantResolver;

    public function __construct(TenantResolver $tenantResolver)
    {
        $this->tenantResolver = $tenantResolver;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        
        // Check if this is a central domain - allow access without tenant
        if ($this->tenantResolver->isCentralDomain($host)) {
            return $next($request);
        }

        // Try to resolve tenant from request
        $tenant = $this->tenantResolver->resolve($request);

        // If no tenant found and we're not on a central domain, return 404
        if (!$tenant) {
            // Check if custom 404 view exists
            $customView = resource_path('views/vendor/filament-tenancy/errors/tenant-not-found.blade.php');
            $packageView = __DIR__ . '/../../resources/views/errors/tenant-not-found.blade.php';
            
            $viewExists = file_exists($customView) || file_exists($packageView);
            
            if ($viewExists) {
                // Use custom 404 view
                return response()->view('filament-tenancy::errors.tenant-not-found', [
                    'host' => $host,
                    'resolver' => config('filament-tenancy.resolver', 'domain'),
                    'appDomain' => env('APP_DOMAIN'),
                ], 404);
            }
            
            // Fallback to standard 404
            abort(404, "Tenant not found for domain/subdomain: {$host}");
        }

        // Verify tenant is active (unless accessing landlord/admin routes)
        if (!$tenant->isActive() && !$this->isLandlordRoute($request)) {
            abort(403, 'Tenant is not active.');
        }

        // Initialize tenancy with the resolved tenant
        Tenancy::switchToTenant($tenant);

        return $next($request);
    }

    /**
     * Check if the current route is a landlord/admin route.
     */
    protected function isLandlordRoute(Request $request): bool
    {
        $path = $request->path();
        $landlordPaths = config('filament-tenancy.middleware.landlord_paths', [
            '/admin',
            '/landlord',
        ]);

        foreach ($landlordPaths as $landlordPath) {
            if (str_starts_with($path, $landlordPath)) {
                return true;
            }
        }

        return false;
    }
}