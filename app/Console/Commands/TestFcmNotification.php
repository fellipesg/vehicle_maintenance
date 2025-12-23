<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FcmService;
use App\Models\UserFcmToken;
use App\Models\User;

class TestFcmNotification extends Command
{
    protected $signature = 'fcm:test {--user-id= : ID do usuário para enviar} {--token= : Token FCM direto para teste}';
    protected $description = 'Envia uma notificação de teste via FCM';

    public function handle()
    {
        try {
            $fcm = new FcmService();
            $this->info('✅ FcmService inicializado com sucesso!');

            $title = 'Teste de Notificação';
            $body = 'Esta é uma notificação de teste do sistema Vehicle Maintenance';
            $data = [
                'type' => 'test',
                'timestamp' => now()->toIso8601String(),
            ];

            if ($this->option('token')) {
                // Teste com token direto
                $token = $this->option('token');
                $this->info("Enviando notificação para token: {$token}");
                $result = $fcm->sendToTokens([$token], $title, $body, $data);
                
                if ($result) {
                    $this->info('✅ Notificação enviada com sucesso!');
                } else {
                    $this->error('❌ Falha ao enviar notificação');
                }
            } elseif ($this->option('user-id')) {
                // Teste para um usuário específico
                $userId = $this->option('user-id');
                $user = User::find($userId);
                
                if (!$user) {
                    $this->error("Usuário com ID {$userId} não encontrado");
                    return 1;
                }

                $tokens = UserFcmToken::where('user_id', $userId)->get();
                
                if ($tokens->isEmpty()) {
                    $this->warn("⚠️  Nenhum token FCM encontrado para o usuário {$userId} ({$user->name})");
                    $this->info('Para registrar um token, use o app Flutter ou crie manualmente no banco.');
                    return 1;
                }

                $this->info("Enviando notificação para usuário: {$user->name} (ID: {$userId})");
                $this->info("Tokens encontrados: {$tokens->count()}");
                
                $result = $fcm->sendToUser($userId, $title, $body, $data);
                
                if ($result) {
                    $this->info('✅ Notificação enviada com sucesso!');
                } else {
                    $this->error('❌ Falha ao enviar notificação');
                }
            } else {
                // Listar tokens disponíveis
                $tokens = UserFcmToken::with('user')->get();
                
                if ($tokens->isEmpty()) {
                    $this->warn('⚠️  Nenhum token FCM cadastrado ainda.');
                    $this->info('');
                    $this->info('Para testar, você pode:');
                    $this->info('1. Registrar um token do app Flutter');
                    $this->info('2. Usar: php artisan fcm:test --token=SEU_TOKEN_AQUI');
                    $this->info('3. Usar: php artisan fcm:test --user-id=ID_DO_USUARIO');
                    return 1;
                }

                $this->info('Tokens FCM cadastrados:');
                $this->table(
                    ['ID', 'User ID', 'Nome', 'Token (primeiros 50 chars)', 'Device'],
                    $tokens->map(function ($token) {
                        return [
                            $token->id,
                            $token->user_id,
                            $token->user->name ?? 'N/A',
                            substr($token->token, 0, 50) . '...',
                            $token->device_type ?? 'N/A',
                        ];
                    })->toArray()
                );

                $this->info('');
                $this->info('Para enviar uma notificação de teste, use:');
                $this->info('php artisan fcm:test --user-id=ID_DO_USUARIO');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Erro: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
