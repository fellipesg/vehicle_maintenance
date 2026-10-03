<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\VehicleConsignment;
use App\Services\Vehicle\VehicleConsignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page a consigned vehicle's owner reaches from the e-mail we send them.
 *
 * It is reachable without an account on purpose: most consigned cars belong to someone
 * who never signed up, and asking them to register before they can say "that is not my
 * garage" would defeat the point. The token in the URL is the secret.
 */
class ConsignmentOwnerController extends Controller
{
    public function show(string $token): View
    {
        $consignment = $this->resolve($token);

        return view('consignments.owner', [
            'consignment' => $consignment,
            'maintenances' => $consignment->vehicle
                ->maintenances()
                ->where('tenant_id', $consignment->tenant_id)
                ->orderByDesc('maintenance_date')
                ->get(),
        ]);
    }

    public function approveHistory(string $token, VehicleConsignmentService $consignments): RedirectResponse
    {
        $consignment = $this->resolve($token);

        if (! $consignment->isActive()) {
            return back()->withErrors(['consignment' => 'Esta consignação já foi encerrada.']);
        }

        $consignments->approveHistoryAccess($consignment, 'owner');

        return back()->with('success', 'Pronto! A garagem já pode ver o histórico de manutenções do veículo.');
    }

    public function dispute(Request $request, string $token, VehicleConsignmentService $consignments): RedirectResponse
    {
        $consignment = $this->resolve($token);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $consignments->dispute($consignment, $data['note'] ?? null);

        return back()->with(
            'success',
            'Registramos a sua contestação. A garagem não pode mais lançar manutenções neste veículo e nossa equipe vai analisar o caso.',
        );
    }

    private function resolve(string $token): VehicleConsignment
    {
        return VehicleConsignment::with(['vehicle', 'garageUser', 'ownerUser'])
            ->where('owner_action_token', $token)
            ->firstOrFail();
    }
}
