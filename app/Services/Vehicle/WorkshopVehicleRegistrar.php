<?php

namespace App\Services\Vehicle;

use App\Models\User;
use App\Models\Vehicle;

/**
 * Veículo criado pela oficina pelo chassi, quando ele ainda não está no RevisaLog. Guarda só
 * chassi, marca, modelo e ano (LGPD, art. 6º III): sem placa, RENAVAM nem dado do dono. Nasce sem
 * proprietário; quem for o dono o vincula depois (CRLV-e na web, cadastro manual no app) e decide
 * o que entra no histórico dele.
 */
class WorkshopVehicleRegistrar
{
    public function __construct(private readonly VehicleMileageService $mileage) {}

    public function register(User $workshopUser, string $chassis, string $brand, string $model, int $year, int $kilometers = 0): Vehicle
    {
        $vehicle = Vehicle::create([
            'chassis' => Vehicle::normalizeChassis($chassis),
            'brand' => trim($brand),
            'model' => trim($model),
            'year' => $year,
        ]);

        $this->mileage->registerOdometer($vehicle, $kilometers);

        return $vehicle->refresh();
    }

    /**
     * Resposta mínima do que a oficina pode saber de um chassi. Com proprietário não vaza nada além
     * de "existe e tem dono": marca, modelo e ano de carro alheio não saem pela consulta por chassi.
     *
     * @return array{found: bool, vehicle: array<string, mixed>|null}
     */
    public function lookup(string $chassis): array
    {
        $vehicle = Vehicle::findByChassis($chassis);

        if ($vehicle === null) {
            return ['found' => false, 'vehicle' => null];
        }

        return ['found' => true, 'vehicle' => $this->summary($vehicle)];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Vehicle $vehicle): array
    {
        if ($vehicle->hasCurrentOwner()) {
            return ['id' => $vehicle->id, 'has_owner' => true];
        }

        return [
            'id' => $vehicle->id,
            'brand' => $vehicle->brand,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'has_owner' => false,
        ];
    }
}
