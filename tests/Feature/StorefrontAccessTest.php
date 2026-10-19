<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Gateway;
use App\Models\Payment;
use App\Models\StoreProduct;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StorefrontAccessTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        config()->set('services.manual.webhook_secret', 'test-secret');
    }

    public function test_product_requires_at_least_one_team(): void
    {
        $course = $this->buildCourse($this->company->id);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/store-products', [
                'course_id' => $course['course']->id,
                'title' => 'Sem turma',
                'price' => 50,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('team_ids');
    }

    public function test_paid_order_grants_all_courses_in_team_for_configured_period(): void
    {
        $dataA = $this->buildCourse($this->company->id);
        $dataB = $this->buildCourse($this->company->id);
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $team->courses()->attach($dataA['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);
        $team->courses()->attach($dataB['course']->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $dataA['course']->id,
            'price' => 100,
            'access_duration_days' => 7,
        ]);

        $product->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);

        $token = $this->apiLogin($this->student);

        $orderId = $this->withToken($token)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $this->withToken($token)->postJson("/api/v1/me/orders/{$orderId}/pay", [
            'gateway_id' => $gateway->id,
            'method' => 'pix',
        ])->assertCreated();

        $payment = Payment::query()->firstOrFail();

        $this->withHeaders([
            'X-Company' => (string) $this->company->id,
            'X-Webhook-Secret' => 'test-secret',
        ])->postJson('/api/v1/webhooks/companies/'.$this->company->slug.'/payments/manual', [
            'event_id' => 'evt-access-1',
            'event' => 'payment.updated',
            'status' => 'paid',
            'transaction_id' => $payment->transaction_id,
        ])->assertStatus(202);

        $this->assertSame(PaymentStatus::Paid->value, $payment->fresh()->status);

        $this->withToken($token)
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertDatabaseHas('team_users', [
            'team_id' => $team->id,
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);

        $pivot = $team->users()->whereKey($this->student->id)->first()?->pivot;
        $this->assertNotNull($pivot);
        $this->assertTrue(Carbon::parse($pivot->expired_at)->between(now()->addDays(6), now()->addDays(8)));

        Carbon::setTestNow(now()->addDays(8));

        $this->withToken($token)
            ->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        Carbon::setTestNow();
    }
}
