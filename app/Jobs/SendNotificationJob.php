<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder for asynchronous notification delivery (email/WhatsApp).
 * Kept queueable so external providers are never called inline.
 */
class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $notificationId,
        public int $userId,
        public string $channel = 'mail',
    ) {}

    public function handle(): void
    {
        $notification = Notification::query()->with('type')->find($this->notificationId);
        $user = User::query()->find($this->userId);

        if (! $notification || ! $user) {
            return;
        }

        // Delivery is intentionally abstracted: integrate mail/WhatsApp here.
        Log::info('notification.dispatched', [
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'channel' => $this->channel,
            'slug' => $notification->type?->slug,
        ]);
    }
}
