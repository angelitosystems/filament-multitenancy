<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Support\TenantResolver;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Illuminate\Http\Request;

class TenantResolverTest extends TestCase
{
    private TenantResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TenantResolver();
    }

    /** @test */
    public function it_can_instantiate_tenant_resolver()
    {
        $this->assertInstanceOf(TenantResolver::class, $this->resolver);
    }

    /** @test */
    public function it_can_set_and_get_current_tenant()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $this->resolver->setCurrent($tenant);
        $currentTenant = $this->resolver->current();

        $this->assertInstanceOf(Tenant::class, $currentTenant);
        $this->assertEquals($tenant->name, $currentTenant->name);
        $this->assertEquals($tenant->domain, $currentTenant->domain);
    }

    /** @test */
    public function it_can_clear_current_tenant()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'is_active' => true,
        ]);

        $this->resolver->setCurrent($tenant);
        $this->assertNotNull($this->resolver->current());

        $this->resolver->setCurrent(null);
        $this->assertNull($this->resolver->current());
    }

    /** @test */
    public function it_returns_null_for_central_domains()
    {
        // Set up central domains in config for testing
        config(['filament-tenancy.central_domains' => ['app.dental.test', 'admin.dental.test']]);
        
        $request = Request::create('https://app.dental.test');
        $resolvedTenant = $this->resolver->resolve($request);

        $this->assertNull($resolvedTenant);
    }

    /** @test */
    public function it_can_check_if_domain_is_central()
    {
        // Set up central domains in config for testing
        config(['filament-tenancy.central_domains' => ['app.dental.test', 'admin.dental.test']]);
        
        $this->assertTrue($this->resolver->isCentralDomain('app.dental.test'));
        $this->assertTrue($this->resolver->isCentralDomain('admin.dental.test'));
        $this->assertFalse($this->resolver->isCentralDomain('tenant.example.com'));
    }
}