<?php

namespace AngelitoSystems\FilamentTenancy;

use AngelitoSystems\FilamentTenancy\Commands\CreateTenantCommand;
use AngelitoSystems\FilamentTenancy\Commands\DeleteTenantCommand;
use AngelitoSystems\FilamentTenancy\Commands\InstallCommand;
use AngelitoSystems\FilamentTenancy\Commands\ListTenantsCommand;
use AngelitoSystems\FilamentTenancy\Commands\MigrateTenantCommand;
use AngelitoSystems\FilamentTenancy\Commands\MonitorConnectionsCommand;
use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\FilamentPlugins\LandlordPlugin;
use AngelitoSystems\FilamentTenancy\FilamentPlugins\TenantPlugin;
use AngelitoSystems\FilamentTenancy\Middleware\EnsureTenantAccess;
use AngelitoSystems\FilamentTenancy\Middleware\InitializeTenancy;
use AngelitoSystems\FilamentTenancy\Middleware\PreventAccessFromCentralDomains;
use AngelitoSystems\FilamentTenancy\Middleware\TenancyPerformanceMonitor;
use AngelitoSystems\FilamentTenancy\Support\DatabaseManager;
use AngelitoSystems\FilamentTenancy\Support\TenantManager;
use AngelitoSystems\FilamentTenancy\Support\TenantResolver;
use AngelitoSystems\FilamentTenancy\Support\ConnectionManager;
use AngelitoSystems\FilamentTenancy\Support\CredentialManager;
use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use AngelitoSystems\FilamentTenancy\Support\DomainResolver;
use AngelitoSystems\FilamentTenancy\Support\TenantUrlGenerator;
use AngelitoSystems\FilamentTenancy\Support\Contracts\ConnectionManagerInterface;
use AngelitoSystems\FilamentTenancy\Support\Contracts\CredentialManagerInterface;
use Filament\Panel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-tenancy.php',
            'filament-tenancy'
        );

        // Register core services
        $this->registerCoreServices();

        // Register facades
        $this->registerFacades();

        // Register commands
        $this->registerCommands();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/filament-tenancy.php' => config_path('filament-tenancy.php'),
        ], 'filament-tenancy-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'filament-tenancy-migrations');

        // Publish seeders (will copy PlanSeeder to Database\Seeders namespace)
        $this->publishes([
            __DIR__ . '/../database/seeders/PlanSeeder.php' => database_path('seeders/PlanSeeder.php'),
        ], 'filament-tenancy-seeders');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register middleware
        $this->registerMiddleware();

        // Register Filament plugins
        $this->registerFilamentPlugins();

        // Register event listeners
        $this->registerEventListeners();
    }

    /**
     * Register core services.
     */
    protected function registerCoreServices(): void
    {
        // Register TenancyLogger
        $this->app->singleton(TenancyLogger::class, function ($app) {
            return new TenancyLogger(
                config('filament-tenancy.logging.channel', 'tenancy')
            );
        });

        // Register CredentialManager
        $this->app->singleton(CredentialManagerInterface::class, function ($app) {
            return new CredentialManager(
                $app->make(TenancyLogger::class)
            );
        });

        // Register ConnectionManager
        $this->app->singleton(ConnectionManagerInterface::class, function ($app) {
            return new ConnectionManager(
                $app->make(CredentialManagerInterface::class),
                $app->make(TenancyLogger::class)
            );
        });

        // Register TenantResolver
        $this->app->singleton(TenantResolver::class, function ($app) {
            return new TenantResolver();
        });

        // Register DatabaseManager
        $this->app->singleton(DatabaseManager::class, function ($app) {
            return new DatabaseManager(
                $app->make('db'),
                $app->make(ConnectionManagerInterface::class)
            );
        });

        // Register TenantManager
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager(
                $app->make(TenantResolver::class),
                $app->make(DatabaseManager::class)
            );
        });

        // Register DomainResolver
        $this->app->singleton(DomainResolver::class, function ($app) {
            return new DomainResolver();
        });

        // Register TenantUrlGenerator
        $this->app->singleton(TenantUrlGenerator::class, function ($app) {
            return new TenantUrlGenerator(
                $app->make(DomainResolver::class)
            );
        });

        // Register current tenant instance
        $this->app->instance('current-tenant', null);
    }

    /**
     * Register facades.
     */
    protected function registerFacades(): void
    {
        $this->app->alias(TenantResolver::class, 'tenancy.resolver');
        $this->app->alias(DatabaseManager::class, 'tenancy.database');
        $this->app->alias(TenantManager::class, 'tenancy');
    }

    /**
     * Register commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CreateTenantCommand::class,
                MigrateTenantCommand::class,
                ListTenantsCommand::class,
                DeleteTenantCommand::class,
                MonitorConnectionsCommand::class,
            ]);
        }
    }

    /**
     * Register middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('tenancy.initialize', InitializeTenancy::class);
        $router->aliasMiddleware('tenancy.prevent-central-access', PreventAccessFromCentralDomains::class);
        $router->aliasMiddleware('tenancy.ensure-tenant-access', EnsureTenantAccess::class);
        $router->aliasMiddleware('tenancy.performance-monitor', TenancyPerformanceMonitor::class);

        // Add global middleware if configured
        if (config('filament-tenancy.middleware.global', true)) {
            $router->pushMiddlewareToGroup('web', InitializeTenancy::class);
        }
    }

    /**
     * Register Filament plugins.
     */
    protected function registerFilamentPlugins(): void
    {
        // Auto-register plugins if enabled
        if (config('filament-tenancy.filament.auto_register_plugins', true)) {
            Panel::configureUsing(function (Panel $panel) {
                $panelId = $panel->getId();
                $tenantPanelId = config('filament-tenancy.filament.tenant_panel_id', 'tenant');
                $landlordPanelId = config('filament-tenancy.filament.landlord_panel_id', 'admin');

                if ($panelId === $tenantPanelId) {
                    $panel->plugin(TenantPlugin::make());
                } elseif ($panelId === $landlordPanelId) {
                    $panel->plugin(LandlordPlugin::make());
                }
            });
        }
    }

    /**
     * Register event listeners.
     */
    protected function registerEventListeners(): void
    {
        // Register event listeners from configuration
        $listeners = config('filament-tenancy.events.listeners', []);

        foreach ($listeners as $event => $eventListeners) {
            foreach ($eventListeners as $listener) {
                $this->app['events']->listen($event, $listener);
            }
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            TenantResolver::class,
            DatabaseManager::class,
            TenantManager::class,
            'tenancy',
        ];
    }
}