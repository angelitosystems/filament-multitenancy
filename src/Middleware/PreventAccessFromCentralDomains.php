<?php

namespace AngelitoSystems\FilamentTenancy\Middleware;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAccessFromCentralDomains
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $centralDomains = config('filament-tenancy.central_domains', []);

        // If we're on a central domain but trying to access tenant routes
        if (in_array($host, $centralDomains) && Tenancy::current()) {
            abort(404, 'Tenant routes are not accessible from central domains.');
        }

        // If we're not on a central domain but no tenant is resolved
        if (!in_array($host, $centralDomains) && !Tenancy::current()) {
            abort(404, 'Tenant not found for this domain.');
        }

        return $next($request);
    }
}