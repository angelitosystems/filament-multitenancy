<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\TenancyLogger;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Mockery;

class TenancyLoggerTest extends TestCase
{
    protected TenancyLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = new TenancyLogger('testing');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_logs_tenant_connection_events()
    {
        $tenant = new Tenant(['id' => 'test-tenant']);
        $context = ['connection_name' => 'tenant_test_tenant'];

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'info',
                'Tenant connection: switched_to_tenant',
                Mockery::on(function ($logContext) use ($tenant, $context) {
                    return $logContext['event'] === 'switched_to_tenant' &&
                           $logContext['tenant_id'] === $tenant->id &&
                           $logContext['tenant_slug'] === $tenant->slug &&
                           $logContext['connection_name'] === $context['connection_name'] &&
                           isset($logContext['timestamp']) &&
                           isset($logContext['ip']) &&
                           isset($logContext['user_agent']);
                })
            )
            ->once();

        $this->logger->logConnection('switched_to_tenant', $tenant, $context);
    }

    /** @test */
    public function it_logs_database_operations()
    {
        $operation = 'create_database';
        $tenant = new Tenant(['id' => 'test-tenant', 'slug' => 'test-tenant']);
        $details = ['database_name' => 'tenant_test_tenant'];

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'info',
                'Database operation: create_database',
                Mockery::on(function ($logContext) use ($operation, $tenant, $details) {
                    return $logContext['operation'] === $operation &&
                           $logContext['tenant_id'] === $tenant->id &&
                           isset($logContext['database_name']);
                })
            )
            ->once();

        $this->logger->logDatabaseOperation($operation, $tenant, $details);
    }

    /** @test */
    public function it_logs_credential_operations()
    {
        $operation = 'stored';
        $tenant = new Tenant(['id' => 'test-tenant', 'slug' => 'test-tenant']);
        $context = ['host' => 'localhost'];

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'info',
                'Credential operation: stored',
                Mockery::on(function ($logContext) use ($operation, $tenant, $context) {
                    return $logContext['operation'] === $operation &&
                           $logContext['tenant_id'] === $tenant->id &&
                           isset($logContext['host']);
                })
            )
            ->once();

        $this->logger->logCredentialOperation($operation, $tenant, $context);
    }

    /** @test */
    public function it_logs_security_events()
    {
        $event = 'encryption_key_rotated';
        $context = ['old_key_hash' => 'abc123', 'new_key_hash' => 'def456'];

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'warning',
                'Security event: encryption_key_rotated',
                Mockery::on(function ($logContext) use ($event, $context) {
                    return $logContext['event'] === $event &&
                           $logContext['security_level'] === 'high' &&
                           $logContext['old_key_hash'] === $context['old_key_hash'] &&
                           $logContext['new_key_hash'] === $context['new_key_hash'];
                })
            )
            ->once();

        $this->logger->logSecurityEvent($event, $context);
    }

    /** @test */
    public function it_logs_connection_errors()
    {
        $error = 'Connection timeout';
        $tenant = new Tenant(['id' => 'test-tenant', 'slug' => 'test-tenant']);
        $details = ['message' => 'Connection failed after 30 seconds'];

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'error',
                'Connection error: Connection timeout',
                Mockery::on(function ($logContext) use ($error, $tenant, $details) {
                    return $logContext['error'] === $error &&
                           $logContext['tenant_id'] === $tenant->id &&
                           isset($logContext['message']);
                })
            )
            ->once();

        $this->logger->logConnectionError($error, $tenant, $details);
    }

    /** @test */
    public function it_logs_connection_with_null_tenant()
    {
        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'info',
                'Tenant connection: switched_to_central',
                Mockery::on(function ($logContext) {
                    return $logContext['event'] === 'switched_to_central' &&
                           !isset($logContext['tenant_id']);
                })
            )
            ->once();

        $this->logger->logConnection('switched_to_central', null, ['connection_name' => 'mysql']);
    }

    /** @test */
    public function it_logs_performance_metrics()
    {
        $metric = 'query_execution_time';
        $value = 150.5;
        $context = ['query' => 'SELECT * FROM users', 'connection' => 'tenant_test', 'unit' => 'ms'];

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'info',
                'Performance metric: query_execution_time',
                Mockery::on(function ($logContext) use ($metric, $value, $context) {
                    return $logContext['metric'] === $metric &&
                           $logContext['value'] === $value &&
                           $logContext['unit'] === $context['unit'] &&
                           $logContext['query'] === $context['query'] &&
                           $logContext['connection'] === $context['connection'];
                })
            )
            ->once();

        $this->logger->logPerformanceMetric($metric, $value, $context);
    }

    /** @test */
    public function it_logs_tenant_switches()
    {
        $fromTenant = new Tenant(['id' => 'tenant-1', 'slug' => 'tenant-1']);
        $toTenant = new Tenant(['id' => 'tenant-2', 'slug' => 'tenant-2']);

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'info',
                'Tenant context switched',
                Mockery::on(function ($logContext) use ($fromTenant, $toTenant) {
                    return $logContext['from_tenant_id'] === $fromTenant->id &&
                           $logContext['to_tenant_id'] === $toTenant->id;
                })
            )
            ->once();

        $this->logger->logTenantSwitch($fromTenant, $toTenant);
    }

    /** @test */
    public function it_logs_cache_operations()
    {
        $operation = 'cache_hit';
        $key = 'tenant_config_test_tenant';
        $context = ['ttl' => 3600];

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'debug',
                'Cache operation: cache_hit',
                Mockery::on(function ($logContext) use ($operation, $key, $context) {
                    return $logContext['operation'] === $operation &&
                           $logContext['cache_key'] === $key &&
                           $logContext['ttl'] === $context['ttl'];
                })
            )
            ->once();

        $this->logger->logCacheOperation($operation, $key, $context);
    }

    /** @test */
    public function it_logs_configuration_changes()
    {
        $setting = 'max_connections';
        $oldValue = 10;
        $newValue = 20;
        $context = ['changed_by' => 'admin'];

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'info',
                'Configuration changed: max_connections',
                Mockery::any()
            )
            ->once();

        $this->logger->logConfigurationChange($setting, $oldValue, $newValue, $context);
    }

    /** @test */
    public function it_gets_log_statistics()
    {
        // This would require implementing a log statistics feature
        // For now, we'll test that the method exists and returns an array
        $stats = $this->logger->getLogStatistics();
        
        $this->assertIsArray($stats);
    }

    /** @test */
    public function it_logs_credential_errors()
    {
        $operation = 'validation_failed';
        $tenant = new Tenant(['id' => 'test-tenant', 'slug' => 'test-tenant']);
        $error = 'Invalid credentials format';

        Log::shouldReceive('channel')
            ->with('testing')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->with(
                'error',
                'Credential error: validation_failed',
                Mockery::on(function ($logContext) use ($operation, $tenant, $error) {
                    return $logContext['operation'] === $operation &&
                           $logContext['tenant_id'] === $tenant->id &&
                           $logContext['error'] === $error;
                })
            )
            ->once();

        $this->logger->logCredentialError($operation, $tenant, $error);
    }
}