<?php

/**
 * Script de teste para FCM
 *
 * Uso:
 * php test-fcm.php "SEU_TOKEN_FCM_AQUI"
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$token = $argv[1] ?? null;

if (! $token) {
    echo "❌ Por favor, forneça um token FCM como argumento.\n";
    echo "Uso: php test-fcm.php \"SEU_TOKEN_FCM_AQUI\"\n";
    echo "\n";
    echo "Para obter um token FCM:\n";
    echo "1. Abra o app Flutter\n";
    echo "2. O token será registrado automaticamente quando você solicitar permissão de notificações\n";
    echo "3. Ou use o comando: php artisan fcm:test --token=SEU_TOKEN\n";
    exit(1);
}

try {
    echo "🔧 Inicializando FcmService...\n";
    $fcm = new \App\Services\FcmService;
    echo "✅ FcmService inicializado com sucesso!\n\n";

    echo "📤 Enviando notificação de teste...\n";
    echo 'Token: '.substr($token, 0, 50)."...\n\n";

    $title = '🧪 Teste de Notificação';
    $body = 'Esta é uma notificação de teste do sistema Vehicle Maintenance!';
    $data = [
        'type' => 'test',
        'timestamp' => now()->toIso8601String(),
        'message' => 'Notificação de teste enviada com sucesso!',
    ];

    $result = $fcm->sendToTokens([$token], $title, $body, $data);

    if ($result) {
        echo "✅ Notificação enviada com sucesso!\n";
        echo "📱 Verifique o dispositivo para ver a notificação.\n";
    } else {
        echo "❌ Falha ao enviar notificação.\n";
        echo "Verifique se o token é válido e se o dispositivo está conectado.\n";
    }
} catch (\Exception $e) {
    echo '❌ Erro: '.$e->getMessage()."\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString()."\n";
    exit(1);
}
