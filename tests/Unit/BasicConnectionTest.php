<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use PHPUnit\Framework\TestCase;

class BasicConnectionTest extends TestCase
{
    /** @test */
    public function it_can_create_tenant_model()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'database_name' => 'tenant_test_db',
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test Tenant', $tenant->name);
        $this->assertEquals('test-tenant', $tenant->slug);
        $this->assertEquals('tenant_test_db', $tenant->database_name);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $tenant = new Tenant();
        $fillable = $tenant->getFillable();

        $expectedFillable = [
            'name',
            'slug',
            'domain',
            'subdomain',
            'database_name',
            'database_host',
            'database_port',
            'database_username',
            'database_password',
            'is_active',
            'plan',
            'expires_at',
            'data',
        ];

        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $fillable, "Field {$field} should be fillable");
        }
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $tenant = new Tenant();
        $casts = $tenant->getCasts();

        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('datetime', $casts['expires_at']);
        $this->assertEquals('array', $casts['data']);
        $this->assertEquals('integer', $casts['database_port']);
    }

    /** @test */
    public function it_can_check_if_tenant_is_active()
    {
        $activeTenant = new Tenant(['is_active' => true]);
        $inactiveTenant = new Tenant(['is_active' => false]);

        $this->assertTrue($activeTenant->is_active);
        $this->assertFalse($inactiveTenant->is_active);
    }

    /** @test */
    public function it_can_handle_tenant_data_as_array()
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $tenant = new Tenant(['data' => $data]);

        $this->assertEquals($data, $tenant->data);
    }
}