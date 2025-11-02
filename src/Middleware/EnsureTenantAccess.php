<?php

namespace AngelitoSystems\FilamentTenancy\Middleware;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenancy::current();

        // Ensure we have a current tenant
        if (!$tenant) {
            abort(404, 'Tenant not found for this domain or subdomain.');
        }

        // Ensure tenant is active
        if (!$tenant->isActive()) {
            abort(403, 'Access denied: Tenant is not active or has expired.');
        }

        return $next($request);
    }
}