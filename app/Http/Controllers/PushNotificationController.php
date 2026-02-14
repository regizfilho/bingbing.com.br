<?php

namespace App\Http\Controllers;

use App\Models\Notification\PushNotification;
use App\Models\Notification\PushNotificationClick;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PushNotificationController extends Controller
{
    public function __construct(
        private PushNotificationService $service
    ) {}

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'device_info' => 'nullable|array',
        ]);

        $subscription = $this->service->subscribe(
            auth()->id(),
            $validated
        );

        return response()->json([
            'success' => true,
            'subscription' => $subscription
        ]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string',
        ]);

        $success = $this->service->unsubscribe(
            auth()->id(),
            $validated['endpoint']
        );

        return response()->json(['success' => $success]);
    }

    public function click(PushNotification $notification): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['success' => false], 401);
        }

        PushNotificationClick::create([
            'uuid' => Str::uuid(),
            'push_notification_id' => $notification->id,
            'user_id' => auth()->id(),
            'clicked_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}