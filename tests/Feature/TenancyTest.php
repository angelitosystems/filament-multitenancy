<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Feature;

use AngelitoSystems\FilamentTenancy\Facades\Tenancy;
use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;

class TenancyTest extends TestCase
{
    /** @test */
    public function it_can_access_tenancy_facade()
    {
        $this->assertTrue(class_exists(Tenancy::class));
    }

    /** @test */
    public function it_starts_with_no_current_tenant()
    {
        $this->assertNull(Tenancy::current());
    }

    /** @test */
    public function it_can_switch_to_tenant_context()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $this->assertNull(Tenancy::current());

        Tenancy::switchToTenant($tenant);

        $this->assertNotNull(Tenancy::current());
        $this->assertEquals($tenant->name, Tenancy::current()->name);
    }

    /** @test */
    public function it_can_switch_to_central_context()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        Tenancy::switchToTenant($tenant);
        $this->assertNotNull(Tenancy::current());

        Tenancy::switchToCentral();
        $this->assertNull(Tenancy::current());
    }

    /** @test */
    public function it_can_run_code_for_specific_tenant()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $result = null;

        Tenancy::runForTenant($tenant, function ($currentTenant) use (&$result) {
            $result = $currentTenant;
        });

        $this->assertInstanceOf(Tenant::class, $result);
        $this->assertEquals($tenant->name, $result->name);
    }

    /** @test */
    public function it_can_run_code_in_central_context()
    {
        $result = Tenancy::runForCentral(function () {
            return 'central';
        });

        $this->assertEquals('central', $result);
    }
}