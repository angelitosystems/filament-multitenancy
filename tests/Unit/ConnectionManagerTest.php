<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\ConnectionManager;
use AngelitoSystems\FilamentTenancy\Support\CredentialManager;
use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConnectionManagerTest extends TestCase
{
    use RefreshDatabase;

    protected ConnectionManager $connectionManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a simple mock for testing
        $credentialManager = $this->createMock(CredentialManager::class);
        $logger = $this->createMock(TenancyLogger::class);
        
        $this->connectionManager = new ConnectionManager($credentialManager, $logger);
    }

    /** @test */
    public function it_can_instantiate_connection_manager()
    {
        $this->assertInstanceOf(ConnectionManager::class, $this->connectionManager);
    }

    /** @test */
    public function it_can_get_tenant_connection_name()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
        
        $connectionName = $this->connectionManager->getTenantConnectionName($tenant);
        
        $this->assertIsString($connectionName);
        $this->assertStringContainsString('tenant_', $connectionName);
        $this->assertStringContainsString((string)$tenant->id, $connectionName);
    }

    /** @test */
    public function it_can_get_tenant_database_config()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'database_name' => 'tenant_test_db',
            'database_host' => 'localhost',
            'database_username' => 'tenant_user',
            'database_password' => 'tenant_pass',
        ]);

        $config = $this->connectionManager->getTenantDatabaseConfig($tenant);
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('database', $config);
        $this->assertArrayHasKey('driver', $config);
    }

    /** @test */
    public function it_can_switch_to_central()
    {
        $this->connectionManager->switchToCentral();
        
        // Verify that switchToCentral doesn't throw exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_throws_exception_when_tenant_has_no_id()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->expectException(\AngelitoSystems\FilamentTenancy\Support\Exceptions\ConnectionException::class);

        $this->connectionManager->switchToTenant($tenant);
    }

    /** @test */
    public function it_throws_exception_for_sqlite_when_switching_to_tenant()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        \Illuminate\Support\Facades\Config::set('database.default', 'sqlite');

        $this->expectException(\AngelitoSystems\FilamentTenancy\Support\Exceptions\ConnectionException::class);
        $this->expectExceptionMessage('SQLite does not support multi-database tenancy');

        $this->connectionManager->switchToTenant($tenant);
    }
}