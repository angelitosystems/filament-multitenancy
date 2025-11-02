<?php

namespace AngelitoSystems\FilamentTenancy\Support;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\Contracts\ConnectionManagerInterface;
use AngelitoSystems\FilamentTenancy\Support\Contracts\CredentialManagerInterface;
use AngelitoSystems\FilamentTenancy\Support\Exceptions\ConnectionException;
use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConnectionManager implements ConnectionManagerInterface
{
    protected CredentialManagerInterface $credentialManager;
    protected TenancyLogger $logger;
    protected array $activeConnections = [];
    protected array $connectionPool = [];
    protected string $originalConnection;

    public function __construct(CredentialManagerInterface $credentialManager, TenancyLogger $logger = null)
    {
        $this->credentialManager = $credentialManager;
        $this->logger = $logger ?? app(TenancyLogger::class);
        $this->originalConnection = Config::get('database.default');
    }

    /**
     * Get secure database configuration for a tenant.
     */
    public function getTenantDatabaseConfig(Tenant $tenant): array
    {
        // If tenant doesn't have ID yet, don't cache
        if (!$tenant->id) {
            return $this->buildTenantDatabaseConfig($tenant);
        }
        
        $cacheKey = $this->getCacheKey('config', $tenant->id);
        
        return Cache::remember($cacheKey, config('filament-tenancy.cache.ttl', 3600), function () use ($tenant) {
            return $this->buildTenantDatabaseConfig($tenant);
        });
    }

    /**
     * Get the tenant connection template configuration.
     * Uses Laravel's default database configuration if template is null.
     */
    protected function getTenantConnectionTemplate(): array
    {
        $template = config('filament-tenancy.database.tenants_connection_template');

        // If null, use Laravel's default connection configuration
        if ($template === null) {
            return $this->buildTemplateFromDefaultConnection();
        }

        // Otherwise return configured template
        return is_array($template) ? $template : [];
    }

    /**
     * Build connection template from Laravel's default database configuration.
     */
    protected function buildTemplateFromDefaultConnection(): array
    {
        $driver = env('DB_CONNECTION', 'mysql');
        $defaultConnection = config("database.connections.{$driver}", []);
        
        // If default connection exists, use it as base
        if (!empty($defaultConnection)) {
            $template = $defaultConnection;
            // Remove database name as it will be set per tenant
            unset($template['database']);
            return $template;
        }

        // Otherwise build from env variables
        $template = [
            'driver' => $driver,
            'prefix' => '',
            'prefix_indexes' => true,
        ];

        // Configure based on database driver
        switch ($driver) {
            case 'sqlite':
                $template['database'] = env('DB_DATABASE', database_path('database.sqlite'));
                $template['foreign_key_constraints'] = env('DB_FOREIGN_KEYS', true);
                break;

            case 'pgsql':
                $template['host'] = env('DB_HOST', '127.0.0.1');
                $template['port'] = env('DB_PORT', '5432');
                $template['username'] = env('DB_USERNAME', 'forge');
                $template['password'] = env('DB_PASSWORD', '');
                $template['charset'] = env('DB_CHARSET', 'utf8');
                $template['search_path'] = 'public';
                $template['sslmode'] = 'prefer';
                break;

            case 'mysql':
            default:
                $template['host'] = env('DB_HOST', '127.0.0.1');
                $template['port'] = env('DB_PORT', '3306');
                $template['username'] = env('DB_USERNAME', 'forge');
                $template['password'] = env('DB_PASSWORD', '');
                $template['charset'] = env('DB_CHARSET', 'utf8mb4');
                $template['collation'] = env('DB_COLLATION', 'utf8mb4_unicode_ci');
                $template['strict'] = true;
                $template['engine'] = null;
                $template['options'] = extension_loaded('pdo_mysql') ? array_filter([
                    \PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
                ]) : [];
                break;
        }

        return $template;
    }

    /**
     * Build database configuration for tenant.
     */
    protected function buildTenantDatabaseConfig(Tenant $tenant): array
    {
        $template = $this->getTenantConnectionTemplate();
        $connectionProfile = $this->getConnectionProfile($tenant);

        // Merge template with tenant-specific configuration
        $config = array_merge($template, [
            'database' => $this->generateDatabaseName($tenant),
        ]);

        // Override connection details from profile if provided
        if (isset($connectionProfile['host'])) {
            $config['host'] = $connectionProfile['host'];
        }
        if (isset($connectionProfile['port'])) {
            $config['port'] = $connectionProfile['port'];
        }
        if (isset($connectionProfile['username'])) {
            $config['username'] = $connectionProfile['username'];
        }
        if (isset($connectionProfile['password'])) {
            $config['password'] = $connectionProfile['password'];
        }

        return $config;
    }

    /**
     * Get connection profile for tenant based on plan or configuration.
     */
    protected function getConnectionProfile(Tenant $tenant): array
    {
        $profiles = config('filament-tenancy.connection_management.credential_profiles', []);
        $defaultProfile = $this->getTenantConnectionTemplate();

        // Determine profile based on tenant plan or use default
        $profileName = $tenant->plan ?? 'default';
        $profile = $profiles[$profileName] ?? [];

        // Use Laravel's default DB configuration if not in profile
        return [
            'host' => $profile['host'] ?? $defaultProfile['host'] ?? env('DB_HOST', '127.0.0.1'),
            'port' => $profile['port'] ?? $defaultProfile['port'] ?? env('DB_PORT', $this->getDefaultPort()),
            'username' => $profile['username'] ?? $defaultProfile['username'] ?? env('DB_USERNAME', 'root'),
            'password' => isset($profile['password']) ? $this->decryptPassword($profile['password']) : ($defaultProfile['password'] ?? env('DB_PASSWORD', '')),
        ];
    }

    /**
     * Get default port based on database driver.
     */
    protected function getDefaultPort(): string
    {
        $driver = env('DB_CONNECTION', 'mysql');
        
        return match ($driver) {
            'pgsql' => '5432',
            'sqlite' => '',
            default => '3306',
        };
    }

    /**
     * Generate secure database name for tenant.
     */
    protected function generateDatabaseName(Tenant $tenant): string
    {
        $prefix = config('filament-tenancy.database.prefix', 'tenant_');
        
        // If tenant has database_name set, use it
        if (!empty($tenant->database_name)) {
            return $tenant->database_name;
        }
        
        // Generate database name
        $slug = $tenant->slug ?? Str::slug($tenant->name ?? 'tenant');
        $suffix = '';
        
        // Only add ID suffix if tenant has ID and config allows it
        if ($tenant->id && config('filament-tenancy.database.use_id_suffix', true)) {
            $suffix = "_{$tenant->id}";
        }
        
        return $prefix . Str::slug($slug) . $suffix;
    }

    /**
     * Establish connection to tenant database.
     */
    public function connectToTenant(Tenant $tenant): string
    {
        $connectionName = $this->getTenantConnectionName($tenant);
        
        try {
            // Check if connection already exists and is active
            if ($this->isConnectionActive($connectionName)) {
                $this->logConnection('reused', $tenant, $connectionName);
                return $connectionName;
            }

            // Configure new connection
            $config = $this->getTenantDatabaseConfig($tenant);
            Config::set("database.connections.{$connectionName}", $config);

            // Test connection
            $this->testConnection($connectionName);

            // Store in active connections
            $this->activeConnections[$connectionName] = [
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'last_used' => now(),
            ];

            $this->logConnection('established', $tenant, $connectionName);
            
            return $connectionName;

        } catch (\Exception $e) {
            $this->logConnectionError('failed', $tenant, $connectionName, $e);
            throw new ConnectionException(
                "Failed to connect to tenant database: {$e->getMessage()}",
                0,
                $e,
                $tenant->id,
                $connectionName
            );
        }
    }

    /**
     * Switch to tenant database connection.
     */
    public function switchToTenant(Tenant $tenant): void
    {
        $startTime = microtime(true);
        
        try {
            // Validate tenant has an ID
            if (!$tenant->id) {
                throw new ConnectionException(
                    "Cannot switch to tenant: tenant does not have an ID",
                    0,
                    null,
                    null,
                    'tenant_unknown'
                );
            }

            $driver = env('DB_CONNECTION', 'mysql');
            
            // SQLite doesn't support multi-database tenancy
            if ($driver === 'sqlite') {
                throw new ConnectionException(
                    "SQLite does not support multi-database tenancy. Please use MySQL or PostgreSQL.",
                    0,
                    null,
                    $tenant->id,
                    'sqlite_not_supported'
                );
            }

            $connectionName = $this->getTenantConnectionName($tenant);
            $config = $this->getTenantDatabaseConfig($tenant);
            
            // Ensure database exists (check via DatabaseManager)
            $databaseManager = app(\AngelitoSystems\FilamentTenancy\Support\DatabaseManager::class);
            if (!$databaseManager->tenantDatabaseExists($tenant)) {
                // Try to create it if auto-create is enabled
                if (config('filament-tenancy.database.auto_create_tenant_database', true)) {
                    if (!$databaseManager->createTenantDatabase($tenant)) {
            throw new ConnectionException(
                "Tenant database does not exist and could not be created",
                0,
                null,
                $tenant->id,
                $connectionName
            );
                    }
                } else {
                    throw new ConnectionException(
                        "Tenant database does not exist",
                        0,
                        null,
                        $tenant->id,
                        $connectionName
                    );
                }
            }
            
            // Configure the connection
            Config::set("database.connections.{$connectionName}", $config);
            
            // Set as default connection
            Config::set('database.default', $connectionName);
            
            // Test the connection
            DB::connection($connectionName)->getPdo();
            
            // Track active connection
            $this->connectionPool[$tenant->id] = [
                'connection_name' => $connectionName,
                'tenant_id' => $tenant->id,
                'connected_at' => now(),
                'last_activity' => now(),
            ];
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            $this->logger->logConnection('switched_to_tenant', $tenant, [
                'connection_name' => $connectionName,
                'execution_time_ms' => round($executionTime, 2),
            ]);
            
        } catch (ConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->logConnectionError("Failed to switch to tenant database: {$e->getMessage()}", $tenant);
            
            throw new ConnectionException(
                "Failed to switch to tenant database: {$e->getMessage()}",
                0,
                $e,
                $tenant->id ?? null,
                $this->getTenantConnectionName($tenant)
            );
        }
    }

    /**
     * Switch back to central database connection.
     */
    public function switchToCentral(): void
    {
        Config::set('database.default', $this->originalConnection);
        
        $this->logger->logConnection('switched_to_central', null, [
            'connection_name' => $this->originalConnection,
        ]);
    }

    /**
     * Get tenant connection name.
     */
    public function getTenantConnectionName(Tenant $tenant): string
    {
        if (!$tenant->id) {
            throw new ConnectionException(
                "Cannot get connection name: tenant does not have an ID",
                0,
                null,
                null,
                'tenant_unknown'
            );
        }
        
        return "tenant_{$tenant->id}";
    }

    /**
     * Test database connection.
     */
    protected function testConnection(string $connectionName): void
    {
        DB::connection($connectionName)->getPdo();
    }

    /**
     * Check if connection is active.
     */
    protected function isConnectionActive(string $connectionName): bool
    {
        if (!isset($this->activeConnections[$connectionName])) {
            return false;
        }

        try {
            DB::connection($connectionName)->getPdo();
            return true;
        } catch (\Exception $e) {
            // Remove inactive connection
            unset($this->activeConnections[$connectionName]);
            return false;
        }
    }

    /**
     * Close tenant connection.
     */
    public function closeTenantConnection(Tenant $tenant): void
    {
        $connectionName = $this->getTenantConnectionName($tenant);
        
        if (isset($this->activeConnections[$connectionName])) {
            DB::purge($connectionName);
            unset($this->activeConnections[$connectionName]);
            $this->logConnection('closed', $tenant, $connectionName);
        }
    }

    /**
     * Close all tenant connections.
     */
    public function closeAllTenantConnections(): void
    {
        foreach ($this->activeConnections as $connectionName => $info) {
            DB::purge($connectionName);
            $this->logConnection('closed_all', null, $connectionName);
        }
        
        $this->activeConnections = [];
    }

    /**
     * Get active connections count.
     */
    public function getActiveConnectionsCount(): int
    {
        return count($this->activeConnections);
    }

    /**
     * Get active connections info.
     */
    public function getActiveConnectionsInfo(): array
    {
        return $this->activeConnections;
    }

    /**
     * Decrypt password if encrypted.
     */
    protected function decryptPassword(string $password): string
    {
        if (empty($password)) {
            return $password;
        }

        // Check if password is encrypted (starts with encrypted prefix)
        if (Str::startsWith($password, 'encrypted:')) {
            try {
                return decrypt(Str::after($password, 'encrypted:'));
            } catch (\Exception $e) {
                Log::warning('Failed to decrypt database password', ['error' => $e->getMessage()]);
                return $password;
            }
        }

        return $password;
    }

    /**
     * Get cache key for tenant data.
     */
    protected function getCacheKey(string $type, int $tenantId): string
    {
        $prefix = config('filament-tenancy.cache.prefix', 'tenancy');
        return "{$prefix}:{$type}:{$tenantId}";
    }

    /**
     * Log connection activity.
     */
    protected function logConnection(string $action, ?Tenant $tenant, string $connectionName): void
    {
        if (!config('filament-tenancy.logging.enabled', true)) {
            return;
        }

        $context = [
            'action' => $action,
            'connection' => $connectionName,
            'tenant_id' => $tenant?->id,
            'tenant_slug' => $tenant?->slug,
            'timestamp' => now()->toISOString(),
        ];

        Log::channel(config('filament-tenancy.logging.channel', 'default'))
           ->info("Database connection {$action}", $context);
    }

    /**
     * Log connection errors.
     */
    protected function logConnectionError(string $action, ?Tenant $tenant, string $connectionName, \Exception $exception): void
    {
        $context = [
            'action' => $action,
            'connection' => $connectionName,
            'tenant_id' => $tenant?->id,
            'tenant_slug' => $tenant?->slug,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toISOString(),
        ];

        Log::channel(config('filament-tenancy.logging.channel', 'default'))
           ->error("Database connection {$action}", $context);
    }

    /**
     * Clear tenant cache.
     */
    public function clearTenantCache(Tenant $tenant): void
    {
        $cacheKey = $this->getCacheKey('config', $tenant->id);
        Cache::forget($cacheKey);
    }

    /**
     * Clear all tenant caches.
     */
    public function clearAllTenantCaches(): void
    {
        $prefix = config('filament-tenancy.cache.prefix', 'tenancy');
        Cache::flush(); // In production, you might want to be more selective
    }
}