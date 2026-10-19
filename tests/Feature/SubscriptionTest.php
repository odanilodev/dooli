<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Gateway;
use App\Models\StoreProduct;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        config()->set('services.manual.webhook_secret', 'test-secret');
    }

    public function test_monthly_subscription_activates_grants_access_and_cancels_at_period_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-19 12:00:00'));

        $course = $this->buildCourse($this->company->id);
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $team->courses()->attach($course['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 99,
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
        ]);
        $product->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);

        $token = $this->apiLogin($this->student);

        $subscriptionId = $this->withToken($token)->postJson('/api/v1/me/subscriptions', [
            'store_product_id' => $product->id,
            'gateway_id' => $gateway->id,
        ])->assertCreated()->json('data.subscription.id');

        $subscription = Subscription::query()->findOrFail($subscriptionId);

        $this->assertSame(SubscriptionStatus::Pending->value, $subscription->status);
        $this->assertSame('manual-sub-'.$subscription->id, $subscription->provider_subscription_id);

        $this->withHeaders([
            'X-Company' => (string) $this->company->id,
            'X-Webhook-Secret' => 'test-secret',
        ])->postJson('/api/v1/webhooks/companies/'.$this->company->slug.'/subscriptions/manual', [
            'provider_subscription_id' => $subscription->provider_subscription_id,
            'status' => 'paid',
            'transaction_id' => 'sub-payment-1',
        ])->assertStatus(202);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Active->value, $subscription->status);
        $this->assertSame('2026-10-19 12:00:00', $subscription->current_period_start?->toDateTimeString());
        $this->assertSame('2026-11-19 12:00:00', $subscription->current_period_end?->toDateTimeString());

        $this->withToken($token)->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->withToken($token)->postJson("/api/v1/me/subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Canceled->value);

        $this->withToken($token)->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00'));

        $this->withToken($token)->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        Carbon::setTestNow();
    }

    public function test_subscription_cannot_be_duplicated(): void
    {
        $course = $this->buildCourse($this->company->id);
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $team->courses()->attach($course['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
        ]);
        $product->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);

        $token = $this->apiLogin($this->student);

        $payload = [
            'store_product_id' => $product->id,
            'gateway_id' => $gateway->id,
        ];

        $this->withToken($token)->postJson('/api/v1/me/subscriptions', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/me/subscriptions', $payload)->assertStatus(422);
    }
}
