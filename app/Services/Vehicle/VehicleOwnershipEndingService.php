<?php

namespace App\Services\Vehicle;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleOwnershipEndedByTeamNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A equipe encerra o vínculo de um dono atual com o veículo: vínculo feito por engano, conta errada,
 * carro vendido cujo comprador não tem como enviar o CRLV-e. É a única saída de um vínculo que não
 * depende do próprio dono nem de outra conta com o documento (VehicleOwnershipService::claimExisting).
 *
 * O vínculo não é apagado: `is_current_owner` vira falso, `sale_date` recebe o dia, e ficam gravados
 * quem encerrou e o motivo. O histórico de manutenções continua no veículo (pertence ao chassi), e o
 * ex-dono continua vendo o que declarou, como acontece com quem vende. Sem outro dono atual, o veículo
 * fica livre para ser vinculado de novo.
 */
class VehicleOwnershipEndingService
{
    /**
     * Encerra o vínculo atual de $owner com $vehicle. Devolve false quando $owner não é dono atual do
     * veículo (nada muda).
     */
    public function endByTeam(Vehicle $vehicle, User $owner, User $admin, string $reason): bool
    {
        $ended = DB::table('user_vehicles')
            ->where('vehicle_id', $vehicle->id)
            ->where('user_id', $owner->id)
            ->where('is_current_owner', true)
            ->update([
                'is_current_owner' => false,
                'sale_date' => now()->toDateString(),
                'ownership_ended_by_user_id' => $admin->id,
                'ownership_ended_reason' => $reason,
                'updated_at' => now(),
            ]);

        if ($ended === 0) {
            return false;
        }

        Log::info('Vínculo de propriedade encerrado por um admin.', [
            'admin_id' => $admin->id,
            'vehicle_id' => $vehicle->id,
            'owner_id' => $owner->id,
        ]);

        $owner->notify(new VehicleOwnershipEndedByTeamNotification($vehicle));

        return true;
    }
}
