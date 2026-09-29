<?php

namespace App\Http\Controllers\Web;

use App\Enums\Portal;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Vehicle\VehicleLookupResult;
use App\Support\VehiclePlateSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Busca de veículo por placa (atual ou antiga), chassi ou RENAVAM, para qualquer conta logada.
 * O resultado é a ficha <x-vehicle.detail> em modo resultado (h2). Quem não é dono do veículo vê
 * chassi e RENAVAM parciais, sem CRV nem motor e sem links para telas de outro portal: a busca
 * aberta a qualquer conta grátis não pode virar fonte de dados para clonagem de documento.
 */
class PublicVehicleController extends Controller
{
    public function search(Request $request): View
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $identifierInput = $request->query('identifier');
        $identifier = is_string($identifierInput) ? trim($identifierInput) : '';
        $lookup = $identifier !== '' ? VehiclePlateSearch::findByIdentifier($identifier) : null;
        $vehicle = $lookup?->vehicle;
        $viewerPortal = Portal::current($viewer, $request);
        $viewerOwnsVehicle = $vehicle !== null && $viewer->can('update', $vehicle);

        $vehicle?->load(['plates' => fn ($query) => $query->orderByDesc('started_at')->orderByDesc('id')]);

        return view('public.vehicle-search', [
            'identifier' => $identifier,
            'vehicle' => $vehicle,
            'matchedBy' => $lookup?->matchedBy,
            'previousPlateEndedAt' => $lookup?->previousPlateEndedAt,
            'viewerPortal' => $viewerPortal,
            'viewerOwnsVehicle' => $viewerOwnsVehicle,
            'detailPortal' => $viewerOwnsVehicle ? $viewerPortal : null,
            'vehiclePortalUrl' => $vehicle !== null && $viewerOwnsVehicle ? $this->vehiclePortalUrl($vehicle, $viewerPortal) : null,
            'adminVehicleUrl' => $vehicle !== null && $viewer->isAdmin() && Route::has('admin.vehicles.show') ? route('admin.vehicles.show', $vehicle) : null,
            'matchLabel' => $this->matchLabel($lookup?->matchedBy),
            'addVehicleAction' => $viewerPortal !== null && in_array($viewerPortal, [Portal::Owner, Portal::Dealer], true) ? $viewerPortal->primaryAction() : null,
        ]);
    }

    /**
     * Ficha do veículo no portal de quem é dono (Meus veículos ou Estoque).
     */
    private function vehiclePortalUrl(Vehicle $vehicle, ?Portal $portal): ?string
    {
        $routeName = $portal?->vehicleRoute();

        return $routeName !== null && Route::has($routeName) ? route($routeName, $vehicle) : null;
    }

    /**
     * Por qual identificador a busca achou o veículo, para quem digitou saber o que casou.
     */
    private function matchLabel(?string $matchedBy): ?string
    {
        return match ($matchedBy) {
            VehicleLookupResult::MATCH_CHASSIS => 'pelo chassi',
            VehicleLookupResult::MATCH_RENAVAM => 'pelo RENAVAM',
            VehicleLookupResult::MATCH_CURRENT_PLATE => 'pela placa atual',
            VehicleLookupResult::MATCH_PREVIOUS_PLATE => 'por uma placa anterior',
            default => null,
        };
    }
}
