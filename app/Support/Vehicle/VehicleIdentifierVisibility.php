<?php

namespace App\Support\Vehicle;

use App\Models\User;
use App\Models\UserVehicle;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use WeakMap;

/**
 * Chassi e RENAVAM nas respostas da API: inteiros só para quem passa no VehiclePolicy::update (o
 * dono atual); visitante e qualquer outra conta (dono anterior, lojista em consignação, oficina,
 * admin) recebem os números parciais do VehicleIdentifierMask, como na busca da web
 * (.ai/rules/public-lookup.md). As chaves chassis e renavam continuam no JSON, só o valor muda, e
 * identifiers_masked diz ao app qual dos dois ele recebeu.
 *
 * Ex.: $identifiers = VehicleIdentifierVisibility::fields(VehicleIdentifierVisibility::viewerOf($request), $vehicle);
 */
final class VehicleIdentifierVisibility
{
    /**
     * Decisões já tomadas, por instância do veículo e id de quem vê. Numa lista de manutenções a
     * mesma instância do veículo se repete (eager load do belongsTo), então a consulta da policy
     * roda uma vez por veículo da página, e a decisão some junto com a instância.
     *
     * @var WeakMap<Vehicle, array<int, bool>>|null
     */
    private static ?WeakMap $decisions = null;

    /**
     * Quem está vendo: o token do app ou a sessão da web. A busca pública não passa pelo
     * auth:sanctum, então lá o token é lido direto no guard sanctum.
     */
    public static function viewerOf(Request $request): ?User
    {
        $viewer = $request->user() ?? $request->user('sanctum');

        return $viewer instanceof User ? $viewer : null;
    }

    public static function showsFullIdentifiers(?User $viewer, Vehicle $vehicle): bool
    {
        if ($viewer === null) {
            return false;
        }

        if (self::loadedAsCurrentVehicleOf($viewer, $vehicle)) {
            return true;
        }

        self::$decisions ??= new WeakMap;
        $decisionsForVehicle = self::$decisions[$vehicle] ?? [];

        if (! array_key_exists($viewer->id, $decisionsForVehicle)) {
            $decisionsForVehicle[$viewer->id] = $viewer->can('update', $vehicle);
            self::$decisions[$vehicle] = $decisionsForVehicle;
        }

        return $decisionsForVehicle[$viewer->id];
    }

    /**
     * @return array{chassis: ?string, renavam: ?string, identifiers_masked: bool}
     */
    public static function fields(?User $viewer, Vehicle $vehicle): array
    {
        if (self::showsFullIdentifiers($viewer, $vehicle)) {
            return [
                'chassis' => $vehicle->chassis,
                'renavam' => $vehicle->renavam,
                'identifiers_masked' => false,
            ];
        }

        return [
            'chassis' => VehicleIdentifierMask::chassis($vehicle->chassis),
            'renavam' => VehicleIdentifierMask::renavam($vehicle->renavam),
            'identifiers_masked' => true,
        ];
    }

    /**
     * O veículo veio de $viewer->currentVehicles() (my-vehicles, /me, login): o pivot carregado já
     * diz o mesmo que o VehiclePolicy::update (vínculo desta conta, dono atual, no tenant dela), sem
     * uma consulta por veículo da lista.
     */
    private static function loadedAsCurrentVehicleOf(User $viewer, Vehicle $vehicle): bool
    {
        if ($viewer->tenant_id === null || ! $vehicle->relationLoaded('pivot')) {
            return false;
        }

        $pivot = $vehicle->getRelation('pivot');

        return $pivot instanceof UserVehicle
            && (int) $pivot->user_id === (int) $viewer->id
            && $pivot->is_current_owner === true
            && (int) $pivot->tenant_id === (int) $viewer->tenant_id;
    }
}
