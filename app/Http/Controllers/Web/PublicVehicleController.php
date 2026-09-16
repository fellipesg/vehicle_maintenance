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

                $vehicle->loadCount([
                    'maintenances',
                    'maintenances as verified_maintenances_count' => fn ($query) => $query->whereNotNull('verified_at'),
                ]);
                $vehicle->load([
                    'provenanceStripMaintenances',
                    'plates' => fn ($q) => $q->orderByDesc('started_at')->orderByDesc('created_at'),
                    'maintenances' => fn ($query) => $query
                        ->with([
                            'items',
                            'invoices',
                            'workshop',
                            'verifiedWorkshop',
                            'user',
                            'photos' => fn ($photos) => $photos
                                ->where('subject', \App\Models\MaintenancePhoto::SUBJECT_VEHICLE)
                                ->where('stage', \App\Models\MaintenancePhoto::STAGE_AFTER)
                                ->orderBy('sort'),
                        ])
                        ->orderByDesc('maintenance_date'),
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
