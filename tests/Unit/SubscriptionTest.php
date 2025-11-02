<?php

namespace AngelitoSystems\FilamentTenancy\Tests\Unit;

use AngelitoSystems\FilamentTenancy\Models\Plan;
use AngelitoSystems\FilamentTenancy\Models\Subscription;
use AngelitoSystems\FilamentTenancy\Models\Tenant;
use AngelitoSystems\FilamentTenancy\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure we use SQLite for testing (not MySQL)
        Config::set('database.default', 'testing');
        Config::set('filament-tenancy.database.default_connection', 'testing');
        
        // Create a test tenant for all subscription tests
        $this->testTenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-subscription',
        ]);
    }
    
    protected $testTenant;

    /** @test */
    public function it_can_instantiate_a_subscription()
    {
        $subscription = new Subscription([
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $subscription = new Subscription();
        $fillable = $subscription->getFillable();

        $expectedFillable = [
            'tenant_id',
            'plan_id',
            'status',
            'starts_at',
            'ends_at',
            'trial_ends_at',
            'canceled_at',
            'canceled_reason',
            'metadata',
        ];

        foreach ($expectedFillable as $attribute) {
            $this->assertContains($attribute, $fillable);
        }
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $subscription = new Subscription();
        $casts = $subscription->getCasts();

        $this->assertEquals('integer', $casts['tenant_id']);
        $this->assertEquals('integer', $casts['plan_id']);
        $this->assertEquals('datetime', $casts['starts_at']);
        $this->assertEquals('datetime', $casts['ends_at']);
        $this->assertEquals('datetime', $casts['trial_ends_at']);
        $this->assertEquals('datetime', $casts['canceled_at']);
        $this->assertEquals('array', $casts['metadata']);
    }

    /** @test */
    public function it_can_belong_to_a_tenant()
    {
        // Create tenant first to avoid connection issues
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        $subscription = new Subscription(['tenant_id' => $tenant->id]);
        $relation = $subscription->tenant();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    /** @test */
    public function it_can_belong_to_a_plan()
    {
        $subscription = new Subscription();
        $relation = $subscription->plan();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }

    /** @test */
    public function it_can_check_if_active()
    {
        $subscription = new Subscription([
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->isActive());

        $subscription = new Subscription([
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => now()->addDays(30),
        ]);

        $this->assertTrue($subscription->isActive());

        $subscription = new Subscription([
            'status' => Subscription::STATUS_CANCELED,
        ]);

        $this->assertFalse($subscription->isActive());
    }

    /** @test */
    public function it_can_check_if_expired()
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => now()->subDays(1),
            'starts_at' => now()->subDays(2),
        ]);

        $this->assertTrue($subscription->isExpired());

        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => now()->addDays(1),
            'starts_at' => now(),
        ]);

        $this->assertFalse($subscription->isExpired());

        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
            'starts_at' => now(),
        ]);

        $this->assertFalse($subscription->isExpired());
    }

    /** @test */
    public function it_can_check_if_on_trial()
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(7),
            'starts_at' => now(),
        ]);

        $this->assertTrue($subscription->onTrial());

        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_TRIAL,
            'trial_ends_at' => now()->subDays(1),
            'starts_at' => now()->subDays(2),
        ]);

        $this->assertFalse($subscription->onTrial());

        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
            'starts_at' => now(),
        ]);

        $this->assertFalse($subscription->onTrial());
    }

    /** @test */
    public function it_can_check_if_canceled()
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_CANCELED,
            'starts_at' => now(),
        ]);

        $this->assertTrue($subscription->isCanceled());

        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'canceled_at' => now(),
            'starts_at' => now(),
        ]);

        $this->assertTrue($subscription->isCanceled());

        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'canceled_at' => null,
            'starts_at' => now(),
        ]);

        $this->assertFalse($subscription->isCanceled());
    }

    /** @test */
    public function it_can_cancel_subscription()
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        $subscription->cancel('User requested cancellation');

        $this->assertEquals(Subscription::STATUS_CANCELED, $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertEquals('User requested cancellation', $subscription->canceled_reason);
    }

    /** @test */
    public function it_can_activate_subscription()
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_PENDING,
            'starts_at' => now(),
        ]);

        $endsAt = now()->addDays(30);
        $subscription->activate($endsAt);

        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertNotNull($subscription->starts_at);
        $this->assertEquals($endsAt->format('Y-m-d'), $subscription->ends_at->format('Y-m-d'));
        $this->assertNull($subscription->canceled_at);
    }

    /** @test */
    public function it_can_scope_active_subscriptions()
    {
        Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
            'starts_at' => now(),
        ]);

        Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => now()->addDays(30),
            'starts_at' => now(),
        ]);

        Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_CANCELED,
            'starts_at' => now(),
        ]);

        Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => now()->subDays(1),
            'starts_at' => now()->subDays(2),
        ]);

        $activeSubscriptions = Subscription::active()->get();

        $this->assertCount(2, $activeSubscriptions);
    }

    /** @test */
    public function it_can_scope_expired_subscriptions()
    {
        Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => now()->subDays(1),
            'starts_at' => now()->subDays(2),
        ]);

        Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => now()->addDays(1),
            'starts_at' => now(),
        ]);

        Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_CANCELED,
            'ends_at' => now()->subDays(1),
            'starts_at' => now()->subDays(2),
        ]);

        $expiredSubscriptions = Subscription::expired()->get();

        $this->assertCount(1, $expiredSubscriptions);
    }

    /** @test */
    public function it_can_store_metadata_as_array()
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->testTenant->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'metadata' => [
                'payment_method' => 'credit_card',
                'billing_email' => 'test@example.com',
            ],
        ]);

        $this->assertIsArray($subscription->metadata);
        $this->assertEquals('credit_card', $subscription->metadata['payment_method']);
        $this->assertEquals('test@example.com', $subscription->metadata['billing_email']);
    }

    /** @test */
    public function it_uses_landlord_connection()
    {
        $subscription = new Subscription();
        $traits = class_uses_recursive(get_class($subscription));
        $this->assertContains(\AngelitoSystems\FilamentTenancy\Concerns\UsesLandlordConnection::class, $traits);
    }
}

