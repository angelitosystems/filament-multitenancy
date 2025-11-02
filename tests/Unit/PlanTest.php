<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Plan;
use AngelitoSystems\FilamentTenancy\Models\Subscription;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure we use SQLite for testing (not MySQL)
        Config::set('database.default', 'testing');
        Config::set('filament-tenancy.database.default_connection', 'testing');
    }

    /** @test */
    public function it_can_instantiate_a_plan()
    {
        $plan = new Plan([
            'name' => 'Basic Plan',
            'slug' => 'basic',
            'price' => 9.99,
            'currency' => 'USD',
        ]);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('Basic Plan', $plan->name);
        $this->assertEquals('basic', $plan->slug);
        $this->assertEquals(9.99, $plan->price);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $plan = new Plan();
        $fillable = $plan->getFillable();

        $expectedFillable = [
            'name',
            'slug',
            'description',
            'price',
            'currency',
            'billing_cycle',
            'features',
            'limits',
            'is_active',
            'sort_order',
        ];

        foreach ($expectedFillable as $attribute) {
            $this->assertContains($attribute, $fillable);
        }
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $plan = new Plan();
        $casts = $plan->getCasts();

        $this->assertEquals('decimal:2', $casts['price']);
        $this->assertEquals('array', $casts['features']);
        $this->assertEquals('array', $casts['limits']);
        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('integer', $casts['sort_order']);
    }

    /** @test */
    public function it_can_have_subscriptions()
    {
        $plan = Plan::create([
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 29.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $plan->subscriptions());
    }

    /** @test */
    public function it_can_get_active_subscriptions()
    {
        $plan = Plan::create([
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 29.99,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $plan->activeSubscriptions());
    }

    /** @test */
    public function it_can_format_price()
    {
        $plan = new Plan([
            'price' => 19.99,
            'currency' => 'USD',
        ]);

        $this->assertEquals('USD 19.99', $plan->formatted_price);
    }

    /** @test */
    public function it_can_scope_active_plans()
    {
        Plan::create([
            'name' => 'Active Plan',
            'slug' => 'active',
            'price' => 10.00,
            'currency' => 'USD',
            'is_active' => true,
        ]);

        Plan::create([
            'name' => 'Inactive Plan',
            'slug' => 'inactive',
            'price' => 10.00,
            'currency' => 'USD',
            'is_active' => false,
        ]);

        $activePlans = Plan::active()->get();

        $this->assertCount(1, $activePlans);
        $this->assertEquals('Active Plan', $activePlans->first()->name);
    }

    /** @test */
    public function it_can_store_features_as_array()
    {
        $plan = Plan::create([
            'name' => 'Feature Plan',
            'slug' => 'feature',
            'price' => 15.00,
            'currency' => 'USD',
            'features' => ['feature1', 'feature2', 'feature3'],
        ]);

        $this->assertIsArray($plan->features);
        $this->assertCount(3, $plan->features);
        $this->assertContains('feature1', $plan->features);
    }

    /** @test */
    public function it_can_store_limits_as_array()
    {
        $plan = Plan::create([
            'name' => 'Limited Plan',
            'slug' => 'limited',
            'price' => 20.00,
            'currency' => 'USD',
            'limits' => [
                'max_users' => 10,
                'max_storage' => 100,
            ],
        ]);

        $this->assertIsArray($plan->limits);
        $this->assertEquals(10, $plan->limits['max_users']);
        $this->assertEquals(100, $plan->limits['max_storage']);
    }

    /** @test */
    public function it_uses_landlord_connection()
    {
        $plan = new Plan();
        $traits = class_uses_recursive(get_class($plan));
        $this->assertContains(\AngelitoSystems\FilamentTenancy\Concerns\UsesLandlordConnection::class, $traits);
    }
}

