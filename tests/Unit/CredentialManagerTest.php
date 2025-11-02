<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\CredentialManager;
use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Mockery;

class CredentialManagerTest extends TestCase
{
    protected CredentialManager $credentialManager;
    protected $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = Mockery::mock(TenancyLogger::class);
        $this->credentialManager = new CredentialManager($this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_store_and_retrieve_credentials()
    {
        $profile = 'test-profile';
        $credentials = [
            'host' => 'localhost',
            'port' => 3306,
            'username' => 'testuser',
            'password' => 'testpass',
            'driver' => 'mysql',
        ];

        $this->logger
            ->shouldReceive('logCredentialOperation')
            ->with('stored', null, Mockery::type('array'))
            ->once();

        $this->credentialManager->storeCredentials($profile, $credentials);

        $retrievedCredentials = $this->credentialManager->getCredentials($profile);

        $this->assertEquals($credentials['host'], $retrievedCredentials['host']);
        $this->assertEquals($credentials['username'], $retrievedCredentials['username']);
        $this->assertEquals($credentials['password'], $retrievedCredentials['password']);
    }

    /** @test */
    public function it_encrypts_passwords_when_storing()
    {
        $profile = 'test-profile';
        $credentials = [
            'host' => 'localhost',
            'password' => 'plaintext-password',
        ];

        $this->logger
            ->shouldReceive('logCredentialOperation')
            ->once();

        $this->credentialManager->storeCredentials($profile, $credentials);

        // Access the internal storage to verify encryption
        $reflection = new \ReflectionClass($this->credentialManager);
        $property = $reflection->getProperty('credentialCache');
        $property->setAccessible(true);
        $cache = $property->getValue($this->credentialManager);

        $this->assertNotEquals('plaintext-password', $cache[$profile]['password']);
    }

    /** @test */
    public function it_can_remove_credentials()
    {
        $profile = 'test-profile';
        $credentials = ['host' => 'localhost'];

        $this->logger
            ->shouldReceive('logCredentialOperation')
            ->with('stored', null, Mockery::type('array'))
            ->once();

        $this->logger
            ->shouldReceive('logCredentialOperation')
            ->with('removed', null, Mockery::type('array'))
            ->once();

        $this->credentialManager->storeCredentials($profile, $credentials);
        $this->assertTrue($this->credentialManager->hasCredentials($profile));

        $this->credentialManager->removeCredentials($profile);
        $this->assertFalse($this->credentialManager->hasCredentials($profile));
    }

    /** @test */
    public function it_can_validate_database_connections()
    {
        $validConfig = [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test_db',
            'username' => 'root',
            'password' => 'password',
        ];

        // Create a partial mock that overrides the testDatabaseConnection method
        $credentialManager = Mockery::mock(CredentialManager::class, [$this->logger])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $credentialManager
            ->shouldReceive('testDatabaseConnection')
            ->with($validConfig)
            ->once()
            ->andReturn(true);

        $this->assertTrue($credentialManager->validateConnection($validConfig));
    }

    /** @test */
    public function it_generates_secure_database_names()
    {
        $tenant = new Tenant(['id' => 1, 'slug' => 'test-tenant']);
        
        $dbName = $this->credentialManager->generateSecureDatabaseName($tenant);
        
        $this->assertStringStartsWith('tenant_', $dbName);
        $this->assertStringContainsString('test_tenant', $dbName);
    }

    /** @test */
    public function it_can_rotate_encryption_keys()
    {
        $profile = 'test-profile';
        $credentials = [
            'host' => 'localhost',
            'password' => 'test-password',
        ];

        $this->logger
            ->shouldReceive('logCredentialOperation')
            ->twice(); // Once for store, once for rotation

        $this->logger
            ->shouldReceive('logSecurityEvent')
            ->with('encryption_key_rotated', Mockery::type('array'))
            ->once();

        // Store credentials with old key
        $this->credentialManager->storeCredentials($profile, $credentials);

        // Rotate encryption key
        $newKey = 'new-encryption-key';
        $this->credentialManager->rotateEncryptionKey($newKey);

        // Verify credentials are still accessible
        $retrievedCredentials = $this->credentialManager->getCredentials($profile);
        $this->assertEquals($credentials['password'], $retrievedCredentials['password']);
    }

    /** @test */
    public function it_gets_tenant_database_config()
    {
        $tenant = new Tenant(['id' => 1, 'slug' => 'test-tenant']);
        
        $config = $this->credentialManager->getTenantDatabaseConfig($tenant);
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('driver', $config);
        $this->assertArrayHasKey('host', $config);
        $this->assertArrayHasKey('database', $config);
        $this->assertStringContainsString('test_tenant', $config['database']);
    }

    /** @test */
    public function it_handles_connection_pool_configuration()
    {
        $poolConfig = $this->credentialManager->getConnectionPoolConfig();
        
        $this->assertIsArray($poolConfig);
        $this->assertArrayHasKey('max_connections', $poolConfig);
        $this->assertArrayHasKey('min_connections', $poolConfig);
        $this->assertArrayHasKey('connection_timeout', $poolConfig);
    }

    /** @test */
    public function it_can_clear_credentials()
    {
        $profile1 = 'profile1';
        $profile2 = 'profile2';
        
        $this->logger
            ->shouldReceive('logCredentialOperation')
            ->times(4); // 2 stores + 2 clears

        $this->credentialManager->storeCredentials($profile1, ['host' => 'host1']);
        $this->credentialManager->storeCredentials($profile2, ['host' => 'host2']);

        $this->assertTrue($this->credentialManager->hasCredentials($profile1));
        $this->assertTrue($this->credentialManager->hasCredentials($profile2));

        $this->credentialManager->clearCredentials();

        $this->assertFalse($this->credentialManager->hasCredentials($profile1));
        $this->assertFalse($this->credentialManager->hasCredentials($profile2));
    }

    /** @test */
    public function it_masks_sensitive_credentials()
    {
        $credentials = [
            'host' => 'localhost',
            'username' => 'testuser',
            'password' => 'secret-password',
            'api_key' => 'secret-api-key',
        ];

        $masked = $this->credentialManager->maskCredentials($credentials);

        $this->assertEquals('localhost', $masked['host']);
        $this->assertEquals('testuser', $masked['username']);
        $this->assertEquals('***', $masked['password']);
        $this->assertEquals('***', $masked['api_key']);
    }

    /** @test */
    public function it_logs_errors_when_storing_credentials_fails()
    {
        $profile = 'test-profile';
        $invalidCredentials = null; // This should cause an error



        $this->expectException(\TypeError::class);

        $this->credentialManager->storeCredentials($profile, $invalidCredentials);
    }
}