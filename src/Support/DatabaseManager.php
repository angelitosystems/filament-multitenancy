<?php

namespace AngelitoSystems\FilamentTenancy\Support;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\Contracts\ConnectionManagerInterface;
use Illuminate\Database\DatabaseManager as IlluminateDatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseManager
{
    protected IlluminateDatabaseManager $databaseManager;
    protected ConnectionManagerInterface $connectionManager;
    protected string $originalConnection;

    public function __construct(IlluminateDatabaseManager $databaseManager, ConnectionManagerInterface $connectionManager)
    {
        $this->databaseManager = $databaseManager;
        $this->connectionManager = $connectionManager;
        $this->originalConnection = Config::get('database.default');
    }

    /**
     * Switch to tenant database.
     */
    public function switchToTenant(Tenant $tenant): void
    {
        $this->connectionManager->switchToTenant($tenant);
        
        Log::info('Switched to tenant database', [
            'tenant_id' => $tenant->id,
            'connection' => $this->connectionManager->getTenantConnectionName($tenant),
        ]);
    }

    /**
     * Switch back to central database.
     */
    public function switchToCentral(): void
    {
        $this->connectionManager->switchToCentral();
        
        Log::info('Switched to central database', [
            'connection' => $this->originalConnection,
        ]);
    }

    /**
     * Get tenant connection name.
     */
    public function getTenantConnectionName(Tenant $tenant): string
    {
        return $this->connectionManager->getTenantConnectionName($tenant);
    }

    /**
     * Create tenant database.
     */
    public function createTenantDatabase(Tenant $tenant): bool
    {
        if (! config('filament-tenancy.database.auto_create_tenant_database', true)) {
            return false;
        }

        try {
            $config = $this->connectionManager->getTenantDatabaseConfig($tenant);
            $driver = $config['driver'] ?? env('DB_CONNECTION', 'mysql');
            $databaseName = $config['database'];

            // SQLite doesn't support CREATE DATABASE
            if ($driver === 'sqlite') {
                Log::warning('SQLite does not support multi-database tenancy. Skipping database creation.', [
                    'tenant_id' => $tenant->id,
                ]);
                return false;
            }
            
            // Create temporary connection to create database
            $tempConnectionName = 'temp_tenant_creation';
            $tempConfig = array_merge($config, [
                'database' => null, // Connect without specifying database
            ]);
            
            Config::set("database.connections.{$tempConnectionName}", $tempConfig);
            
            // Create database based on driver
            if ($driver === 'pgsql') {
                // PostgreSQL: Use CREATE DATABASE
                DB::connection($tempConnectionName)
                    ->statement("CREATE DATABASE \"{$databaseName}\"");
            } else {
                // MySQL: Use CREATE DATABASE with charset and collation
            DB::connection($tempConnectionName)
                ->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            Log::info('Created tenant database', [
                'tenant_id' => $tenant->id,
                'database' => $databaseName,
                'driver' => $driver,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create tenant database: {$e->getMessage()}", [
                'tenant_id' => $tenant->id,
            ]);
            return false;
        }
    }

    /**
     * Delete tenant database.
     */
    public function deleteTenantDatabase(Tenant $tenant): bool
    {
        if (! config('filament-tenancy.database.auto_delete_tenant_database', false)) {
            return false;
        }

        try {
            $config = $this->connectionManager->getTenantDatabaseConfig($tenant);
            $databaseName = $config['database'];
            
            // Close tenant connection first
            $this->connectionManager->closeTenantConnection($tenant);
            
            // Create temporary connection to delete database
            $tempConnectionName = 'temp_tenant_deletion';
            $tempConfig = array_merge($config, [
                'database' => null, // Connect without specifying database
            ]);
            
            Config::set("database.connections.{$tempConnectionName}", $tempConfig);
            
            // Delete database
            DB::connection($tempConnectionName)
                ->statement("DROP DATABASE IF EXISTS `{$databaseName}`");

            Log::info('Deleted tenant database', [
                'tenant_id' => $tenant->id,
                'database' => $databaseName,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete tenant database: {$e->getMessage()}", [
                'tenant_id' => $tenant->id,
            ]);
            return false;
        }
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

    public function tenantDatabaseExists(Tenant $tenant): bool
    {
        try {
            $databaseName = $tenant->database_name;
            $template = $this->getTenantConnectionTemplate();
            $driver = $template['driver'] ?? env('DB_CONNECTION', 'mysql');
            
            // SQLite doesn't use schemas, check file existence instead
            if ($driver === 'sqlite') {
                $databasePath = $template['database'] ?? database_path('database.sqlite');
                return file_exists($databasePath);
            }
            
            $tempConnectionName = 'temp_tenant_check';
            $tempConfig = array_merge($template, [
                'database' => null,
            ]);
            
            Config::set("database.connections.{$tempConnectionName}", $tempConfig);
            
            // Use appropriate query based on driver
            if ($driver === 'pgsql') {
                $result = DB::connection($tempConnectionName)
                    ->select("SELECT datname FROM pg_database WHERE datname = ?", [$databaseName]);
            } else {
            $result = DB::connection($tempConnectionName)
                ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
            }

            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Run migrations for tenant.
     */
    public function runTenantMigrations(Tenant $tenant): bool
    {
        try {
            $driver = env('DB_CONNECTION', 'mysql');
            
            // SQLite doesn't support multi-database tenancy
            if ($driver === 'sqlite') {
                Log::warning('SQLite does not support multi-database tenancy. Skipping tenant migrations.', [
                    'tenant_id' => $tenant->id,
                ]);
                return false;
            }

            // Ensure database exists before switching
            if (!$this->tenantDatabaseExists($tenant)) {
                if (!$this->createTenantDatabase($tenant)) {
                    throw new \Exception('Failed to create tenant database');
                }
            }

            $this->switchToTenant($tenant);

            // Run migrations
            Artisan::call('migrate', [
                '--database' => $this->getTenantConnectionName($tenant),
                '--path' => config('filament-tenancy.migrations.paths', []),
                '--force' => true,
            ]);

            $this->switchToCentral();

            return true;
        } catch (\Exception $e) {
            $this->switchToCentral();
            Log::error("Failed to run tenant migrations: {$e->getMessage()}", [
                'tenant_id' => $tenant->id,
            ]);
            return false;
        }
    }

    /**
     * Get current tenant connection name.
     */
    public function getCurrentTenantConnection(): ?string
    {
        $currentConnection = $this->databaseManager->getDefaultConnection();
        
        if (str_starts_with($currentConnection, 'tenant_')) {
            return $currentConnection;
        }

        return null;
    }

    /**
     * Check if currently using tenant connection.
     */
    public function isUsingTenantConnection(): bool
    {
        return $this->getCurrentTenantConnection() !== null;
    }

    /**
     * Get tenant from current connection.
     */
    public function getTenantFromCurrentConnection(): ?Tenant
    {
        $connectionName = $this->getCurrentTenantConnection();
        
        if (! $connectionName) {
            return null;
        }

        // Extract tenant ID from connection name (tenant_{id})
        $tenantId = str_replace('tenant_', '', $connectionName);
        
        if (! is_numeric($tenantId)) {
            return null;
        }

        // Switch to central connection to query tenant
        $originalConnection = $this->databaseManager->getDefaultConnection();
        $this->switchToCentral();
        
        $tenant = Tenant::find($tenantId);
        
        // Switch back to original connection
        $this->databaseManager->setDefaultConnection($originalConnection);
        
        return $tenant;
    }
}