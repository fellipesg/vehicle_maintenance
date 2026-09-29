<?php

namespace App\Support\Vehicle;

use App\Models\Maintenance;
use Illuminate\Support\Collection;

/**
 * Regras do histórico de manutenções na ficha do veículo, iguais em todos os portais:
 *
 * - Ordem: a mesma da linha do tempo (App\Services\Vehicle\VehicleTimelineBuilder), da mais antiga
 *   para a mais recente: quilometragem crescente, depois data e id. Manutenção sem quilometragem não
 *   entra na linha do tempo; na lista ela vai para o fim, por data.
 * - Filtro de procedência (?verified): '1' = Selo da oficina, '0' = Declaradas, vazio = Todas.
 */
final class VehicleMaintenanceHistory
{
    public const FILTER_ALL = '';

    public const FILTER_SEALED = '1';

    public const FILTER_DECLARED = '0';

    /**
     * @template TMaintenance of Maintenance
     *
     * @param  iterable<TMaintenance>  $maintenances
     * @return Collection<int, TMaintenance>
     */
    public static function inTimelineOrder(iterable $maintenances): Collection
    {
        return collect($maintenances)
            ->sort(function (Maintenance $left, Maintenance $right): int {
                $leftHasKm = $left->kilometers !== null;
                $rightHasKm = $right->kilometers !== null;

                if ($leftHasKm !== $rightHasKm) {
                    return $leftHasKm ? -1 : 1;
                }

                if ($leftHasKm && (int) $left->kilometers !== (int) $right->kilometers) {
                    return (int) $left->kilometers <=> (int) $right->kilometers;
                }

                return [$left->maintenance_date?->format('Y-m-d') ?? '', (int) $left->id]
                    <=> [$right->maintenance_date?->format('Y-m-d') ?? '', (int) $right->id];
            })
            ->values();
    }

    /**
     * Valor do filtro de procedência a partir da query string: '1', '0' ou '' (todas).
     */
    public static function normalizeFilter(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return in_array($value, [self::FILTER_SEALED, self::FILTER_DECLARED], true) ? $value : self::FILTER_ALL;
    }

    public static function matchesFilter(Maintenance|bool $maintenanceOrVerified, string $filter): bool
    {
        $isVerified = is_bool($maintenanceOrVerified) ? $maintenanceOrVerified : $maintenanceOrVerified->isVerified();

        return match (self::normalizeFilter($filter)) {
            self::FILTER_SEALED => $isVerified,
            self::FILTER_DECLARED => ! $isVerified,
            default => true,
        };
    }

    /**
     * Título do estado vazio quando o filtro não deixa nenhuma manutenção.
     */
    public static function emptyFilterTitle(string $filter): string
    {
        return self::normalizeFilter($filter) === self::FILTER_SEALED
            ? 'Nenhuma manutenção com Selo da oficina'
            : 'Nenhuma manutenção declarada';
    }

    /**
     * "1 manutenção", "3 manutenções".
     */
    public static function countLabel(int $count): string
    {
        return number_format($count, 0, ',', '.').' '.($count === 1 ? 'manutenção' : 'manutenções');
    }

    /**
     * "1 declarada", "2 declaradas".
     */
    public static function declaredLabel(int $count): string
    {
        return number_format($count, 0, ',', '.').' '.($count === 1 ? 'declarada' : 'declaradas');
    }
}
