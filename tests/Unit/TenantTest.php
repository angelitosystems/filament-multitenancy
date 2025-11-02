<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class TenantTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure we use SQLite for testing (not MySQL)
        Config::set('database.default', 'testing');
        Config::set('filament-tenancy.database.default_connection', 'testing');
        
        // Set up configuration for testing
        Config::set('filament-tenancy.tenant_model', Tenant::class);
        Config::set('filament-tenancy.database.auto_create', true);
        Config::set('filament-tenancy.database.auto_delete', false);
        Config::set('filament-tenancy.central_domains', ['app.dental.test']);
    }

    /** @test */
    public function it_can_instantiate_a_tenant()
    {
        $tenant = new Tenant([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
        ]);

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test Tenant', $tenant->name);
        $this->assertEquals('test.example.com', $tenant->domain);
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
            'plan', // Legacy: string plan name
            'plan_id', // New: foreign key to plans table
            'expires_at',
            'data',
        ];

        foreach ($expectedFillable as $attribute) {
            $this->assertContains($attribute, $fillable);
        }
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $tenant = new Tenant();
        $casts = $tenant->getCasts();

        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('integer', $casts['plan_id']);
        $this->assertEquals('datetime', $casts['expires_at']);
        $this->assertEquals('array', $casts['data']);
        $this->assertEquals('integer', $casts['database_port']);
    }

    /** @test */
    public function it_can_check_if_tenant_is_active()
    {
        $tenant = new Tenant();
        $tenant->setRawAttributes(['is_active' => true]);
        $this->assertTrue($tenant->isActive());

        $tenant = new Tenant();
        $tenant->setRawAttributes(['is_active' => false]);
        $this->assertFalse($tenant->isActive());
    }

    /** @test */
    public function it_can_check_if_tenant_is_expired()
    {
        $tenant = new Tenant();
        $tenant->setRawAttributes(['expires_at' => now()->addDays(30)]);
        $this->assertFalse($tenant->isExpired());

        $tenant = new Tenant();
        $tenant->setRawAttributes(['expires_at' => now()->subDays(1)]);
        $this->assertTrue($tenant->isExpired());

        $tenant = new Tenant();
        $tenant->setRawAttributes(['expires_at' => null]);
        $this->assertFalse($tenant->isExpired());
    }

    /** @test */
    public function it_can_get_full_domain()
    {
        $tenant = new Tenant();
        $tenant->setRawAttributes(['domain' => 'test.example.com']);
        $this->assertEquals('test.example.com', $tenant->getFullDomain());

        $subdomainTenant = new Tenant();
        $subdomainTenant->setRawAttributes(['subdomain' => 'test']);
        // Based on the actual implementation, it uses central_domains config
        $this->assertEquals('test.app.dental.test', $subdomainTenant->getFullDomain());
    }

    /** @test */
    public function it_can_get_url()
    {
        $tenant = new Tenant();
        $tenant->setRawAttributes(['domain' => 'test.example.com']);
        $this->assertEquals('http://test.example.com', $tenant->getUrl());
    }

    /** @test */
    public function it_loads_configuration_correctly()
    {
        // Test that the configuration is loaded properly
        $this->assertEquals(Tenant::class, config('filament-tenancy.tenant_model'));
        $this->assertTrue(config('filament-tenancy.database.auto_create'));
        $this->assertFalse(config('filament-tenancy.database.auto_delete'));
        $this->assertContains('app.dental.test', config('filament-tenancy.central_domains'));
    }

    /** @test */
    public function it_can_access_database_configuration()
    {
        // Test database configuration access
        $this->assertIsArray(config('filament-tenancy.database'));
        $this->assertArrayHasKey('auto_create', config('filament-tenancy.database'));
        $this->assertArrayHasKey('auto_delete', config('filament-tenancy.database'));
        $this->assertArrayHasKey('connection_pool_size', config('filament-tenancy.database'));
    }

    /** @test */
    public function it_can_have_plan_relationship()
    {
        $plan = \AngelitoSystems\FilamentTenancy\Models\Plan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price' => 10.00,
            'currency' => 'USD',
        ]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'plan_id' => $plan->id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $tenant->planModel());
        $this->assertEquals($plan->id, $tenant->plan_id);
    }

    /** @test */
    public function it_can_have_subscriptions()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $tenant->subscriptions());
    }

    /** @test */
    public function it_can_get_active_subscription()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        \AngelitoSystems\FilamentTenancy\Models\Subscription::create([
            'tenant_id' => $tenant->id,
            'status' => \AngelitoSystems\FilamentTenancy\Models\Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        $activeSubscription = $tenant->activeSubscription();

        $this->assertNotNull($activeSubscription);
        $this->assertEquals(\AngelitoSystems\FilamentTenancy\Models\Subscription::STATUS_ACTIVE, $activeSubscription->status);
    }

    /** @test */
    public function it_can_get_current_plan_from_plan_id()
    {
        $plan = \AngelitoSystems\FilamentTenancy\Models\Plan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price' => 10.00,
            'currency' => 'USD',
        ]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'plan_id' => $plan->id,
        ]);

        $currentPlan = $tenant->getCurrentPlan();
        $this->assertInstanceOf(\AngelitoSystems\FilamentTenancy\Models\Plan::class, $currentPlan);
        $this->assertEquals($plan->id, $currentPlan->id);
    }

    /** @test */
    public function it_can_get_current_plan_from_legacy_plan_string()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'plan' => 'basic',
        ]);

        $currentPlan = $tenant->getCurrentPlan();
        $this->assertEquals('basic', $currentPlan);
    }

    /** @test */
    public function it_can_use_data_attribute_methods()
    {
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'data' => [
                'settings' => [
                    'theme' => 'dark',
                    'timezone' => 'UTC',
                ],
            ],
        ]);

        $this->assertEquals('dark', $tenant->getData('settings.theme'));
        $this->assertEquals('UTC', $tenant->getData('settings.timezone'));
        $this->assertTrue($tenant->hasData('settings.theme'));

        $tenant->setData('settings.language', 'en');
        $this->assertEquals('en', $tenant->getData('settings.language'));

        $tenant->removeData('settings.language');
        $this->assertFalse($tenant->hasData('settings.language'));
    }

    /** @test */
    public function it_auto_generates_slug_on_creation()
    {
        $tenant = Tenant::create([
            'name' => 'My Test Tenant',
        ]);

        $this->assertEquals('my-test-tenant', $tenant->slug);
    }

    /** @test */
    public function it_respects_provided_slug()
    {
        $tenant = Tenant::create([
            'name' => 'My Test Tenant',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $tenant->slug);
    }

    /** @test */
    public function it_can_scope_active_tenants()
    {
        Tenant::create([
            'name' => 'Active Tenant',
            'slug' => 'active',
            'is_active' => true,
        ]);

        Tenant::create([
            'name' => 'Inactive Tenant',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $activeTenants = Tenant::active()->get();

        $this->assertCount(1, $activeTenants);
        $this->assertEquals('Active Tenant', $activeTenants->first()->name);
    }

    /** @test */
    public function it_can_scope_expired_tenants()
    {
        Tenant::create([
            'name' => 'Expired Tenant',
            'slug' => 'expired',
            'expires_at' => now()->subDays(1),
        ]);

        Tenant::create([
            'name' => 'Active Tenant',
            'slug' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $expiredTenants = Tenant::expired()->get();

        $this->assertCount(1, $expiredTenants);
        $this->assertEquals('Expired Tenant', $expiredTenants->first()->name);
    }
}