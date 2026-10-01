<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Para onde uma notificação leva quem a recebeu: a ficha do veículo citado, no portal da própria
 * conta. Usado pelo sino, pela página de notificações e pelo "marcar como lida", para que os três
 * mostrem "Ver veículo" só quando existe uma ficha que a conta pode abrir.
 */
class NotificationLink
{
    /**
     * URL da ficha do veículo, ou null quando a notificação não cita veículo ou o portal da conta
     * não tem ficha (oficina).
     *
     * Ordem: vehicle_id vira a rota da ficha do portal; sem ele, vale vehicle_url se for um caminho
     * deste site ("/usuario/veiculos/7" ou uma URL absoluta do mesmo host). Qualquer outro endereço é
     * ignorado, para o "marcar como lida" nunca redirecionar para fora.
     *
     * @param  DatabaseNotification|array<string, mixed>  $notification
     */
    public static function vehicleUrl(DatabaseNotification|array $notification, User $user): ?string
    {
        $data = $notification instanceof DatabaseNotification ? (array) $notification->data : $notification;
        $vehicleRoute = $user->portal()->vehicleRoute();

        if ($vehicleRoute === null) {
            return null;
        }

        $vehicleId = $data['vehicle_id'] ?? null;

        if (is_numeric($vehicleId) && (int) $vehicleId > 0) {
            return route($vehicleRoute, (int) $vehicleId, absolute: false);
        }

        $vehicleUrl = $data['vehicle_url'] ?? null;

        return is_string($vehicleUrl) && self::isInternalUrl($vehicleUrl) ? $vehicleUrl : null;
    }

    private static function isInternalUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//') && ! str_starts_with($url, '/\\');
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($host)
            && in_array($scheme, ['http', 'https'], true)
            && strcasecmp($host, (string) parse_url(url('/'), PHP_URL_HOST)) === 0;
    }
}
