<?php

namespace AngelitoSystems\FilamentTenancy\FilamentPlugins;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Middleware\InitializeTenancy;
use AngelitoSystems\FilamentTenancy\Resources\TenantResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class LandlordPlugin implements Plugin
{
    protected bool $autoRegister = true;
    protected array $middleware = [];
    protected array $resources = [];
    protected array $pages = [];

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
        return 'filament-tenancy-landlord';
    }

    public function register(Panel $panel): void
    {
        // Add tenancy middleware (but not tenant access enforcement)
        $panel->middleware([
            InitializeTenancy::class,
            ...$this->middleware,
        ]);

        // Configure panel for landlord context
        $panel->brandName(config('app.name', 'Landlord Panel'));

        // Note: In Filament 4, database connection is handled at model level, not panel level
        // The default Laravel connection will be used automatically

        // Register landlord-specific resources
        if ($this->autoRegister) {
            $panel->resources([
                TenantResource::class,
                ...$this->resources,
            ]);
        }

        // Register landlord-specific pages
        if (!empty($this->pages)) {
            $panel->pages($this->pages);
        }
    }

    public function boot(Panel $panel): void
    {
        // Boot logic for landlord plugin
        if ($this->autoRegister) {
            $this->registerLandlordResources($panel);
        }

        // Ensure we're always in central context for landlord panel
        Tenancy::switchToCentral();
    }

    /**
     * Set whether to auto-register landlord resources.
     */
    public function autoRegister(bool $autoRegister = true): static
    {
        $this->autoRegister = $autoRegister;
        return $this;
    }

    /**
     * Add middleware to the landlord panel.
     */
    public function middleware(array $middleware): static
    {
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Add resources to the landlord panel.
     */
    public function resources(array $resources): static
    {
        $this->resources = array_merge($this->resources, $resources);
        return $this;
    }

    /**
     * Add pages to the landlord panel.
     */
    public function pages(array $pages): static
    {
        $this->pages = array_merge($this->pages, $pages);
        return $this;
    }

    /**
     * Register landlord-specific resources.
     */
    protected function registerLandlordResources(Panel $panel): void
    {
        // Auto-discover and register landlord resources
        $landlordResourcesPath = config('filament-tenancy.landlord_resources_path');
        
        if ($landlordResourcesPath && is_dir($landlordResourcesPath)) {
            $this->discoverResources($panel, $landlordResourcesPath);
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