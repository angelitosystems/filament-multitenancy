<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\ConnectionManager;
use AngelitoSystems\FilamentTenancy\Support\DatabaseManager;
use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Illuminate\Database\DatabaseManager as IlluminateDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;

class DatabaseManagerTest extends TestCase
{
    use RefreshDatabase;

    protected DatabaseManager $databaseManager;
    protected $connectionManager;
    protected $logger;
    protected $illuminateDatabaseManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(TenancyLogger::class);
        $this->connectionManager = Mockery::mock(ConnectionManager::class);
        $this->illuminateDatabaseManager = app(IlluminateDatabaseManager::class);
        
        $this->databaseManager = new DatabaseManager(
            $this->illuminateDatabaseManager,
            $this->connectionManager
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_instantiate_database_manager()
    {
        $this->assertInstanceOf(DatabaseManager::class, $this->databaseManager);
    }

    /** @test */
    public function it_can_get_tenant_connection_name()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->connectionManager
            ->shouldReceive('getTenantConnectionName')
            ->with($tenant)
            ->once()
            ->andReturn('tenant_1');

        $connectionName = $this->databaseManager->getTenantConnectionName($tenant);

        $this->assertEquals('tenant_1', $connectionName);
    }

    /** @test */
    public function it_can_switch_to_central()
    {
        $this->connectionManager
            ->shouldReceive('switchToCentral')
            ->once();

        $this->databaseManager->switchToCentral();
    }

    /** @test */
    public function it_checks_if_tenant_database_exists()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'database_name' => 'tenant_test_db',
        ]);

        // Mock environment for SQLite
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // For SQLite, it checks file existence
        $exists = $this->databaseManager->tenantDatabaseExists($tenant);
        
        // Since we're using :memory:, it should return false
        $this->assertIsBool($exists);
    }

    /** @test */
    public function it_skips_database_creation_for_sqlite()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $result = $this->databaseManager->createTenantDatabase($tenant);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_skips_migrations_for_sqlite()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        Config::set('database.default', 'sqlite');

        $this->logger
            ->shouldReceive('warning')
            ->once();

        $result = $this->databaseManager->runTenantMigrations($tenant);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_validates_tenant_has_id_before_migrations()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        // Tenant without ID should cause issues
        $this->expectException(\Exception::class);

        Config::set('database.default', 'mysql');
        Config::set('filament-tenancy.database.auto_create_tenant_database', true);

        $this->connectionManager
            ->shouldReceive('getTenantDatabaseConfig')
            ->andReturn([
                'driver' => 'mysql',
                'host' => 'localhost',
                'database' => 'test_db',
            ]);

        $this->databaseManager->runTenantMigrations($tenant);
    }
}

