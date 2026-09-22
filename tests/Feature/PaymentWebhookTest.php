<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Gateway;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\StoreProduct;
use App\Models\TeamUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        config()->set('services.manual.webhook_secret', 'test-secret');
    }

    public function test_webhook_confirms_payment_grants_access_and_is_idempotent(): void
    {
        $course = $this->buildCourse($this->company->id);

        $storeProduct = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $course['course']->id,
            'price' => 100,
        ]);

        $team = $this->enroll($this->student, $course['course'], $this->company->id);
        $storeProduct->teams()->attach($team->id, ['company_id' => $this->company->id, 'status' => 'active']);

        $gateway = Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);

        $studentToken = $this->apiLogin($this->student);
        $adminToken = $this->apiLogin($this->admin);

        $orderId = $this->withToken($studentToken)->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $storeProduct->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->withToken($adminToken)->postJson('/api/v1/admin/payments', [
            'order_id' => $orderId,
            'gateway_id' => $gateway->id,
            'method' => 'pix',
            'amount' => 100,
        ])->assertCreated();

        $payment = Payment::query()->firstOrFail();

        $webhookPayload = [
            'event_id' => 'evt_1',
            'event' => 'payment.updated',
            'status' => 'paid',
            'transaction_id' => $payment->transaction_id,
        ];

        $this->withHeaders([
            'X-Company' => (string) $this->company->id,
            'X-Webhook-Secret' => 'test-secret',
        ])
            ->postJson('/api/v1/webhooks/payments/manual', $webhookPayload)
            ->assertStatus(202);

        $this->assertSame(PaymentStatus::Paid->value, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Paid->value, $payment->order->fresh()->status);

        $this->assertDatabaseHas('team_users', [
            'team_id' => $team->id,
            'user_id' => $this->student->id,
            'status' => 'active',
        ]);

        // Duplicate webhook must not be processed twice.
        $this->withHeaders([
            'X-Company' => (string) $this->company->id,
            'X-Webhook-Secret' => 'test-secret',
        ])
            ->postJson('/api/v1/webhooks/payments/manual', $webhookPayload)
            ->assertStatus(202);

        $this->assertSame(1, PaymentEvent::query()->where('external_id', 'evt_1')->count());
        $this->assertSame(1, TeamUser::query()->where('team_id', $team->id)->where('user_id', $this->student->id)->count());
    }

    public function test_webhook_requires_company_header(): void
    {
        $this->postJson('/api/v1/webhooks/payments/manual', ['status' => 'paid'])
            ->assertStatus(400);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);

        $this->withHeaders([
            'X-Company' => (string) $this->company->id,
            'X-Webhook-Secret' => 'wrong-secret',
        ])
            ->postJson('/api/v1/webhooks/payments/manual', ['status' => 'paid'])
            ->assertStatus(401)
            ->assertJsonPath('code', 'invalid_webhook_signature');
    }

    public function test_webhook_fails_closed_when_secret_is_not_configured(): void
    {
        Gateway::factory()->create([
            'company_id' => $this->company->id,
            'code' => 'manual',
        ]);

        config()->set('services.manual.webhook_secret', null);

        $this->withHeaders([
            'X-Company' => (string) $this->company->id,
            'X-Webhook-Secret' => 'any',
        ])
            ->postJson('/api/v1/webhooks/payments/manual', ['status' => 'paid'])
            ->assertStatus(401);
    }
}
