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
                ->with(['maintenances.items', 'maintenances.invoices', 'maintenances.workshop'])
                ->first();
        }

        return view('public.vehicle-search', compact('vehicle', 'identifier'));
    }
}
