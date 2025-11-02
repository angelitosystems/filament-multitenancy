<?php

namespace AngelitoSystems\FilamentTenancy\Support;

class DomainResolver
{
    /**
     * Get the base domain for subdomain-based tenancy.
     * 
     * Priority order:
     * 1. APP_DOMAIN environment variable
     * 2. Extract from APP_URL (remove subdomain if present)
     * 3. Extract from central_domains config
     * 4. Fallback to 'localhost'
     */
    public function getBaseDomain(): string
    {
        // Priority 1: Always use APP_DOMAIN if configured
        $baseDomain = env('APP_DOMAIN');
        
        if (!$baseDomain) {
            // Priority 2: Extract base domain from APP_URL (remove subdomain if present)
            $baseDomain = $this->extractBaseDomainFromAppUrl();
        }
        
        if (!$baseDomain) {
            // Priority 3: Extract base domain from central_domains config
            $baseDomain = $this->extractBaseDomainFromConfig();
        }
        
        // Priority 4: Last resort fallback
        if (!$baseDomain) {
            $baseDomain = 'localhost';
        }
        
        return $baseDomain;
    }

    /**
     * Extract base domain from APP_URL.
     */
    protected function extractBaseDomainFromAppUrl(): ?string
    {
        $appUrl = env('APP_URL');
        if (!$appUrl) {
            return null;
        }

        $parsedUrl = parse_url($appUrl);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return null;
        }

        $host = $parsedUrl['host'];
        
        // Only use if it's not localhost and doesn't have a port
        if (in_array($host, ['localhost', '127.0.0.1', '::1']) || isset($parsedUrl['port'])) {
            return null;
        }

        // Extract base domain (remove subdomain if present)
        // e.g., "app.dental.test" -> "dental.test"
        return $this->extractBaseDomain($host);
    }

    /**
     * Extract base domain from central_domains config.
     */
    protected function extractBaseDomainFromConfig(): ?string
    {
        $centralDomains = config('filament-tenancy.central_domains', []);
        
        foreach ($centralDomains as $centralDomain) {
            // Skip localhost/ip addresses
            if (in_array($centralDomain, ['localhost', '127.0.0.1', '::1'])) {
                continue;
            }
            
            // Extract base domain if it's a subdomain
            $baseDomain = $this->extractBaseDomain($centralDomain);
            if ($baseDomain && $baseDomain !== $centralDomain) {
                return $baseDomain;
            }
            
            // If it's already a base domain (2 parts), use it
            $parts = explode('.', $centralDomain);
            if (count($parts) === 2) {
                return $centralDomain;
            }
        }
        
        return null;
    }

    /**
     * Extract base domain from a host string.
     * Removes subdomain if present.
     * 
     * Examples:
     * - "app.dental.test" -> "dental.test"
     * - "dental.test" -> "dental.test"
     * - "sub.app.dental.test" -> "app.dental.test"
     */
    protected function extractBaseDomain(string $host): string
    {
        $parts = explode('.', $host);
        
        // If it has more than 2 parts, remove the first part (subdomain)
        if (count($parts) > 2) {
            return implode('.', array_slice($parts, 1));
        }
        
        // Already a base domain
        return $host;
    }

    /**
     * Build full domain for a tenant.
     * 
     * @param string|null $domain Full domain (if tenant uses domain-based identification)
     * @param string|null $subdomain Subdomain (if tenant uses subdomain-based identification)
     * @return string Full domain for the tenant
     */
    public function buildFullDomain(?string $domain, ?string $subdomain): string
    {
        // If tenant has a full domain, use it directly
        if ($domain) {
            return $domain;
        }

        // If tenant has a subdomain, build full domain
        if ($subdomain) {
            $baseDomain = $this->getBaseDomain();
            return $subdomain . '.' . $baseDomain;
        }

        return '';
    }
}

