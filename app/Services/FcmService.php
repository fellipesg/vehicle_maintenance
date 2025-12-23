<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Log;

class FcmService
{
    private $messaging;

    public function __construct()
    {
        $credentialsPath = config('firebase.credentials_path');
        
        if (!$credentialsPath || !file_exists(storage_path('app/' . $credentialsPath))) {
            throw new \Exception('Firebase credentials file not found. Please configure FIREBASE_CREDENTIALS_PATH in .env');
        }

        $factory = (new Factory)
            ->withServiceAccount(storage_path('app/' . $credentialsPath));

        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send notification to a single user
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        $tokens = UserFcmToken::where('user_id', $userId)->pluck('token')->toArray();

        if (empty($tokens)) {
            Log::warning("No FCM tokens found for user {$userId}");
            return false;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send notification to multiple tokens
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): bool
    {
        if (empty($tokens)) {
            return false;
        }

        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $report = $this->messaging->sendMulticast($message, $tokens);

            // Remove invalid tokens
            if ($report->hasFailures()) {
                foreach ($report->failures() as $failure) {
                    if ($failure->error()->getCode() === 'messaging/registration-token-not-registered') {
                        UserFcmToken::where('token', $failure->target()->value())->delete();
                    }
                }
            }

            Log::info("FCM notification sent", [
                'successful' => $report->successes()->count(),
                'failed' => $report->failures()->count(),
            ]);

            return $report->successes()->count() > 0;
        } catch (\Exception $e) {
            Log::error("FCM notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to a workshop (all users of the workshop)
     */
    public function sendToWorkshop(int $workshopId, string $title, string $body, array $data = []): bool
    {
        $workshop = \App\Models\Workshop::with('user')->find($workshopId);
        
        if (!$workshop || !$workshop->user) {
            return false;
        }

        return $this->sendToUser($workshop->user->id, $title, $body, $data);
    }

    /**
     * Send maintenance reminder notification
     */
    public function sendMaintenanceReminder(int $userId, array $maintenanceData, array $serviceData, array $workshopData): bool
    {
        $title = "Lembrete de Manutenção";
        $body = "Sua manutenção está próxima do vencimento";
        
        $data = [
            'type' => 'maintenance_reminder',
            'maintenance_id' => (string)$maintenanceData['id'],
            'service_id' => (string)($serviceData['id'] ?? ''),
            'workshop_id' => (string)($workshopData['id'] ?? ''),
        ];

        return $this->sendToUser($userId, $title, $body, $data);
    }

    /**
     * Send new maintenance notification to workshop
     */
    public function sendNewMaintenanceToWorkshop(int $workshopId, array $maintenanceData, array $userData): bool
    {
        $title = "Nova Manutenção Cadastrada";
        $body = "Uma nova manutenção foi cadastrada para sua oficina por {$userData['name']}";
        
        $data = [
            'type' => 'new_maintenance',
            'maintenance_id' => (string)$maintenanceData['id'],
            'workshop_id' => (string)$workshopId,
            'user_id' => (string)$userData['id'],
        ];

        return $this->sendToWorkshop($workshopId, $title, $body, $data);
    }
}
