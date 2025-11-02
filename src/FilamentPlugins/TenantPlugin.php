<?php

namespace AngelitoSystems\FilamentTenancy\FilamentPlugins;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Middleware\EnsureTenantAccess;
use AngelitoSystems\FilamentTenancy\Middleware\InitializeTenancy;
use Filament\Contracts\Plugin;
use Filament\Panel;

class TenantPlugin implements Plugin
{
    protected bool $autoRegister = true;
    protected array $middleware = [];
    protected array $excludedResources = [];
    protected array $excludedPages = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-tenancy-tenant';
    }

    public function register(Panel $panel): void
    {
        // Add tenancy middleware
        $panel->middleware([
            InitializeTenancy::class,
            EnsureTenantAccess::class,
            ...$this->middleware,
        ]);

        // Configure panel for tenant context
        $panel->brandName(function () {
            $tenant = Tenancy::current();
            return $tenant ? $tenant->name : config('app.name');
        });

        // Note: In Filament 4, database connection is handled at model level via traits
        // Tenant models should use BelongsToTenant trait to use tenant connections

        // Configure tenant-specific paths if needed
        if (config('filament-tenancy.tenant_asset_url')) {
            $panel->assets([
                // Add tenant-specific assets here
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        // Boot logic for tenant plugin
        if ($this->autoRegister) {
            $this->registerTenantResources($panel);
        }
    }

    /**
     * Set whether to auto-register tenant resources.
     */
    public function autoRegister(bool $autoRegister = true): static
    {
        $this->autoRegister = $autoRegister;
        return $this;
    }

    /**
     * Add middleware to the tenant panel.
     */
    public function middleware(array $middleware): static
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Exclude resources from tenant context.
     */
    public function excludeResources(array $resources): static
    {
        $this->excludedResources = array_merge($this->excludedResources, $resources);
        return $this;
    }

    /**
     * Exclude pages from tenant context.
     */
    public function excludePages(array $pages): static
    {
        $this->excludedPages = array_merge($this->excludedPages, $pages);
        return $this;
    }

    /**
     * Register tenant-specific resources.
     */
    protected function registerTenantResources(Panel $panel): void
    {
        // Auto-discover and register tenant resources
        $tenantResourcesPath = config('filament-tenancy.tenant_resources_path');
        
        if ($tenantResourcesPath && is_dir($tenantResourcesPath)) {
            $this->discoverResources($panel, $tenantResourcesPath);
        }
    }

    /**
     * Discover resources in the given path.
     */
    protected function discoverResources(Panel $panel, string $path): void
    {
        $files = glob($path . '/*.php');
        
        foreach ($files as $file) {
            $className = $this->getClassNameFromFile($file);
            
            if ($className && 
                class_exists($className) && 
                !in_array($className, $this->excludedResources) &&
                is_subclass_of($className, \Filament\Resources\Resource::class)) {
                
                $panel->resources([$className]);
            }
        }
    }

    /**
     * Get class name from file path.
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $content = file_get_contents($file);
        
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches) &&
            preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            
            return $namespaceMatches[1] . '\\' . $classMatches[1];
        }
        
        return null;
    }
}