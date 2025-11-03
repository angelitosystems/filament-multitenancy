<?php

namespace AngelitoSystems\FilamentTenancy;

use AngelitoSystems\FilamentTenancy\Commands\TestLanguageSwitchingCommand;
use AngelitoSystems\FilamentTenancy\Commands\ClearLanguageSessionCommand;
use AngelitoSystems\FilamentTenancy\Commands\DebugConfigCommand;
use AngelitoSystems\FilamentTenancy\Commands\TestManualSwitchCommand;
use AngelitoSystems\FilamentTenancy\Commands\CreateCentralAdminCommand;
use AngelitoSystems\FilamentTenancy\Commands\CreateTenantCommand;
use AngelitoSystems\FilamentTenancy\Commands\CreateTenantUserCommand;
use AngelitoSystems\FilamentTenancy\Commands\AssignRoleToTenantUserCommand;
use AngelitoSystems\FilamentTenancy\Commands\DebugLanguageRoutesCommand;
use AngelitoSystems\FilamentTenancy\Commands\DeleteTenantCommand;
use AngelitoSystems\FilamentTenancy\Commands\DiagnoseLanguageCommand;
use AngelitoSystems\FilamentTenancy\Commands\InstallCommand;
use AngelitoSystems\FilamentTenancy\Commands\ListTenantsCommand;
use AngelitoSystems\FilamentTenancy\Commands\MigrateTenantCommand;
use AngelitoSystems\FilamentTenancy\Commands\MonitorConnectionsCommand;
use AngelitoSystems\FilamentTenancy\Commands\PublishAssetsCommand;
use AngelitoSystems\FilamentTenancy\Commands\TestTranslationsCommand;
use AngelitoSystems\FilamentTenancy\Commands\SeedCentralDatabaseCommand;
use AngelitoSystems\FilamentTenancy\Commands\SetupCentralDatabaseCommand;
use AngelitoSystems\FilamentTenancy\Commands\TenantFreshCommand;
use AngelitoSystems\FilamentTenancy\Commands\TenantMigrateCommand;
use AngelitoSystems\FilamentTenancy\Commands\TenantRollbackCommand;
use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Facades\TenancyPermissions;
use AngelitoSystems\FilamentTenancy\FilamentPlugins\TenancyLandlordPlugin;
use AngelitoSystems\FilamentTenancy\FilamentPlugins\TenancyTenantPlugin;
use AngelitoSystems\FilamentTenancy\Middleware\CheckPermission;
use AngelitoSystems\FilamentTenancy\Middleware\CheckRole;
use AngelitoSystems\FilamentTenancy\Middleware\EnsureTenantAccess;
use AngelitoSystems\FilamentTenancy\Middleware\InitializeTenancy;
use AngelitoSystems\FilamentTenancy\Middleware\PreventAccessFromCentralDomains;
use AngelitoSystems\FilamentTenancy\Middleware\SetLocale;
use AngelitoSystems\FilamentTenancy\Middleware\TenancyPerformanceMonitor;
use AngelitoSystems\FilamentTenancy\Middleware\TenantSessionMiddleware;
use AngelitoSystems\FilamentTenancy\Support\AssetManager;
use AngelitoSystems\FilamentTenancy\Support\DatabaseManager;
use AngelitoSystems\FilamentTenancy\Support\PermissionManager;
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
use Illuminate\Support\Facades\Route;
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
        // Set locale early from session/cookie if available
        // This runs before Laravel uses the default locale from .env
        $this->setLocaleEarly();

        // Register routes first to ensure they're available for plugins
        $this->registerRoutes();

        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/filament-tenancy.php' => config_path('filament-tenancy.php'),
        ], 'filament-tenancy-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'filament-tenancy-migrations');

        // Publish tenant migrations (example migrations for tenant databases)
        $this->publishes([
            __DIR__ . '/../database/migrations/tenant' => database_path('migrations/tenant'),
        ], 'filament-tenancy-tenant-migrations');

        // Publish seeders (will copy seeders to Database\Seeders namespace)
        $this->publishes([
            __DIR__ . '/../database/seeders/PlanSeeder.php' => database_path('seeders/PlanSeeder.php'),
            __DIR__ . '/../database/seeders/CentralRolePermissionSeeder.php' => database_path('seeders/CentralRolePermissionSeeder.php'),
        ], 'filament-tenancy-seeders');

        // Publish tenant seeders (for tenant databases)
        $this->publishes([
            __DIR__ . '/../database/seeders/tenant' => database_path('seeders/tenant'),
        ], 'filament-tenancy-tenant-seeders');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-tenancy');

        // Load language files
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'filament-tenancy');

        // Publish views (404 page)
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-tenancy'),
        ], 'filament-tenancy-views');

        // Publish language files
        $this->publishes([
            __DIR__ . '/../lang' => resource_path('lang/vendor/filament-tenancy'),
        ], 'filament-tenancy-lang');

        // Publish simple language files for __('tenancy.key') usage
        $this->publishes([
            __DIR__ . '/../lang/es/simple.php' => resource_path('lang/es/tenancy.php'),
            __DIR__ . '/../lang/en/simple.php' => resource_path('lang/en/tenancy.php'),
        ], 'filament-tenancy-simple-lang');

        // Publish Filament translations
        $this->publishes([
            __DIR__ . '/../lang/es/filament-actions.php' => resource_path('lang/es/filament-actions.php'),
            __DIR__ . '/../lang/es/filament-panels.php' => resource_path('lang/es/filament-panels.php'),
            __DIR__ . '/../lang/es/filament-tables.php' => resource_path('lang/es/filament-tables.php'),
        ], 'filament-tenancy-filament-lang');

        // Publish routes
        $this->publishes([
            __DIR__ . '/../routes/tenant.php' => base_path('routes/tenant.php'),
        ], 'filament-tenancy-routes');

        // Publish Livewire component
        $this->publishes([
            __DIR__ . '/Components/TenantNotFound.php' => app_path('Livewire/TenantNotFound.php'),
        ], 'filament-tenancy-components');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register events
        $this->registerEvents();

        // Register middleware
        $this->registerMiddleware();

        // Register Filament plugins
        $this->registerFilamentPlugins();

        // Register event listeners
        $this->registerEventListeners();

        // Register locale early setup listener
        $this->registerLocaleEarlySetup();

        // Register asset manager helper
        AssetManager::registerAssetHelper();
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

        // Register PermissionManager
        $this->app->singleton(PermissionManager::class, function ($app) {
            return new PermissionManager();
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
        $this->app->alias(PermissionManager::class, 'tenancy.permissions');
    }

    /**
     * Register commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PublishAssetsCommand::class,
                TestTranslationsCommand::class,
                DiagnoseLanguageCommand::class,
                DebugLanguageRoutesCommand::class,
                TestLanguageSwitchingCommand::class,
                ClearLanguageSessionCommand::class,
                DebugConfigCommand::class,
                TestManualSwitchCommand::class,
                SetupCentralDatabaseCommand::class,
                CreateCentralAdminCommand::class,
                SeedCentralDatabaseCommand::class,
                CreateTenantCommand::class,
                CreateTenantUserCommand::class,
                AssignRoleToTenantUserCommand::class,
                MigrateTenantCommand::class,
                TenantMigrateCommand::class,
                TenantRollbackCommand::class,
                TenantFreshCommand::class,
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
        $router->aliasMiddleware('tenancy.session', TenantSessionMiddleware::class);
        $router->aliasMiddleware('permission', CheckPermission::class);
        $router->aliasMiddleware('role', CheckRole::class);
        $router->aliasMiddleware('locale', SetLocale::class);

        // Add global middleware if configured
        if (config('filament-tenancy.middleware.global', true)) {
            $router->pushMiddlewareToGroup('web', InitializeTenancy::class);
            // Add session middleware after initialization to prevent 419 errors
            $router->pushMiddlewareToGroup('web', TenantSessionMiddleware::class);
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
                    $panel->plugin(TenancyTenantPlugin::make());
                } elseif ($panelId === $landlordPanelId) {
                    $panel->plugin(TenancyLandlordPlugin::make());
                }
            });
        }
    }

    /**
     * Register events.
     */
    protected function registerEvents(): void
    {
        // Register tenant creation events
        $this->app['events']->listen(
            \AngelitoSystems\FilamentTenancy\Events\TenantCreated::class,
            \AngelitoSystems\FilamentTenancy\Listeners\CreateRolesAndPermissionsOnTenantCreated::class
        );

        $this->app['events']->listen(
            \AngelitoSystems\FilamentTenancy\Events\TenantCreated::class,
            \AngelitoSystems\FilamentTenancy\Listeners\RunTenantMigrationsOnTenantCreated::class
        );
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
     * Register package routes.
     */
    protected function registerRoutes(): void
    {
        // Register routes immediately with the router
        Route::middleware('web')
            ->prefix('language')
            ->group(function () {
                Route::get('{locale}', function (string $locale) {
                    if (in_array($locale, array_keys(\AngelitoSystems\FilamentTenancy\Components\LanguageSwitcher::getAvailableLocales()))) {
                        \AngelitoSystems\FilamentTenancy\Components\LanguageSwitcher::setLocale($locale);
                    }
                    return redirect()->back();
                })->name('language.switch');
            });

        // Also load from file if it exists
        if (file_exists(__DIR__ . '/../routes/tenant.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/tenant.php');
        }
        
        // Load additional web routes for Laravel 12 compatibility
        if (file_exists(__DIR__ . '/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
        
        // Load enhanced language switching routes
        if (file_exists(__DIR__ . '/../routes/enhanced-language.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/enhanced-language.php');
        }
    }

    /**
     * Set locale early from session/cookie before Laravel uses .env default.
     * This ensures user's locale preference is respected even before middleware runs.
     */
    protected function setLocaleEarly(): void
    {
        // Try to set locale if request is available during boot
        // This is a fallback, the main logic is in registerLocaleEarlySetup()
        if (!$this->app->runningInConsole() && $this->app->bound('request')) {
            try {
                $request = $this->app->make('request');
                $this->applyLocaleFromRequest($request);
            } catch (\Exception $e) {
                // Silently fail - listener will handle it
            }
        }
    }

    /**
     * Register listener to set locale early when request is available.
     */
    protected function registerLocaleEarlySetup(): void
    {
        // Listen for route matched event to set locale early
        // This runs before most middleware but after request is available
        Event::listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
            if (!$this->app->runningInConsole() && $event->request) {
                $this->applyLocaleFromRequest($event->request);
            }
        });

        // Also listen for any HTTP request if route matching hasn't fired yet
        // This is a backup in case route matching event doesn't fire
        if (!$this->app->runningInConsole() && $this->app->bound('request')) {
            try {
                $request = $this->app->make('request');
                $this->applyLocaleFromRequest($request);
            } catch (\Exception $e) {
                // Silently fail - middleware will handle it
            }
        }
    }

    /**
     * Apply locale from request (cookie or session).
     */
    protected function applyLocaleFromRequest($request): void
    {
        try {
            // Check if localization is enabled
            if (!config('filament-tenancy.localization.enabled', true)) {
                return;
            }

            $locale = null;
            $availableLocales = array_keys(\AngelitoSystems\FilamentTenancy\Components\LanguageSwitcher::getAvailableLocales());

            // Priority 1: Check cookie (works even before session starts)
            if ($request->hasCookie('locale')) {
                $cookieLocale = $request->cookie('locale');
                if (in_array($cookieLocale, $availableLocales)) {
                    $locale = $cookieLocale;
                }
            }

            // Priority 2: Check session if it's started
            if (!$locale && $request->hasSession() && $request->session()->isStarted()) {
                $sessionLocale = $request->session()->get('locale');
                if ($sessionLocale && in_array($sessionLocale, $availableLocales)) {
                    $locale = $sessionLocale;
                }
            }

            // If we found a valid locale, set it early
            if ($locale && \Illuminate\Support\Facades\App::getLocale() !== $locale) {
                \Illuminate\Support\Facades\App::setLocale($locale);
                \Illuminate\Support\Facades\Log::info('TenancyServiceProvider: Set locale early from session/cookie', [
                    'locale' => $locale,
                    'source' => $request->hasCookie('locale') ? 'cookie' : 'session',
                    'previous_locale' => config('app.locale'),
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if we can't set locale early
            // The middleware will handle it later
            \Illuminate\Support\Facades\Log::debug('TenancyServiceProvider: Could not set locale early', [
                'error' => $e->getMessage(),
            ]);
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
            PermissionManager::class,
            'tenancy',
            'tenancy.permissions',
        ];
    }
}