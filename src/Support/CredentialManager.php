<?php

namespace AngelitoSystems\FilamentTenancy\Support;

use AngelitoSystems\FilamentTenancy\Support\Contracts\CredentialManagerInterface;
use AngelitoSystems\FilamentTenancy\Support\Exceptions\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CredentialManager implements CredentialManagerInterface
{
    protected string $encryptionKey;
    protected array $credentialCache = [];
    protected array $connectionPools = [];
    protected TenancyLogger $logger;

    public function __construct(?TenancyLogger $logger = null)
    {
        $appKey = config('app.key') ?: 'base64:'.base64_encode('default-test-key-32-characters');
        $this->encryptionKey = $this->parseEncryptionKey($appKey);
        $this->logger = $logger ?? app(TenancyLogger::class);
    }
    
    /**
     * Parse encryption key from various formats.
     */
    protected function parseEncryptionKey(string $key): string
    {
        // Handle base64 encoded keys
        if (Str::startsWith($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }
        
        return $key;
    }

    /**
     * Load credentials from secure storage.
     */
    protected function loadCredentials(): void
    {
        $this->credentialCache = config('filament-tenancy.credential_vault', []);
    }

    /**
     * Get database credentials for a specific profile.
     */
    public function getCredentials(string $profile = 'default'): array
    {
        $credentials = $this->credentialCache[$profile] ?? $this->credentialCache['default'] ?? [];

        return [
            'host' => $credentials['host'] ?? env('TENANT_DB_HOST', '127.0.0.1'),
            'port' => $credentials['port'] ?? env('TENANT_DB_PORT', 3306),
            'username' => $credentials['username'] ?? env('TENANT_DB_USERNAME', 'root'),
            'password' => $this->decryptCredential($credentials['password'] ?? env('TENANT_DB_PASSWORD', '')),
            'driver' => $credentials['driver'] ?? env('TENANT_DB_DRIVER', 'mysql'),
            'charset' => $credentials['charset'] ?? 'utf8mb4',
            'collation' => $credentials['collation'] ?? 'utf8mb4_unicode_ci',
            'options' => $credentials['options'] ?? [],
        ];
    }

    /**
     * Store encrypted credentials for a profile.
     */
    public function storeCredentials(string $profile, array $credentials): void
    {
        try {
            // Encrypt sensitive data
            if (isset($credentials['password'])) {
                $credentials['password'] = $this->encryptCredential($credentials['password']);
            }

            $this->credentialCache[$profile] = $credentials;
            
            $this->logger->logCredentialOperation('stored', null, [
                'profile' => $profile,
                'host' => $credentials['host'] ?? null,
                'driver' => $credentials['driver'] ?? null,
            ]);
        } catch (\Exception $e) {
            $this->logCredentialError('store_failed', $profile, $e);
            throw $e;
        }
    }

    /**
     * Remove credentials for a profile.
     */
    public function removeCredentials(string $profile): void
    {
        if (isset($this->credentialCache[$profile])) {
            unset($this->credentialCache[$profile]);
            $this->logger->logCredentialOperation('removed', null, [
                'profile' => $profile,
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Check if credentials exist for a profile.
     */
    public function hasCredentials(string $profile): bool
    {
        return isset($this->credentialCache[$profile]);
    }

    /**
     * Get available credential profiles.
     */
    public function getAvailableProfiles(): array
    {
        return array_keys($this->credentialCache);
    }

    /**
     * Validate credentials by testing connection.
     */
    public function validateCredentials(string $profile): bool
    {
        try {
            $credentials = $this->getCredentials($profile);
            
            // Create temporary PDO connection to test
            $dsn = $this->buildDsn($credentials);
            $pdo = new \PDO(
                $dsn,
                $credentials['username'],
                $credentials['password'],
                $credentials['options'] ?? []
            );
            
            $pdo = null; // Close connection
            
            $this->logCredentialActivity('validated', $profile);
            return true;
            
        } catch (\Exception $e) {
            $this->logCredentialError('validation_failed', $profile, $e);
            return false;
        }
    }

    /**
     * Build DSN string for database connection.
     */
    protected function buildDsn(array $credentials): string
    {
        $driver = $credentials['driver'] ?? 'mysql';
        $host = $credentials['host'] ?? '127.0.0.1';
        $port = $credentials['port'] ?? 3306;
        $charset = $credentials['charset'] ?? 'utf8mb4';

        switch ($driver) {
            case 'mysql':
                return "mysql:host={$host};port={$port};charset={$charset}";
            case 'pgsql':
                return "pgsql:host={$host};port={$port}";
            case 'sqlite':
                return "sqlite:{$host}";
            case 'sqlsrv':
                return "sqlsrv:Server={$host},{$port}";
            default:
                throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Encrypt credential data.
     */
    protected function encryptCredential(string $value): string
    {
        if (empty($value)) {
            return $value;
        }

        try {
            // Use the custom encryption key if available
            $encrypter = new \Illuminate\Encryption\Encrypter($this->encryptionKey, config('app.cipher', 'AES-256-CBC'));
            return 'encrypted:' . $encrypter->encryptString($value);
        } catch (\Exception $e) {
            Log::warning('Failed to encrypt credential', ['error' => $e->getMessage()]);
            return $value;
        }
    }

    /**
     * Decrypt credential data.
     */
    protected function decryptCredential(string $value): string
    {
        if (empty($value) || !Str::startsWith($value, 'encrypted:')) {
            return $value;
        }

        try {
            // Use the custom encryption key if available
            $encrypter = new \Illuminate\Encryption\Encrypter($this->encryptionKey, config('app.cipher', 'AES-256-CBC'));
            return $encrypter->decryptString(Str::after($value, 'encrypted:'));
        } catch (\Exception $e) {
            Log::warning('Failed to decrypt credential', ['error' => $e->getMessage()]);
            return $value;
        }
    }

    /**
     * Rotate encryption keys for all stored credentials.
     */
    public function rotateEncryptionKeys(): void
    {
        foreach ($this->credentialCache as $profile => $credentials) {
            if (isset($credentials['password']) && Str::startsWith($credentials['password'], 'encrypted:')) {
                // Decrypt with old key and re-encrypt with new key
                $decrypted = $this->decryptCredential($credentials['password']);
                $this->credentialCache[$profile]['password'] = $this->encryptCredential($decrypted);
            }
        }

        $this->logCredentialActivity('keys_rotated', 'all');
    }

    /**
     * Generate secure database name for tenant.
     */
    public function generateSecureDatabaseName($tenantSlugOrTenant, int $tenantId = null): string
    {
        // Handle both Tenant object and string slug
        if (is_object($tenantSlugOrTenant)) {
            $tenantSlug = $tenantSlugOrTenant->slug ?? Str::slug($tenantSlugOrTenant->id);
            $tenantId = $tenantSlugOrTenant->id;
        } else {
            $tenantSlug = $tenantSlugOrTenant;
        }
        
        $prefix = config('filament-tenancy.database.prefix', 'tenant_');
        $useIdSuffix = config('filament-tenancy.database.use_id_suffix', true);
        $useHashSuffix = config('filament-tenancy.database.use_hash_suffix', false);
        
        $name = $prefix . str_replace('-', '_', Str::slug($tenantSlug));
        
        if ($useIdSuffix && $tenantId) {
            $name .= "_{$tenantId}";
        }
        
        if ($useHashSuffix) {
            $hash = substr(hash('sha256', $tenantSlug . $tenantId . $this->encryptionKey), 0, 8);
            $name .= "_{$hash}";
        }
        
        return $name;
    }

    /**
     * Get connection pool configuration.
     */
    public function getConnectionPoolConfig(): array
    {
        return config('filament-tenancy.connection_pool', [
            'max_connections' => 100,
            'min_connections' => 5,
            'connection_timeout' => 30,
            'idle_timeout' => 300, // 5 minutes
            'max_lifetime' => 3600, // 1 hour
            'health_check_interval' => 60, // 1 minute
        ]);
    }

    /**
     * Log credential activity.
     */
    protected function logCredentialActivity(string $action, string $profile): void
    {
        if (!config('filament-tenancy.logging.enabled', true)) {
            return;
        }

        $context = [
            'action' => $action,
            'profile' => $profile,
            'timestamp' => now()->toISOString(),
        ];

        Log::channel(config('filament-tenancy.logging.channel', 'default'))
           ->info("Credential {$action}", $context);
    }

    /**
     * Log credential errors.
     */
    protected function logCredentialError(string $action, string $profile, \Exception $exception): void
    {
        $context = [
            'action' => $action,
            'profile' => $profile,
            'error' => $exception->getMessage(),
            'timestamp' => now()->toISOString(),
        ];

        Log::channel(config('filament-tenancy.logging.channel', 'default'))
           ->error("Credential {$action}", $context);
    }

    /**
     * Clear all credentials from memory.
     */
    public function clearCredentials(): void
    {
        // Log each profile being cleared
        foreach (array_keys($this->credentialCache) as $profile) {
            $this->logger->logCredentialOperation('cleared', null, [
                'profile' => $profile,
                'timestamp' => now()->toISOString(),
            ]);
        }
        
        $this->credentialCache = [];
    }

    /**
     * Get masked credentials for display purposes.
     */
    public function getMaskedCredentials(string $profile): array
    {
        $credentials = $this->credentialCache[$profile] ?? [];
        
        if (isset($credentials['password'])) {
            $credentials['password'] = '***masked***';
        }
        
        return $credentials;
    }

    /**
     * Validate connection using provided configuration.
     */
    public function validateConnection(array $config): bool
    {
        try {
            return $this->testDatabaseConnection($config);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test database connection (can be overridden for testing).
     */
    protected function testDatabaseConnection(array $config): bool
    {
        $connection = new \PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            $config['username'],
            $config['password'],
            [\PDO::ATTR_TIMEOUT => 5]
        );
        return true;
    }

    /**
     * Get tenant database configuration.
     */
    public function getTenantDatabaseConfig($tenant): array
    {
        $profile = $tenant->plan ?? 'default';
        $credentials = $this->getCredentials($profile);
        
        return [
            'driver' => 'mysql',
            'host' => $credentials['host'] ?? 'localhost',
            'port' => $credentials['port'] ?? 3306,
            'database' => $this->generateSecureDatabaseName($tenant->slug ?? 'tenant', $tenant->id),
            'username' => $credentials['username'] ?? 'root',
            'password' => $credentials['password'] ?? '',
        ];
    }

    /**
     * Mask sensitive credentials for display.
     */
    public function maskCredentials(array $credentials): array
    {
        $masked = $credentials;
        $sensitiveKeys = ['password', 'api_key', 'secret', 'token'];
        
        foreach ($sensitiveKeys as $key) {
            if (isset($masked[$key])) {
                $masked[$key] = '***';
            }
        }
        
        return $masked;
    }

    /**
     * Rotate encryption key.
     */
    public function rotateEncryptionKey(string $newKey): void
    {
        $oldKey = $this->encryptionKey;
        $parsedNewKey = $this->parseEncryptionKey($newKey);
        
        // Re-encrypt all stored credentials with new key
        foreach ($this->credentialCache as $profile => $credentials) {
            if (isset($credentials['password'])) {
                // Decrypt with old key
                $encryptedValue = $credentials['password'];
                if (Str::startsWith($encryptedValue, 'encrypted:')) {
                    try {
                        $oldEncrypter = new \Illuminate\Encryption\Encrypter($oldKey, config('app.cipher', 'AES-256-CBC'));
                        $decrypted = $oldEncrypter->decryptString(Str::after($encryptedValue, 'encrypted:'));
                    } catch (\Exception $e) {
                        Log::warning('Failed to decrypt credential during key rotation', ['error' => $e->getMessage()]);
                        continue;
                    }
                } else {
                    $decrypted = $encryptedValue; // Not encrypted
                }
                
                // Update encryption key and encrypt with new key
                $this->encryptionKey = $parsedNewKey;
                $credentials['password'] = $this->encryptCredential($decrypted);
                $this->credentialCache[$profile] = $credentials;
                
                // Log the credential operation for this profile
                $this->logger->logCredentialOperation('rotated', null, $credentials);
            }
        }
        
        // Ensure the new key is set
        $this->encryptionKey = $parsedNewKey;
        
        // Log the security event
        $this->logger->logSecurityEvent('encryption_key_rotated', [
            'profiles_affected' => array_keys($this->credentialCache),
            'timestamp' => now(),
        ]);
    }

    /**
     * Decrypt password using specific key.
     */
    protected function decryptPassword(string $encryptedPassword, string $key = null): string
    {
        $key = $key ?? $this->encryptionKey;
        
        try {
            // Create a temporary encrypter with the specific key
            $encrypter = new \Illuminate\Encryption\Encrypter($key, config('app.cipher', 'AES-256-CBC'));
            return $encrypter->decrypt($encryptedPassword);
        } catch (\Exception $e) {
            return $encryptedPassword; // Return as-is if decryption fails
        }
    }
}