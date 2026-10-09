<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LookupWorkshopVehicleRequest;
use App\Http\Requests\Api\V1\StoreWorkshopVehicleRequest;
use App\Models\Vehicle;
use App\Services\Vehicle\WorkshopVehicleRegistrar;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

/**
 * Veículo pelo chassi, para a oficina registrar OS num carro que ainda não tem dono no RevisaLog.
 * Com proprietário nada do carro sai (sem marca, modelo nem ano): a consulta por chassi não pode
 * virar fonte de dados de carro alheio.
 */
#[Group('Workshop vehicles', weight: 14)]
class WorkshopVehicleController extends Controller
{
    public function __construct(private readonly WorkshopVehicleRegistrar $registrar) {}

    public function lookup(LookupWorkshopVehicleRequest $request): JsonResponse
    {
        return ApiResponse::success($this->registrar->lookup((string) $request->validated('chassis')));
    }

    public function store(StoreWorkshopVehicleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $existing = Vehicle::findByChassis($data['chassis']);

        if ($existing !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Este chassi já está no RevisaLog.',
                'data' => ['found' => true, 'vehicle' => $this->registrar->summary($existing)],
            ], 409);
        }

        $vehicle = $this->registrar->register(
            $request->user(),
            $data['chassis'],
            $data['brand'],
            $data['model'],
            (int) $data['year'],
        );

        return ApiResponse::created($this->registrar->summary($vehicle));
    }
}
