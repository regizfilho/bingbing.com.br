<?php

namespace App\Services;

use App\Models\Notification\PushSubscription;
use App\Models\Notification\PushNotification;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription as WebPushSubscription;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct()
    {
        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.vapid.subject'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ]);
    }

    public function subscribe(int $userId, array $subscriptionData): PushSubscription
    {
        $keys = $subscriptionData['keys'] ?? [];

        return PushSubscription::updateOrCreate(
            [
                'user_id' => $userId,
                'endpoint' => $subscriptionData['endpoint'],
            ],
            [
                'public_key' => $keys['p256dh'] ?? '',
                'auth_token' => $keys['auth'] ?? '',
                'device_info' => $subscriptionData['device_info'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    public function unsubscribe(int $userId, string $endpoint): bool
    {
        return PushSubscription::where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete() > 0;
    }

    public function send(PushSubscription $subscription, array $payload): bool
    {
        try {
            $webPushSubscription = WebPushSubscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => [
                    'p256dh' => $subscription->public_key,
                    'auth' => $subscription->auth_token,
                ],
            ]);

            $this->webPush->queueNotification($webPushSubscription, json_encode($payload));

            foreach ($this->webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $subscription->update(['last_used_at' => now()]);
                    return true;
                } else {
                    if ($report->isSubscriptionExpired()) {
                        $subscription->deactivate();
                    }
                    return false;
                }
            }

            return false;
        } catch (\Exception $e) {
            \Log::error('Push notification send error: ' . $e->getMessage());
            return false;
        }
    }

    public function sendToUser(int $userId, array $payload): int
    {
        $subscriptions = PushSubscription::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $successCount = 0;

        foreach ($subscriptions as $subscription) {
            if ($this->send($subscription, $payload)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    public function sendToAll(array $payload): int
    {
        $subscriptions = PushSubscription::where('is_active', true)->get();

        $successCount = 0;

        foreach ($subscriptions as $subscription) {
            if ($this->send($subscription, $payload)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    public function processNotification(PushNotification $notification): void
    {
        $notification->update(['status' => PushNotification::STATUS_PROCESSING]);

        $payload = [
            'title' => $notification->title,
            'body' => $notification->body,
            'icon' => $notification->icon ?? '/imgs/ico.png',
            'badge' => $notification->badge ?? '/imgs/ico.png',
            'data' => array_merge($notification->data ?? [], [
                'url' => $notification->url ?? '/',
                'notification_id' => $notification->id,
            ]),
        ];

        $successCount = match ($notification->target_type) {
            PushNotification::TARGET_ALL => $this->sendToAll($payload),
            PushNotification::TARGET_USER => $this->sendToUsers($notification->target_filters['user_ids'] ?? [], $payload),
            default => 0,
        };

        $notification->update([
            'total_sent' => $successCount,
            'total_success' => $successCount,
            'sent_at' => now(),
            'status' => $successCount > 0 ? PushNotification::STATUS_COMPLETED : PushNotification::STATUS_FAILED,
        ]);
    }

    private function sendToUsers(array $userIds, array $payload): int
    {
        $subscriptions = PushSubscription::whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->get();

        $successCount = 0;

        foreach ($subscriptions as $subscription) {
            if ($this->send($subscription, $payload)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    public function notifyUser(int $userId, string $title, string $body, ?string $url = null, array $data = []): int
    {
        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/imgs/ico.png',
            'badge' => '/imgs/ico.png',
            'data' => array_merge($data, [
                'url' => $url ?? '/',
            ]),
        ];

        return $this->sendToUser($userId, $payload);
    }

    public function broadcast(string $title, string $body, ?string $url = null, array $data = []): int
    {
        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/imgs/ico.png',
            'badge' => '/imgs/ico.png',
            'data' => array_merge($data, [
                'url' => $url ?? '/',
            ]),
        ];

        return $this->sendToAll($payload);
    }

    public function getSubscriptionStats(): array
    {
        return [
            'total_subscriptions' => PushSubscription::count(),
            'active_subscriptions' => PushSubscription::where('is_active', true)->count(),
            'inactive_subscriptions' => PushSubscription::where('is_active', false)->count(),
            'total_users_subscribed' => PushSubscription::where('is_active', true)->distinct('user_id')->count('user_id'),
        ];
    }

    public function getNotificationStats(): array
    {
        return [
            'total_sent' => PushNotification::where('status', PushNotification::STATUS_COMPLETED)->sum('total_sent'),
            'total_success' => PushNotification::where('status', PushNotification::STATUS_COMPLETED)->sum('total_success'),
            'total_failed' => PushNotification::where('status', PushNotification::STATUS_FAILED)->count(),
            'pending_notifications' => PushNotification::where('status', PushNotification::STATUS_PENDING)->count(),
        ];
    }
}