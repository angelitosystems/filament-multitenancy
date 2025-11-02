<?php

namespace AngelitoSystems\FilamentTenancy\Tests;

use AngelitoSystems\FilamentTenancy\TenancyServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load package configuration
        $this->app['config']->set('filament-tenancy', require __DIR__ . '/../config/filament-tenancy.php');

        // Run migrations for package tables
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (class_exists(Factory::class)) {
            Factory::guessFactoryNamesUsing(
                fn (string $modelName) => 'AngelitoSystems\\FilamentTenancy\\Database\\Factories\\'.class_basename($modelName).'Factory'
            );
        }

        // Mock the DatabaseManager to avoid actual database switching in tests
        $this->app->bind(\AngelitoSystems\FilamentTenancy\Support\DatabaseManager::class, function () {
            return new class extends \AngelitoSystems\FilamentTenancy\Support\DatabaseManager {
                public function __construct() {
                    // Override constructor to avoid database manager dependency
                }
                
                public function switchToTenant($tenant): void {
                    // Mock implementation - do nothing
                }
                
                public function switchToCentral(): void {
                    // Mock implementation - do nothing
                }
                
                public function createTenantDatabase($tenant): bool {
                    return true;
                }
                
                public function deleteTenantDatabase($tenant): bool {
                    return true;
                }
                
                public function runTenantMigrations($tenant): bool {
                    return true;
                }
            };
        });
    }

    protected function getPackageProviders($app)
    {
        return [
            TenancyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Set application key for encryption
        $app['config']->set('app.key', 'base64:'.base64_encode('32-character-secret-key-for-test'));
        
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up filament-tenancy configuration for testing
        $app['config']->set('filament-tenancy.database.auto_create', false);
        $app['config']->set('filament-tenancy.database.auto_delete', false);
        $app['config']->set('filament-tenancy.database.default_connection', 'testing');
        $app['config']->set('filament-tenancy.logging.enabled', true);
        $app['config']->set('filament-tenancy.logging.channel', 'testing');
        $app['config']->set('filament-tenancy.monitoring.enabled', true);
    }

    /**
     * Create a test tenant.
     */
    protected function createTestTenant(array $attributes = []): \AngelitoSystems\FilamentTenancy\Models\Tenant
    {
        return \AngelitoSystems\FilamentTenancy\Models\Tenant::create(array_merge([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
            'domain' => 'test.example.com',
        ], $attributes));
    }

    /**
     * Create a test plan.
     */
    protected function createTestPlan(array $attributes = []): \AngelitoSystems\FilamentTenancy\Models\Plan
    {
        return \AngelitoSystems\FilamentTenancy\Models\Plan::create(array_merge([
            'name' => 'Test Plan',
            'slug' => 'test-plan-' . uniqid(),
            'price' => 10.00,
            'currency' => 'USD',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Create a test subscription.
     */
    protected function createTestSubscription(array $attributes = []): \AngelitoSystems\FilamentTenancy\Models\Subscription
    {
        $tenant = $attributes['tenant_id'] ?? $this->createTestTenant()->id;
        $plan = $attributes['plan_id'] ?? $this->createTestPlan()->id;

        return \AngelitoSystems\FilamentTenancy\Models\Subscription::create(array_merge([
            'tenant_id' => $tenant,
            'plan_id' => $plan,
            'status' => \AngelitoSystems\FilamentTenancy\Models\Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ], $attributes));
    }

    /**
     * Get test database configuration.
     */
    protected function getTestDatabaseConfig(): array
    {
        return [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ];
    }

    /**
     * Get test credentials.
     */
    protected function getTestCredentials(): array
    {
        return [
            'host' => 'localhost',
            'port' => 3306,
            'username' => 'testuser',
            'password' => 'testpass',
            'driver' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }

    /**
     * Mock the logger to prevent actual logging during tests.
     */
    protected function mockLogger(): \Mockery\MockInterface
    {
        $mock = \Mockery::mock(\AngelitoSystems\FilamentTenancy\Support\TenancyLogger::class);
        $this->app->instance(\AngelitoSystems\FilamentTenancy\Support\TenancyLogger::class, $mock);
        return $mock;
    }

    /**
     * Assert that a tenant connection exists.
     */
    protected function assertTenantConnectionExists(string $connectionName): void
    {
        $connections = config('database.connections');
        $this->assertArrayHasKey($connectionName, $connections);
    }

    /**
     * Assert that the current connection is for a specific tenant.
     */
    protected function assertCurrentConnectionIs(string $expectedConnection): void
    {
        $currentConnection = config('database.default');
        $this->assertEquals($expectedConnection, $currentConnection);
    }

    /**
     * Assert that sensitive data is masked.
     */
    protected function assertSensitiveDataMasked(array $data, array $sensitiveKeys): void
    {
        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $this->assertEquals('***', $data[$key], "Sensitive key '{$key}' should be masked");
            }
        }
    }
}