<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The model that represents a tenant in your application.
    |
    */
    'tenant_model' => \AngelitoSystems\FilamentTenancy\Models\Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution Strategy
    |--------------------------------------------------------------------------
    |
    | This option controls how tenants are resolved from incoming requests.
    | Supported: "domain", "subdomain", "path"
    |
    */
    'resolver' => env('TENANCY_RESOLVER', 'domain'),

    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    |
    | These domains are considered "central" and will not be resolved as tenants.
    | They typically host the landlord panel for managing tenants.
    |
    */
    'central_domains' => [
        'localhost',
        '127.0.0.1',
        // APP_DOMAIN is automatically considered a central domain
        // You can add additional central domains here if needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant database management.
    |
    */
    'database' => [
        /*
        |--------------------------------------------------------------------------
        | Default Connection Name
        |--------------------------------------------------------------------------
        |
        | The default database connection name to use. This will use Laravel's
        | default connection from .env (DB_CONNECTION) if not specified.
        |
        */
        'default_connection' => env('DB_CONNECTION', 'mysql'),

        /*
        |--------------------------------------------------------------------------
        | Auto Create Database
        |--------------------------------------------------------------------------
        |
        | Whether to automatically create the database when creating a tenant.
        |
        */
        'auto_create' => env('TENANCY_AUTO_CREATE_DB', true),

        /*
        |--------------------------------------------------------------------------
        | Auto Delete Database
        |--------------------------------------------------------------------------
        |
        | Whether to automatically delete the database when deleting a tenant.
        |
        */
        'auto_delete' => env('TENANCY_AUTO_DELETE_DB', false),

        /*
        |--------------------------------------------------------------------------
        | Connection Pool Size
        |--------------------------------------------------------------------------
        |
        | Maximum number of database connections to pool per tenant.
        |
        */
        'connection_pool_size' => env('TENANCY_CONNECTION_POOL_SIZE', 10),

        /*
        |--------------------------------------------------------------------------
        | Connection Timeout
        |--------------------------------------------------------------------------
        |
        | Database connection timeout in seconds.
        |
        */
        'connection_timeout' => env('TENANCY_CONNECTION_TIMEOUT', 30),

        /*
        |--------------------------------------------------------------------------
        | Max Connections Per Tenant
        |--------------------------------------------------------------------------
        |
        | Maximum number of concurrent connections allowed per tenant.
        |
        */
        'max_connections_per_tenant' => env('TENANCY_MAX_CONNECTIONS_PER_TENANT', 5),

        /*
        |--------------------------------------------------------------------------
        | Tenant Connection Template
        |--------------------------------------------------------------------------
        |
        | Template configuration for tenant database connections.
        | Each tenant will have its own database using this template.
        | This uses Laravel's default database configuration from .env
        | and supports sqlite, mysql, and pgsql.
        |
        | Set to null to use Laravel's default connection configuration.
        | Otherwise, provide an array with connection settings.
        |
        */
        'tenants_connection_template' => null, // null = use Laravel's default DB config from .env
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for caching tenant resolution and database connections.
    |
    */
    'cache' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Caching
        |--------------------------------------------------------------------------
        |
        | Whether to cache tenant resolution results for better performance.
        |
        */
        'enabled' => env('TENANCY_CACHE_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Cache TTL
        |--------------------------------------------------------------------------
        |
        | Time to live for cached tenant data in seconds.
        |
        */
        'ttl' => env('TENANCY_CACHE_TTL', 3600),

        /*
        |--------------------------------------------------------------------------
        | Cache Key Prefix
        |--------------------------------------------------------------------------
        |
        | Prefix for tenant cache keys.
        |
        */
        'prefix' => 'tenancy',
    ],

    /*
    |--------------------------------------------------------------------------
    | Events Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant-related events and listeners.
    |
    */
    'events' => [
        /*
        |--------------------------------------------------------------------------
        | Auto Register Listeners
        |--------------------------------------------------------------------------
        |
        | Whether to automatically register default event listeners.
        |
        */
        'auto_register_listeners' => true,

        /*
        |--------------------------------------------------------------------------
        | Event Listeners
        |--------------------------------------------------------------------------
        |
        | Custom event listeners for tenant events.
        |
        */
        'listeners' => [
            // 'AngelitoSystems\FilamentTenancy\Events\TenantCreated' => [
            //     'AngelitoSystems\FilamentTenancy\Listeners\CreateTenantDatabase',
            //     'AngelitoSystems\FilamentTenancy\Listeners\RunTenantMigrations',
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenancy middleware.
    |
    */
    'middleware' => [
        /*
        |--------------------------------------------------------------------------
        | Auto Register Middleware
        |--------------------------------------------------------------------------
        |
        | Whether to automatically register tenancy middleware globally.
        |
        */
        'auto_register' => true,

        /*
        |--------------------------------------------------------------------------
        | Global Middleware
        |--------------------------------------------------------------------------
        |
        | Whether to register InitializeTenancy middleware globally in the 'web' group.
        |
        */
        'global' => true,

        /*
        |--------------------------------------------------------------------------
        | Middleware Priority
        |--------------------------------------------------------------------------
        |
        | Priority for tenancy middleware in the middleware stack.
        |
        */
        'priority' => 100,

        /*
        |--------------------------------------------------------------------------
        | Landlord Paths
        |--------------------------------------------------------------------------
        |
        | Paths that should be accessible from landlord/central domains even
        | when no tenant is resolved. These paths are typically admin panels.
        |
        */
        'landlord_paths' => [
            '/admin',
            '/landlord',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Filament panel integration.
    |
    */
    'filament' => [
        /*
        |--------------------------------------------------------------------------
        | Auto Register Plugins
        |--------------------------------------------------------------------------
        |
        | Whether to automatically register Filament plugins.
        |
        */
        'auto_register_plugins' => true,

        /*
        |--------------------------------------------------------------------------
        | Landlord Panel ID
        |--------------------------------------------------------------------------
        |
        | The panel ID for the landlord/central panel.
        |
        */
        'landlord_panel_id' => 'admin',

        /*
        |--------------------------------------------------------------------------
        | Tenant Panel ID
        |--------------------------------------------------------------------------
        |
        | The panel ID for tenant panels.
        |
        */
        'tenant_panel_id' => 'tenant',

        /*
        |--------------------------------------------------------------------------
        | Tenant Panel Path
        |--------------------------------------------------------------------------
        |
        | The path for tenant panels.
        |
        */
        'tenant_panel_path' => '/admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security-related configuration options.
    |
    */
    'security' => [
        /*
        |--------------------------------------------------------------------------
        | Prevent Cross-Tenant Access
        |--------------------------------------------------------------------------
        |
        | Whether to prevent access to tenant panels from central domains.
        |
        */
        'prevent_cross_tenant_access' => true,

        /*
        |--------------------------------------------------------------------------
        | Allowed Central Routes
        |--------------------------------------------------------------------------
        |
        | Routes that are allowed to be accessed from central domains.
        |
        */
        'allowed_central_routes' => [
            'login',
            'logout',
            'register',
            'password.*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the tenancy logging system.
    |
    */
    'logging' => [
        'enabled' => env('TENANCY_LOGGING_ENABLED', true),
        'channel' => env('TENANCY_LOG_CHANNEL', 'tenancy'),
        'level' => env('TENANCY_LOG_LEVEL', 'info'),
        'mask_sensitive_data' => env('TENANCY_MASK_SENSITIVE_DATA', true),
        
        // Log specific events
        'log_events' => [
            'tenant_connections' => true,
            'database_operations' => true,
            'credential_operations' => true,
            'security_events' => true,
            'performance_metrics' => true,
            'configuration_changes' => true,
        ],
        
        // Performance logging thresholds
        'performance_thresholds' => [
            'slow_query_ms' => env('TENANCY_SLOW_QUERY_THRESHOLD', 1000),
            'slow_connection_ms' => env('TENANCY_SLOW_CONNECTION_THRESHOLD', 500),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for performance monitoring and alerts.
    |
    */
    'monitoring' => [
        'enabled' => env('TENANCY_MONITORING_ENABLED', true),
        'performance_threshold_ms' => env('TENANCY_PERFORMANCE_THRESHOLD', 1000),
        'memory_threshold_mb' => env('TENANCY_MEMORY_THRESHOLD', 128),
        'connection_timeout' => env('TENANCY_CONNECTION_TIMEOUT', 30),
        
        // Monitoring intervals (in seconds)
        'intervals' => [
            'connection_check' => env('TENANCY_CONNECTION_CHECK_INTERVAL', 60),
            'performance_check' => env('TENANCY_PERFORMANCE_CHECK_INTERVAL', 30),
            'memory_check' => env('TENANCY_MEMORY_CHECK_INTERVAL', 120),
        ],
        
        // Alert thresholds
        'alerts' => [
            'max_failed_connections' => env('TENANCY_MAX_FAILED_CONNECTIONS', 5),
            'max_connection_time_ms' => env('TENANCY_MAX_CONNECTION_TIME', 2000),
            'max_memory_usage_mb' => env('TENANCY_MAX_MEMORY_USAGE', 256),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the centralized connection management system.
    |
    */
    'connection_management' => [
        'enabled' => true,
        'credential_profiles' => [
            'default' => [
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'username' => env('DB_USERNAME', 'forge'),
                'password' => env('DB_PASSWORD', ''),
                'driver' => env('DB_CONNECTION', 'mysql'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
        ],
        'encryption' => [
            'enabled' => true,
            'key_rotation_days' => 90,
        ],
        'caching' => [
            'enabled' => true,
            'ttl' => 3600, // seconds
            'store' => 'default',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant migrations.
    |
    */
    'migrations' => [
        /*
        |--------------------------------------------------------------------------
        | Auto Run Migrations
        |--------------------------------------------------------------------------
        |
        | Whether to automatically run migrations when creating a tenant.
        |
        */
        'auto_run' => env('TENANCY_AUTO_MIGRATE', true),

        /*
        |--------------------------------------------------------------------------
        | Migration Paths
        |--------------------------------------------------------------------------
        |
        | Additional paths to search for tenant migrations.
        |
        */
        'paths' => [
            database_path('migrations/tenant'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings for tenant management.
    |
    */
    'security' => [
        'encryption_key' => env('TENANCY_ENCRYPTION_KEY'),
        'credential_rotation_days' => env('TENANCY_CREDENTIAL_ROTATION_DAYS', 90),
        'max_login_attempts' => env('TENANCY_MAX_LOGIN_ATTEMPTS', 5),
        'password_requirements' => [
            'min_length' => env('TENANCY_PASSWORD_MIN_LENGTH', 8),
            'require_uppercase' => env('TENANCY_PASSWORD_REQUIRE_UPPERCASE', true),
            'require_lowercase' => env('TENANCY_PASSWORD_REQUIRE_LOWERCASE', true),
            'require_numbers' => env('TENANCY_PASSWORD_REQUIRE_NUMBERS', true),
            'require_symbols' => env('TENANCY_PASSWORD_REQUIRE_SYMBOLS', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Management
    |--------------------------------------------------------------------------
    |
    | Advanced connection management settings.
    |
    */
    'connections' => [
        'credential_profiles' => [
            'default' => [
                'host' => env('TENANCY_DEFAULT_HOST', 'localhost'),
                'port' => env('TENANCY_DEFAULT_PORT', 3306),
                'username' => env('TENANCY_DEFAULT_USERNAME', 'root'),
                'password' => env('TENANCY_DEFAULT_PASSWORD', ''),
            ],
        ],
        'encryption' => [
            'enabled' => env('TENANCY_ENCRYPT_CREDENTIALS', true),
            'algorithm' => env('TENANCY_ENCRYPTION_ALGORITHM', 'AES-256-CBC'),
        ],
        'pool' => [
            'enabled' => env('TENANCY_CONNECTION_POOL_ENABLED', true),
            'max_size' => env('TENANCY_CONNECTION_POOL_MAX_SIZE', 20),
            'min_size' => env('TENANCY_CONNECTION_POOL_MIN_SIZE', 5),
            'idle_timeout' => env('TENANCY_CONNECTION_IDLE_TIMEOUT', 300),
        ],
    ],
];