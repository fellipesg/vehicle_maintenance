<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WelcomePushNotifier
{
    public function send(User $user): void
    {
        try {
            $firstName = trim((string) Str::of($user->name)->explode(' ')->first());
            $greeting = $firstName !== '' ? $firstName : $user->name;

            (new FcmService)->sendToUser(
                $user->id,
                'Bem-vindo à Revisalog! 🚗',
                "Olá {$greeting}! Sua conta foi criada com sucesso. Comece a registrar as manutenções do seu veículo.",
                [
                    'type' => 'welcome',
                    'user_id' => (string) $user->id,
                ]
            );
        } catch (\Exception $e) {
            Log::warning('Failed to send welcome notification: '.$e->getMessage());
        }
    }
}
