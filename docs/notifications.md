<?php

// ============================================================================
// EXEMPLOS DE USO DO NotificationMessages
// ============================================================================

use App\Services\NotificationMessages;
use App\Services\PushNotificationService;

$pushService = app(PushNotificationService::class);

// 1️⃣ COMPRA DE CRÉDITOS (já implementado acima)
$message = NotificationMessages::creditPurchase(1000, 'Pacote Básico');
$pushService->notifyUser(
    $userId,
    $message['title'],
    $message['body'],
    route('wallet.index')
);

// 2️⃣ VITÓRIA EM JOGO
$message = NotificationMessages::gameVictory('Truco Mineiro', 50.00);
$pushService->notifyUser(
    $userId,
    $message['title'],
    $message['body'],
    route('games.index')
);

// 3️⃣ BOAS-VINDAS
$message = NotificationMessages::welcome($user->name);
$pushService->notifyUser(
    $userId,
    $message['title'],
    $message['body'],
    route('dashboard')
);

// 4️⃣ SALDO BAIXO (broadcast para todos com saldo < 100)
$lowBalanceUsers = User::whereHas('wallet', function($q) {
    $q->where('balance', '<', 100);
})->pluck('id')->toArray();

$notification = PushNotification::create([
    'uuid' => Str::uuid(),
    'created_by' => 1, // ID admin
    'title' => '⚠️ Saldo Baixo!',
    'body' => 'Seus créditos estão acabando! Recarregue agora e continue jogando!',
    'url' => route('wallet.index'),
    'icon' => '/imgs/ico.png',
    'badge' => '/imgs/ico.png',
    'target_type' => 'user',
    'target_filters' => ['user_ids' => $lowBalanceUsers],
    'status' => 'pending',
]);

$pushService->processNotification($notification);

// 5️⃣ CUPOM APLICADO (toast + push)
$message = NotificationMessages::couponApplied('PROMO50', 25.00);
$pushService->notifyUser(
    $userId,
    $message['title'],
    $message['body'],
    route('wallet.index')
);

// 6️⃣ PROMOÇÃO (broadcast para todos)
$message = NotificationMessages::promotion(
    'Mega Promoção de Fim de Semana!',
    'Pacotes com 50% OFF até domingo! Aproveite agora!'
);

$notification = PushNotification::create([
    'uuid' => Str::uuid(),
    'created_by' => 1,
    'title' => $message['title'],
    'body' => $message['body'],
    'url' => route('wallet.index'),
    'icon' => '/imgs/ico.png',
    'badge' => '/imgs/ico.png',
    'target_type' => 'all',
    'target_filters' => null,
    'status' => 'pending',
]);

$pushService->processNotification($notification);