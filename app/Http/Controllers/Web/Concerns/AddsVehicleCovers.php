<?php

namespace App\Http\Controllers\Web\Concerns;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleCoverService;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

/**
 * Passo 3 (opcional) do assistente de entrada de veículo: as duas capas, paisagem 16:9 e retrato
 * 9:16, enquadradas no navegador (<x-ui.image-cropper>) antes do envio (.ai/rules/user-vehicles.md).
 * Só quem pode editar o veículo (o dono atual) abre esta tela.
 */
trait AddsVehicleCovers
{
    abstract protected function vehicleEntryFlow(): VehicleEntryFlow;

    public function editCovers(Vehicle $vehicle): View
    {
        Gate::authorize('update', $vehicle);

        $flow = $this->vehicleEntryFlow();

        return view('vehicles.entry.covers', [
            'flow' => $flow,
            'portal' => $flow->portal,
            'vehicle' => $vehicle,
        ]);
    }

    public function updateCovers(Request $request, Vehicle $vehicle, VehicleCoverService $covers): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $flow = $this->vehicleEntryFlow();
        $coverRules = File::image(allowSvg: false)
            ->types(['jpg', 'jpeg', 'png', 'webp'])
            ->max(VehicleEntryFlow::COVER_MAX_KILOBYTES);
        $missingMessage = 'Escolha ao menos uma capa, ou use "Pular por agora".';

        $request->validate([
            'cover' => ['nullable', 'required_without:cover_portrait', $coverRules],
            'cover_portrait' => ['nullable', $coverRules],
        ], [
            'cover.required_without' => $missingMessage,
        ]);

        if ($request->file('cover') !== null) {
            $covers->storeLandscape($vehicle, $request->file('cover'));
        }

        if ($request->file('cover_portrait') !== null) {
            $covers->storePortrait($vehicle, $request->file('cover_portrait'));
        }

        return redirect()->to($flow->vehicleUrl($vehicle))
            ->with('success', 'Capas salvas.');
    }
}
