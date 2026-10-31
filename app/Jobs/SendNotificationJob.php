<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use App\Services\CompanyMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $notificationId,
        public int $userId,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(CompanyMailService $mail): void
    {
        $notification = Notification::query()
            ->withoutGlobalScope('company')
            ->with('type')
            ->find($this->notificationId);

        $user = User::query()
            ->withoutGlobalScope('company')
            ->find($this->userId);

        if (! $notification || ! $user || $notification->company_id !== $user->company_id) {
            return;
        }

        $pivot = DB::table('notification_users')
            ->where('notification_id', $notification->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $pivot || $pivot->email_sent_at !== null || $pivot->email_status === 'sent') {
            return;
        }

        $attempts = (int) $pivot->email_attempts;

        if ($attempts >= $this->tries) {
            $this->updateDelivery($notification->id, $user->id, 'failed', $attempts, null, 'Limite de tentativas atingido.');

            return;
        }

        try {
            $sent = $mail->sendNotification($notification, $user);

            $this->updateDelivery(
                $notification->id,
                $user->id,
                'sent',
                $attempts + 1,
                $this->messageId($sent),
                null,
                now(),
            );
        } catch (Throwable $e) {
            $this->updateDelivery(
                $notification->id,
                $user->id,
                'failed',
                $attempts + 1,
                null,
                mb_substr($e->getMessage(), 0, 1000),
            );

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->updateDelivery(
            $this->notificationId,
            $this->userId,
            'failed',
            null,
            null,
            mb_substr($e->getMessage(), 0, 1000),
        );
    }

    private function updateDelivery(
        int $notificationId,
        int $userId,
        string $status,
        ?int $attempts = null,
        ?string $messageId = null,
        ?string $error = null,
        mixed $sentAt = null,
    ): void {
        $values = [
            'email_status' => $status,
            'email_error' => $error,
            'updated_at' => now(),
        ];

        if ($attempts !== null) {
            $values['email_attempts'] = $attempts;
        }

        if ($messageId !== null) {
            $values['email_message_id'] = $messageId;
        }

        if ($sentAt !== null) {
            $values['email_sent_at'] = $sentAt;
        }

        DB::table('notification_users')
            ->where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->update($values);
    }

    private function messageId(mixed $sent): ?string
    {
        if (! $sent || ! method_exists($sent, 'getMessageId')) {
            return null;
        }

        $id = $sent->getMessageId();

        return is_string($id) && $id !== '' ? $id : null;
    }
}
