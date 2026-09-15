<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\VehiclePlateSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicVehicleController extends Controller
{
    public function search(Request $request): View
    {
        $vehicle = null;
        $identifier = $request->input('identifier');
        $matchedBy = null;
        $previousPlateEndedAt = null;

        if ($identifier) {
            $lookup = VehiclePlateSearch::findByIdentifier((string) $identifier);

            if ($lookup !== null) {
                $vehicle = $lookup->vehicle;
                $matchedBy = $lookup->matchedBy;
                $previousPlateEndedAt = $lookup->previousPlateEndedAt;

                $vehicle->load([
                    'plates' => fn ($q) => $q->orderByDesc('started_at')->orderByDesc('created_at'),
                    'maintenances' => fn ($query) => $query->with([
                        'items',
                        'invoices',
                        'workshop',
                        'photos' => fn ($photos) => $photos
                            ->where('subject', \App\Models\MaintenancePhoto::SUBJECT_VEHICLE)
                            ->where('stage', \App\Models\MaintenancePhoto::STAGE_AFTER)
                            ->orderBy('sort'),
                    ]),
                ]);
            }
        }

        return view('public.vehicle-search', compact(
            'vehicle',
            'identifier',
            'matchedBy',
            'previousPlateEndedAt',
        ));
    }
}
