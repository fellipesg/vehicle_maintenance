<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicVehicleController extends Controller
{
    public function search(Request $request): View
    {
        $vehicle = null;
        $identifier = $request->input('identifier');

        if ($identifier) {
            $vehicle = Vehicle::where('license_plate', $identifier)
                ->orWhere('renavam', $identifier)
                ->with([
                    'maintenances' => fn ($query) => $query->with([
                        'items',
                        'invoices',
                        'workshop',
                        'photos' => fn ($photos) => $photos
                            ->where('subject', \App\Models\MaintenancePhoto::SUBJECT_VEHICLE)
                            ->where('stage', \App\Models\MaintenancePhoto::STAGE_AFTER)
                            ->orderBy('sort'),
                    ]),
                ])
                ->first();
        }

        return view('public.vehicle-search', compact('vehicle', 'identifier'));
    }
}
