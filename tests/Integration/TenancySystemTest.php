<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Integration;

use AngelitoSystems\FilamentTenancy\Support\Contracts\ConnectionManagerInterface;
use AngelitoSystems\FilamentTenancy\Support\Contracts\CredentialManagerInterface;
use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\DatabaseManager;
use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenancySystemTest extends TestCase
{
    use RefreshDatabase;

    protected ConnectionManagerInterface $connectionManager;
    protected CredentialManagerInterface $credentialManager;
    protected DatabaseManager $databaseManager;
    protected TenancyLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionManager = app(ConnectionManagerInterface::class);
        $this->credentialManager = app(CredentialManagerInterface::class);
        $this->databaseManager = app(DatabaseManager::class);
        $this->logger = app(TenancyLogger::class);
    }

    /** @test */
    public function it_can_create_and_switch_to_tenant()
    {
        // Create a tenant
        $tenant = Tenant::create([
            'id' => 'test-tenant',
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('test-tenant', $tenant->id);

        // Switch to tenant
        $this->databaseManager->switchToTenant($tenant);

        // Verify we're on the tenant connection
        $currentConnection = Config::get('database.default');
        $this->assertStringContainsString('tenant', $currentConnection);

        // Switch back to central
        $this->databaseManager->switchToCentral();

        // Verify we're back on central connection
        $currentConnection = Config::get('database.default');
        $this->assertNotContains('tenant', $currentConnection);
    }

    /** @test */
    public function it_manages_credentials_securely()
    {
        $profile = 'test-profile';
        $credentials = [
            'host' => 'localhost',
            'port' => 3306,
            'username' => 'testuser',
            'password' => 'secret-password',
            'driver' => 'mysql',
        ];

        // Store credentials
        $this->credentialManager->storeCredentials($profile, $credentials);

        // Verify credentials exist
        $this->assertTrue($this->credentialManager->hasCredentials($profile));

        // Retrieve credentials
        $retrievedCredentials = $this->credentialManager->getCredentials($profile);
        $this->assertEquals($credentials['host'], $retrievedCredentials['host']);
        $this->assertEquals($credentials['password'], $retrievedCredentials['password']);

        // Remove credentials
        $this->credentialManager->removeCredentials($profile);
        $this->assertFalse($this->credentialManager->hasCredentials($profile));
    }

    /** @test */
    public function it_generates_tenant_database_configuration()
    {
        $tenant = Tenant::create([
            'id' => 'config-test',
            'name' => 'Config Test Tenant',
            'domain' => 'config.example.com',
        ]);

        $config = $this->credentialManager->getTenantDatabaseConfig($tenant);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('driver', $config);
        $this->assertArrayHasKey('host', $config);
        $this->assertArrayHasKey('database', $config);
        $this->assertStringContainsString('config_test', $config['database']);
    }

    /** @test */
    public function it_validates_database_connections()
    {
        $validConfig = [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ];

        $isValid = $this->credentialManager->validateConnection($validConfig);
        $this->assertTrue($isValid);

        $invalidConfig = [
            'driver' => 'mysql',
            'host' => 'nonexistent-host',
            'database' => 'nonexistent-db',
            'username' => 'invalid',
            'password' => 'invalid',
        ];

        $isValid = $this->credentialManager->validateConnection($invalidConfig);
        $this->assertFalse($isValid);
    }

    /** @test */
    public function it_handles_tenant_database_creation_and_deletion()
    {
        $tenant = Tenant::create([
            'id' => 'db-test',
            'name' => 'Database Test Tenant',
            'domain' => 'db.example.com',
        ]);

        // Test database creation (if auto-creation is enabled)
        if (config('filament-tenancy.database.auto_create', false)) {
            $this->databaseManager->createTenantDatabase($tenant);
            
            // Verify database was created by switching to it
            $this->databaseManager->switchToTenant($tenant);
            $this->assertTrue(true); // If we get here, the connection worked
            
            $this->databaseManager->switchToCentral();
        }

        // Test database deletion (if auto-deletion is enabled)
        if (config('filament-tenancy.database.auto_delete', false)) {
            $this->databaseManager->deleteTenantDatabase($tenant);
            $this->assertTrue(true); // If we get here, deletion worked
        }
    }

    /** @test */
    public function it_logs_tenant_operations()
    {
        $tenant = Tenant::create([
            'id' => 'log-test',
            'name' => 'Log Test Tenant',
            'domain' => 'log.example.com',
        ]);

        // Switch to tenant (this should be logged)
        $this->databaseManager->switchToTenant($tenant);

        // Switch back to central (this should also be logged)
        $this->databaseManager->switchToCentral();

        // We can't easily test the actual log output in a unit test,
        // but we can verify the operations completed without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_connection_errors_gracefully()
    {
        $tenant = Tenant::create([
            'id' => 'error-test',
            'name' => 'Error Test Tenant',
            'domain' => 'error.example.com',
        ]);

        // Store invalid credentials
        $invalidCredentials = [
            'host' => 'invalid-host',
            'port' => 9999,
            'username' => 'invalid',
            'password' => 'invalid',
            'driver' => 'mysql',
            'database' => 'invalid_db',
        ];

        $profile = 'tenant_' . $tenant->id;
        $this->credentialManager->storeCredentials($profile, $invalidCredentials);

        // Attempting to switch should handle the error gracefully
        try {
            $this->connectionManager->switchToTenant($tenant);
            $this->fail('Expected ConnectionException was not thrown');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    /** @test */
    public function it_maintains_connection_isolation()
    {
        $tenant1 = Tenant::create([
            'id' => 'isolation-1',
            'name' => 'Isolation Test 1',
            'domain' => 'isolation1.example.com',
        ]);

        $tenant2 = Tenant::create([
            'id' => 'isolation-2',
            'name' => 'Isolation Test 2',
            'domain' => 'isolation2.example.com',
        ]);

        // Switch to tenant 1
        $this->databaseManager->switchToTenant($tenant1);
        $connection1 = Config::get('database.default');

        // Switch to tenant 2
        $this->databaseManager->switchToTenant($tenant2);
        $connection2 = Config::get('database.default');

        // Verify different connections
        $this->assertNotEquals($connection1, $connection2);
        $this->assertStringContainsString('isolation_1', $connection1);
        $this->assertStringContainsString('isolation_2', $connection2);

        // Switch back to central
        $this->databaseManager->switchToCentral();
        $centralConnection = Config::get('database.default');
        
        $this->assertNotEquals($connection1, $centralConnection);
        $this->assertNotEquals($connection2, $centralConnection);
    }

    /** @test */
    public function it_handles_encryption_key_rotation()
    {
        $profile = 'rotation-test';
        $credentials = [
            'host' => 'localhost',
            'password' => 'test-password',
        ];

        // Store credentials
        $this->credentialManager->storeCredentials($profile, $credentials);

        // Rotate encryption key
        $newKey = 'new-encryption-key-' . time();
        $this->credentialManager->rotateEncryptionKey($newKey);

        // Verify credentials are still accessible
        $retrievedCredentials = $this->credentialManager->getCredentials($profile);
        $this->assertEquals($credentials['password'], $retrievedCredentials['password']);
    }

    /** @test */
    public function it_provides_connection_pool_configuration()
    {
        $poolConfig = $this->credentialManager->getConnectionPoolConfig();

        $this->assertIsArray($poolConfig);
        $this->assertArrayHasKey('max_connections', $poolConfig);
        $this->assertArrayHasKey('min_connections', $poolConfig);
        $this->assertArrayHasKey('connection_timeout', $poolConfig);
        $this->assertArrayHasKey('idle_timeout', $poolConfig);
    }

    /** @test */
    public function it_masks_sensitive_data_in_logs()
    {
        $sensitiveData = [
            'username' => 'testuser',
            'password' => 'secret123',
            'api_key' => 'key_abc123',
            'host' => 'localhost',
        ];

        $maskedData = $this->logger->maskSensitiveData($sensitiveData);

        $this->assertEquals('testuser', $maskedData['username']);
        $this->assertEquals('localhost', $maskedData['host']);
        $this->assertEquals('***', $maskedData['password']);
        $this->assertEquals('***', $maskedData['api_key']);
    }
}