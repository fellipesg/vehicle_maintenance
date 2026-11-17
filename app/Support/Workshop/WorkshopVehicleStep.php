<?php

namespace App\Support\Workshop;

use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\Vehicle\VehicleLookupResult;
use App\Support\VehiclePlateSearch;
use Illuminate\Http\Request;

/**
 * Etapa 1 das telas da oficina que começam escolhendo um veículo: a OS (/oficina/manutencoes/nova) e
 * a vistoria cautelar (/oficina/vistorias/nova). A placa (?license_plate=) ou o chassi (?chassis=)
 * confirma o veículo, e só então o resto do formulário aparece.
 *
 * Mora aqui, e não em cada controller, por causa de `showsFullVehicle`: é o mascaramento de
 * .ai/rules/public-lookup.md, a decisão que impede uma oficina aprovada sem vínculo com o veículo de
 * ver a placa atual e o hodômetro que nenhuma tela pública mostra. Duas cópias da mesma conta é
 * exatamente a forma de a segunda tela esquecer uma linha dela.
 *
 * Depois de um erro de validação, o dado enviado (old input) vale quando a URL não traz nada.
 */
final class WorkshopVehicleStep
{
    /**
     * A etapa 1 sem nenhuma busca, para a tela que não vai mostrar resultado — a vistoria de uma
     * conta sem a funcionalidade, por exemplo. Consultar placa para depois não mostrar nada seria
     * dar o oráculo de enumeração de graça a quem a tela já recusou.
     *
     * @return array{vehicle: Vehicle|null, licensePlate: string, chassis: string, chassisOwned: bool, chassisInvalid: bool, creatingVehicle: bool, lookup: VehicleLookupResult|null, vehicleHasOwner: bool, showsFullVehicle: bool}
     */
    public static function none(): array
    {
        return [
            'vehicle' => null,
            'licensePlate' => '',
            'chassis' => '',
            'chassisOwned' => false,
            'chassisInvalid' => false,
            'creatingVehicle' => false,
            'lookup' => null,
            'vehicleHasOwner' => false,
            'showsFullVehicle' => false,
        ];
    }

    /**
     * @return array{vehicle: Vehicle|null, licensePlate: string, chassis: string, chassisOwned: bool, chassisInvalid: bool, creatingVehicle: bool, lookup: VehicleLookupResult|null, vehicleHasOwner: bool, showsFullVehicle: bool}
     */
    public static function resolve(Request $request, ?Workshop $workshop): array
    {
        $chassisInput = $request->query('chassis');
        $chassisInput = is_scalar($chassisInput) && (string) $chassisInput !== '' ? (string) $chassisInput : (string) $request->old('chassis', '');
        $chassis = substr(Vehicle::normalizeChassis($chassisInput), 0, 17);

        $plateInput = $request->query('license_plate');
        $plateInput = is_scalar($plateInput) && (string) $plateInput !== '' ? (string) $plateInput : (string) $request->old('license_plate', '');
        $licensePlate = $chassis === '' ? substr(VehiclePlateSearch::normalize($plateInput), 0, 10) : '';

        $lookup = $licensePlate !== '' ? VehiclePlateSearch::findByPlate($licensePlate) : null;
        $vehicle = $lookup?->vehicle;
        $chassisOwned = false;
        $creatingVehicle = false;
        $chassisInvalid = false;

        if ($chassis !== '') {
            if (strlen($chassis) !== 17) {
                $chassisInvalid = true;
            } else {
                $vehicle = Vehicle::findByChassis($chassis);

                if ($vehicle !== null && $vehicle->hasCurrentOwner()) {
                    $chassisOwned = true;
                    $vehicle = null;
                } else {
                    $creatingVehicle = $vehicle === null;
                }
            }
        }

        $vehicleHasOwner = $vehicle !== null && $vehicle->hasCurrentOwner();
        // Só a consulta exata de 17 caracteres resolve veículo pelo chassi (chassi curto cai em
        // $chassisInvalid), e veículo com dono já saiu daqui como $chassisOwned: chegar com veículo
        // por este caminho é a oficina estar com o carro ou com o documento na mão.
        $foundByFullChassis = $chassis !== '' && $vehicle !== null;

        return [
            'vehicle' => $vehicle,
            'licensePlate' => $licensePlate,
            'chassis' => $chassis,
            'chassisOwned' => $chassisOwned,
            'chassisInvalid' => $chassisInvalid,
            'creatingVehicle' => $creatingVehicle,
            'lookup' => $lookup,
            'vehicleHasOwner' => $vehicleHasOwner,
            'showsFullVehicle' => $vehicle === null || WorkshopVehicleVisibility::showsFullVehicle(
                $workshop,
                $vehicle,
                $foundByFullChassis,
            ),
        ];
    }
}
