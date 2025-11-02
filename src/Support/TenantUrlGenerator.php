<?php

namespace AngelitoSystems\FilamentTenancy\Support;

class TenantUrlGenerator
{
    protected DomainResolver $domainResolver;

    public function __construct(DomainResolver $domainResolver)
    {
        $this->domainResolver = $domainResolver;
    }

    /**
     * Generate URL for a tenant.
     * 
     * @param string|null $domain Full domain (if tenant uses domain-based identification)
     * @param string|null $subdomain Subdomain (if tenant uses subdomain-based identification)
     * @param string $path Optional path to append to the URL
     * @return string Full URL for the tenant
     */
    public function generateUrl(?string $domain, ?string $subdomain, string $path = ''): string
    {
        $fullDomain = $this->domainResolver->buildFullDomain($domain, $subdomain);
        
        if (!$fullDomain) {
            return '';
        }

        $protocol = config('filament-tenancy.https', false) ? 'https' : 'http';
        $url = $protocol . '://' . $fullDomain;
        
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }

        return $url;
    }

    /**
     * Generate full domain for a tenant.
     * 
     * @param string|null $domain Full domain (if tenant uses domain-based identification)
     * @param string|null $subdomain Subdomain (if tenant uses subdomain-based identification)
     * @return string Full domain for the tenant
     */
    public function generateDomain(?string $domain, ?string $subdomain): string
    {
        return $this->domainResolver->buildFullDomain($domain, $subdomain);
    }
}

