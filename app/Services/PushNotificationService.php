<?php

namespace App\Services;

use App\Models\Notification\PushNotification;
use App\Models\Notification\PushSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    public function processNotification(PushNotification $notification): void
    {
        try {
            Log::info('Iniciando processamento de notificação', [
                'notification_id' => $notification->id,
                'target_type' => $notification->target_type,
                'target_filters' => $notification->target_filters
            ]);

            $notification->update(['status' => PushNotification::STATUS_PROCESSING]);

            $subscriptions = $this->getTargetSubscriptions($notification);

            Log::info('Subscriptions encontradas', [
                'notification_id' => $notification->id,
                'count' => $subscriptions->count()
            ]);

            if ($subscriptions->isEmpty()) {
                Log::warning('Nenhuma subscription encontrada', [
                    'notification_id' => $notification->id
                ]);
                
                $notification->update([
                    'status' => PushNotification::STATUS_COMPLETED,
                    'sent_at' => now(),
                    'total_sent' => 0,
                    'total_success' => 0,
                ]);
                return;
            }

            $auth = [
                'VAPID' => [
                    'subject' => config('app.url'),
                    'publicKey' => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ]
            ];

            $webPush = new WebPush($auth);
            $webPush->setAutomaticPadding(false);

            $payload = json_encode([
                'title' => $notification->title,
                'body' => $notification->body,
                'icon' => $notification->icon,
                'badge' => $notification->badge,
                'url' => $notification->url,
                'data' => array_merge($notification->data ?? [], [
                    'notification_id' => $notification->uuid, // Usar UUID em vez de ID
                ]),
            ]);

            $totalSent = 0;
            $totalSuccess = 0;
            $totalFailed = 0;

            foreach ($subscriptions as $sub) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'publicKey' => $sub->public_key,
                        'authToken' => $sub->auth_token,
                    ]);

                    $webPush->queueNotification($subscription, $payload);
                    $totalSent++;
                    
                    Log::debug('Notificação adicionada à fila', [
                        'subscription_id' => $sub->id,
                        'user_id' => $sub->user_id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Erro ao adicionar notificação na fila', [
                        'subscription_id' => $sub->id,
                        'error' => $e->getMessage()
                    ]);
                    $totalFailed++;
                }
            }

            Log::info('Iniciando flush de notificações', [
                'notification_id' => $notification->id,
                'total_queued' => $totalSent
            ]);

            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getEndpoint();

                if ($report->isSuccess()) {
                    $totalSuccess++;
                    PushSubscription::where('endpoint', $endpoint)
                        ->update(['last_used_at' => now()]);
                        
                    Log::debug('Notificação enviada com sucesso', [
                        'endpoint' => substr($endpoint, 0, 50) . '...'
                    ]);
                } else {
                    $totalFailed++;
                    
                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::where('endpoint', $endpoint)
                            ->update(['is_active' => false]);
                        
                        Log::warning('Subscription expirada', [
                            'endpoint' => substr($endpoint, 0, 50) . '...'
                        ]);
                    }

                    Log::error('Falha ao enviar notificação', [
                        'endpoint' => substr($endpoint, 0, 50) . '...',
                        'reason' => $report->getReason(),
                        'expired' => $report->isSubscriptionExpired()
                    ]);
                }
            }

            Log::info('Processamento concluído', [
                'notification_id' => $notification->id,
                'total_sent' => $totalSent,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed
            ]);

            $notification->update([
                'status' => PushNotification::STATUS_COMPLETED,
                'sent_at' => now(),
                'total_sent' => $totalSent,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao processar notificação', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);

            $notification->update(['status' => PushNotification::STATUS_FAILED]);
        }
    }

    private function getTargetSubscriptions(PushNotification $notification)
    {
        $query = PushSubscription::where('is_active', true)
            ->select(['id', 'user_id', 'endpoint', 'public_key', 'auth_token']); // Selecionar apenas campos necessários

        if ($notification->target_type === PushNotification::TARGET_USER) {
            $userIds = $notification->target_filters['user_ids'] ?? [];
            
            if (empty($userIds)) {
                Log::warning('Notificação de usuário sem IDs especificados', [
                    'notification_id' => $notification->id
                ]);
                return collect();
            }
            
            $query->whereIn('user_id', $userIds);
            
            Log::info('Buscando subscriptions para usuários específicos', [
                'notification_id' => $notification->id,
                'user_ids' => $userIds
            ]);
        } else {
            Log::info('Buscando todas as subscriptions ativas (broadcast)', [
                'notification_id' => $notification->id
            ]);
        }

        // Usar chunk para processar em lotes se houver muitas subscriptions
        $subscriptions = $query->get();
        
        Log::info('Subscriptions retornadas', [
            'notification_id' => $notification->id,
            'count' => $subscriptions->count(),
            'user_ids' => $subscriptions->pluck('user_id')->unique()->toArray()
        ]);

        return $subscriptions;
    }

    public function notifyUser(int $userId, string $title, string $body, ?string $url = null): int
    {
        $subscriptions = PushSubscription::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $auth = [
            'VAPID' => [
                'subject' => config('app.url'),
                'publicKey' => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ]
        ];

        $webPush = new WebPush($auth);
        $webPush->setAutomaticPadding(false);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/imgs/ico.png',
            'badge' => '/imgs/ico.png',
            'url' => $url,
        ]);

        $count = 0;

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                ]);

                $webPush->queueNotification($subscription, $payload);
                $count++;
            } catch (\Exception $e) {
                Log::error('Erro ao enviar notificação direta', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint', $report->getEndpoint())
                    ->update(['is_active' => false]);
            }
        }

        return $count;
    }

    public function getSubscriptionStats(): array
    {
        return [
            'total_users_subscribed' => PushSubscription::where('is_active', true)
                ->distinct('user_id')
                ->count('user_id'),
            'active_subscriptions' => PushSubscription::where('is_active', true)->count(),
            'total_subscriptions' => PushSubscription::count(),
        ];
    }

    public function getNotificationStats(): array
    {
        return [
            'total_sent' => PushNotification::sum('total_sent'),
            'total_success' => PushNotification::sum('total_success'),
            'total_failed' => PushNotification::sum('total_failed'),
            'pending_notifications' => PushNotification::where('status', PushNotification::STATUS_PENDING)->count(),
        ];
    }
}