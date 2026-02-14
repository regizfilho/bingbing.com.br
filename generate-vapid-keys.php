<?php

/**
 * Script para Gerar Chaves VAPID
 * 
 * Execute este arquivo UMA ÚNICA VEZ para gerar suas chaves VAPID
 * 
 * Uso:
 * php generate-vapid-keys.php
 */

require __DIR__ . '/vendor/autoload.php';

use Minishlink\WebPush\VAPID;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║           GERADOR DE CHAVES VAPID                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    $keys = VAPID::createVapidKeys();
    
    echo "✅ Chaves geradas com sucesso!\n\n";
    echo "Copie as linhas abaixo para o seu arquivo .env:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Pedir o subject ao usuário
    echo "Digite o SUBJECT (email ou URL do seu site):\n";
    echo "Exemplos: mailto:admin@seusite.com ou https://seusite.com\n";
    echo "SUBJECT: ";
    
    $subject = trim(fgets(STDIN));
    
    // Validar formato
    if (!str_starts_with($subject, 'mailto:') && !str_starts_with($subject, 'http://') && !str_starts_with($subject, 'https://')) {
        echo "\n⚠️  ATENÇÃO: O subject deve começar com 'mailto:', 'http://' ou 'https://'\n";
        echo "Usando formato correto automaticamente...\n\n";
        
        // Tentar corrigir automaticamente
        if (filter_var($subject, FILTER_VALIDATE_EMAIL)) {
            $subject = "mailto:" . $subject;
        } else {
            $subject = "https://" . $subject;
        }
    }
    
    echo "\n";
    echo "# VAPID Configuration - Push Notifications\n";
    echo "VAPID_SUBJECT=" . $subject . "\n";
    echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
    echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "\n";
    echo "📝 PRÓXIMOS PASSOS:\n\n";
    echo "1. Copie as 3 linhas acima\n";
    echo "2. Cole no seu arquivo .env\n";
    echo "3. Adicione ao config/services.php:\n\n";
    echo "   'vapid' => [\n";
    echo "       'subject' => env('VAPID_SUBJECT'),\n";
    echo "       'public_key' => env('VAPID_PUBLIC_KEY'),\n";
    echo "       'private_key' => env('VAPID_PRIVATE_KEY'),\n";
    echo "   ],\n\n";
    echo "4. Execute: php artisan config:clear\n";
    echo "5. Pronto! Seu sistema de push está configurado.\n";
    echo "\n";
    
    // Salvar em arquivo também
    $envContent = "# VAPID Configuration - Push Notifications\n";
    $envContent .= "VAPID_SUBJECT=" . $subject . "\n";
    $envContent .= "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
    $envContent .= "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
    
    file_put_contents(__DIR__ . '/.env.vapid', $envContent);
    echo "💾 Chaves também salvas em: .env.vapid\n";
    echo "   (você pode copiar deste arquivo se preferir)\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "\n❌ Erro ao gerar chaves: " . $e->getMessage() . "\n";
    echo "\nVerifique se você instalou a biblioteca:\n";
    echo "composer require minishlink/web-push\n\n";
    exit(1);
}

echo "✨ Concluído!\n\n";