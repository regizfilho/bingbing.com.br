<?php

namespace App\Http\Controllers;

use App\Models\Notification\PushNotification;
use App\Models\Notification\PushNotificationClick;
use App\Models\Notification\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PushNotificationController extends Controller
{
    public function __construct(
        private PushNotificationService $service
    ) {}

    public function subscribe(Request $request): JsonResponse
    {
        Log::info('📥 Subscribe request recebida', [
            'has_auth' => auth()->check(),
            'user_id' => auth()->id(),
            'endpoint' => $request->input('endpoint'),
        ]);

        if (!auth()->check()) {
            Log::warning('❌ Subscribe sem autenticação');
            return response()->json(['success' => false, 'message' => 'Não autenticado'], 401);
        }

        try {
            $validated = $request->validate([
                'endpoint' => 'required|string',
                'keys' => 'required|array',
                'keys.p256dh' => 'required|string',
                'keys.auth' => 'required|string',
                'device_info' => 'nullable|string',
            ]);

            Log::info('✅ Validação passou', [
                'endpoint' => $validated['endpoint'],
                'has_keys' => isset($validated['keys']),
            ]);

            // Verifica se já existe uma subscription ativa para este user + endpoint
            $existingSubscription = PushSubscription::where('user_id', auth()->id())
                ->where('endpoint', $validated['endpoint'])
                ->first();

            if ($existingSubscription) {
                Log::info('🔄 Subscription existente encontrada, atualizando...', [
                    'subscription_id' => $existingSubscription->id,
                    'is_active' => $existingSubscription->is_active,
                ]);

                // Atualiza a subscription existente
                $existingSubscription->update([
                    'public_key' => $validated['keys']['p256dh'],
                    'auth_token' => $validated['keys']['auth'],
                    'device_info' => $validated['device_info'] ?? $existingSubscription->device_info,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);

                Log::info('✅ Subscription atualizada com sucesso', [
                    'subscription_id' => $existingSubscription->id,
                ]);

                return response()->json([
                    'success' => true,
                    'subscription' => $existingSubscription->fresh()
                ]);
            }

            // Se não existe, cria uma nova
            $subscription = PushSubscription::create([
                'uuid' => Str::uuid(),
                'user_id' => auth()->id(),
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'device_info' => $validated['device_info'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]);

            Log::info('✅ Subscription criada com sucesso', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
            ]);

            return response()->json([
                'success' => true,
                'subscription' => $subscription
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Erro de validação', [
                'errors' => $e->errors(),
                'input' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ Erro ao criar subscription', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar inscrição: ' . $e->getMessage()
            ], 500);
        }
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['success' => false, 'message' => 'Não autenticado'], 401);
        }

        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        $updated = PushSubscription::where('user_id', auth()->id())
            ->where('endpoint', $validated['endpoint'])
            ->update(['is_active' => false]);

        return response()->json(['success' => $updated > 0]);
    }

    public function click(PushNotification $notification): JsonResponse
    {
        Log::info('Requisição de click recebida (POST)', [
            'notification_uuid' => $notification->uuid,
            'notification_id' => $notification->id,
            'has_auth' => auth()->check(),
            'session_id' => session()->getId(),
        ]);

        if (!auth()->check()) {
            Log::warning('Click sem autenticação', [
                'notification_uuid' => $notification->uuid
            ]);
            return response()->json(['success' => false, 'message' => 'Não autenticado'], 401);
        }

        try {
            $click = PushNotificationClick::create([
                'uuid' => Str::uuid(),
                'push_notification_id' => $notification->id,
                'user_id' => auth()->id(),
                'clicked_at' => now(),
            ]);

            Log::info('Click registrado com sucesso', [
                'click_id' => $click->id,
                'notification_uuid' => $notification->uuid,
                'notification_id' => $notification->id,
                'user_id' => auth()->id()
            ]);

            return response()->json(['success' => true, 'click_id' => $click->id]);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar click', [
                'notification_uuid' => $notification->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Erro ao registrar'], 500);
        }
    }

    public function clickGet(PushNotification $notification): JsonResponse
    {
        Log::info('Requisição de click recebida (GET)', [
            'notification_uuid' => $notification->uuid,
            'notification_id' => $notification->id,
            'has_auth' => auth()->check(),
            'session_id' => session()->getId(),
        ]);

        if (!auth()->check()) {
            Log::warning('Click GET sem autenticação', [
                'notification_uuid' => $notification->uuid
            ]);
            return response()->json(['success' => false, 'message' => 'Não autenticado'], 401);
        }

        try {
            $recentClick = PushNotificationClick::where('push_notification_id', $notification->id)
                ->where('user_id', auth()->id())
                ->where('clicked_at', '>', now()->subMinutes(5))
                ->first();

            if ($recentClick) {
                Log::info('Click duplicado ignorado', [
                    'notification_uuid' => $notification->uuid,
                    'user_id' => auth()->id(),
                    'recent_click_id' => $recentClick->id
                ]);
                
                return response()->json([
                    'success' => true, 
                    'click_id' => $recentClick->id,
                    'duplicate' => true
                ]);
            }

            $click = PushNotificationClick::create([
                'uuid' => Str::uuid(),
                'push_notification_id' => $notification->id,
                'user_id' => auth()->id(),
                'clicked_at' => now(),
            ]);

            Log::info('Click GET registrado com sucesso', [
                'click_id' => $click->id,
                'notification_uuid' => $notification->uuid,
                'notification_id' => $notification->id,
                'user_id' => auth()->id()
            ]);

            return response()->json(['success' => true, 'click_id' => $click->id]);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar click GET', [
                'notification_uuid' => $notification->uuid,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erro ao registrar'], 500);
        }
    }
}