<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notificação do sino para o app. action_url e action_label são caminhos do portal web e ficam de
 * fora: o app navega pelo type e pelos ids (vehicle_id, maintenance_id).
 *
 * @mixin \Illuminate\Notifications\DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    private const WEB_ONLY_KEYS = ['action_url', 'action_label', 'vehicle_url'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;

        return [
            'id' => $this->id,
            'type' => is_string($data['type'] ?? null) ? $data['type'] : null,
            'title' => is_string($data['title'] ?? null) ? $data['title'] : 'Notificação',
            'body' => is_string($data['body'] ?? null) ? $data['body'] : '',
            'vehicle_id' => is_numeric($data['vehicle_id'] ?? null) ? (int) $data['vehicle_id'] : null,
            'maintenance_id' => is_numeric($data['maintenance_id'] ?? null) ? (int) $data['maintenance_id'] : null,
            'data' => array_diff_key($data, array_flip(self::WEB_ONLY_KEYS)),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
