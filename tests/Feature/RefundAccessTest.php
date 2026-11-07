<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Enums\PaymentStatus;
use App\Models\Gateway;
use App\Models\Payment;
use App\Models\StoreProduct;
use App\Models\Team;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class RefundAccessTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    private array $data;

    private Team $team;

    private int $paymentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();

        $this->data = $this->buildCourse($this->company->id);

        // Turma sem matrícula direta: o acesso do aluno vem só da compra.
        $this->team = Team::factory()->create([
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);
        $this->team->courses()->attach($this->data['course']->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);

        $product = StoreProduct::factory()->create([
            'company_id' => $this->company->id,
            'course_id' => $this->data['course']->id,
            'price' => 100,
        ]);
        $product->teams()->attach($this->team->id, [
            'company_id' => $this->company->id,
            'status' => GeneralStatus::Active->value,
        ]);

        $gateway = Gateway::factory()->create(['company_id' => $this->company->id, 'code' => 'manual']);

        $orderId = $this->withToken($this->apiLogin($this->student))->postJson('/api/v1/me/orders', [
            'items' => [['store_product_id' => $product->id, 'quantity' => 1]],
        ])->json('data.id');

        $this->paymentId = $this->withToken($this->apiLogin($this->admin))->postJson('/api/v1/admin/payments', [
            'order_id' => $orderId,
            'gateway_id' => $gateway->id,
            'method' => 'pix',
            'amount' => 100,
        ])->json('data.id');

        $this->webhook('evt-paid-1', PaymentStatus::Paid->value);
    }

    private function webhook(string $externalId, string $status): void
    {
        app(PaymentService::class)->applyWebhook($this->paymentId, [
            'external_id' => $externalId,
            'event' => 'payment.updated',
            'status' => $status,
            'transaction_id' => Payment::query()->findOrFail($this->paymentId)->transaction_id,
            'metadata' => [],
        ]);
    }

    private function studentCourseIds(): array
    {
        return collect(
            $this->withToken($this->apiLogin($this->student))->getJson('/api/v1/me/courses')->json('data'),
        )->pluck('id')->all();
    }

    public function test_student_sees_the_course_after_paying(): void
    {
        $this->assertContains($this->data['course']->id, $this->studentCourseIds());
    }

    public function test_refund_removes_the_course_from_the_student(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/payments/{$this->paymentId}/refund", ['amount' => 100, 'reason' => 'Arrependimento'])
            ->assertOk();

        $this->assertNotContains($this->data['course']->id, $this->studentCourseIds());
    }

    public function test_a_late_paid_webhook_does_not_give_access_back_after_refund(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/payments/{$this->paymentId}/refund", ['amount' => 100, 'reason' => 'Arrependimento'])
            ->assertOk();

        $this->webhook('evt-paid-late', PaymentStatus::Paid->value);

        $this->assertNotContains($this->data['course']->id, $this->studentCourseIds());
        $this->assertSame(PaymentStatus::Refunded->value, Payment::query()->findOrFail($this->paymentId)->status);
    }

    public function test_gateway_refund_webhooks_after_a_refund_do_not_duplicate_it(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson("/api/v1/admin/payments/{$this->paymentId}/refund", ['amount' => 100, 'reason' => 'Arrependimento'])
            ->assertOk();

        foreach (['evt-refund-1', 'evt-refund-2'] as $id) {
            app(PaymentService::class)->applyWebhook($this->paymentId, [
                'external_id' => $id,
                'event' => 'payment.updated',
                'status' => PaymentStatus::Refunded->value,
                'transaction_id' => Payment::query()->findOrFail($this->paymentId)->transaction_id,
                'metadata' => ['transaction_amount_refunded' => 100, 'date_last_updated' => '2026-11-07T09:22:00.000-04:00'],
            ]);
        }

        $this->assertSame(1, Payment::query()->findOrFail($this->paymentId)->refunds()->count());
    }

    public function test_refund_webhook_stores_the_time_in_the_app_timezone(): void
    {
        app(PaymentService::class)->applyWebhook($this->paymentId, [
            'external_id' => 'evt-refund-tz',
            'event' => 'payment.updated',
            'status' => PaymentStatus::Refunded->value,
            'transaction_id' => Payment::query()->findOrFail($this->paymentId)->transaction_id,
            'metadata' => ['transaction_amount_refunded' => 100, 'date_last_updated' => '2026-11-07T09:22:00.000-04:00'],
        ]);

        $refund = Payment::query()->findOrFail($this->paymentId)->refunds()->firstOrFail();

        // 09:22 em -04:00 = 10:22 em Brasília (-03:00).
        $this->assertSame('2026-11-07 10:22:00', $refund->refunded_at->format('Y-m-d H:i:s'));
    }
}
