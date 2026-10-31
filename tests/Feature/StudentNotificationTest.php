<?php

namespace Tests\Feature;

use App\Enums\GeneralStatus;
use App\Enums\NotificationSlug;
use App\Mail\TransactionalMail;
use App\Models\Notification;
use App\Models\Team;
use App\Models\TypeNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class StudentNotificationTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_list_exposes_read_state(): void
    {
        $unread = $this->createNotificationFor($this->student);
        $read = $this->createNotificationFor($this->student, now()->toDateTimeString());

        $response = $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $items = collect($response->json('data'))->keyBy('id');

        $this->assertNull($items[$unread->id]['read_at']);
        $this->assertNotNull($items[$read->id]['read_at']);
    }

    public function test_mark_as_read_sets_read_at(): void
    {
        $notification = $this->createNotificationFor($this->student);
        $token = $this->apiLogin($this->student);

        $this->withToken($token)
            ->postJson("/api/v1/me/notifications/{$notification->id}/read")
            ->assertOk();

        $response = $this->withToken($token)->getJson('/api/v1/me/notifications')->assertOk();
        $item = collect($response->json('data'))->firstWhere('id', $notification->id);

        $this->assertNotNull($item['read_at']);
    }

    public function test_mark_all_as_read(): void
    {
        $this->createNotificationFor($this->student);
        $this->createNotificationFor($this->student);
        $token = $this->apiLogin($this->student);

        $this->withToken($token)->postJson('/api/v1/me/notifications/read-all')->assertOk();

        $response = $this->withToken($token)->getJson('/api/v1/me/notifications')->assertOk();
        $items = collect($response->json('data'));

        $this->assertTrue($items->every(fn ($item) => $item['read_at'] !== null));
    }

    public function test_student_only_sees_own_notifications(): void
    {
        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->createNotificationFor($other);

        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_transactional_notification_is_emailed_once_and_exposes_payload(): void
    {
        Mail::fake();

        $service = app(NotificationService::class);
        $payload = [
            'message' => 'Seu pagamento foi aprovado.',
            'action_url' => 'minhas-compras',
            'metadata' => ['order_id' => 10],
        ];

        $notification = $service->sendToUser(
            $this->student,
            NotificationSlug::PaymentApproved,
            $payload,
            'payment_approved:10',
        );

        $service->sendToUser(
            $this->student,
            NotificationSlug::PaymentApproved,
            $payload,
            'payment_approved:10',
        );

        $this->assertSame(1, Notification::query()
            ->withoutGlobalScope('company')
            ->where('dedupe_key', 'payment_approved:10')
            ->count());

        Mail::assertSentTimes(TransactionalMail::class, 1);
        Mail::assertSent(
            TransactionalMail::class,
            fn (TransactionalMail $mail) => $mail->hasTo($this->student->email)
                && $mail->mailBody === 'Seu pagamento foi aprovado.',
        );

        $delivery = DB::table('notification_users')
            ->where('notification_id', $notification->id)
            ->where('user_id', $this->student->id)
            ->first();

        $this->assertSame('sent', $delivery->email_status);
        $this->assertSame(1, $delivery->email_attempts);
        $this->assertNotNull($delivery->email_sent_at);

        $response = $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/me/notifications')
            ->assertOk();

        $item = collect($response->json('data'))->firstWhere('id', $notification->id);

        $this->assertSame('Pagamento aprovado', $item['title']);
        $this->assertSame('Seu pagamento foi aprovado.', $item['message']);
        $this->assertSame('minhas-compras', $item['action_url']);
        $this->assertSame('payment', $item['category']);
        $this->assertSame('success', $item['severity']);
    }

    private function createNotificationFor(User $user, ?string $readAt = null): Notification
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);

        $type = TypeNotification::query()->firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => 'system'],
            ['name' => 'Sistema'],
        );

        $notification = Notification::query()->create([
            'company_id' => $this->company->id,
            'team_id' => $team->id,
            'type_notification_id' => $type->id,
            'status' => GeneralStatus::Active->value,
        ]);

        $notification->users()->attach($user->id, [
            'company_id' => $this->company->id,
            'read_at' => $readAt,
        ]);

        return $notification;
    }
}
