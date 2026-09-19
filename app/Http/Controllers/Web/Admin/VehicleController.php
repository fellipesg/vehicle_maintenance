<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Support\VehiclePlateSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $vehiclesQuery = Vehicle::query()
            ->withCount('maintenances')
            ->with([
                'owners' => fn ($query) => $query->wherePivot('is_current_owner', true),
                'maintenances' => fn ($query) => $query
                    ->orderByDesc('maintenance_date')
                    ->orderByDesc('id')
                    ->limit(1)
                    ->with('workshop'),
            ])
            ->orderByDesc('created_at');

        if ($search !== '') {
            $lookup = VehiclePlateSearch::findByIdentifier($search);
            if ($lookup !== null) {
                $vehiclesQuery->where('vehicles.id', $lookup->vehicle->id);
            } else {
                $vehiclesQuery->where(function ($query) use ($search) {
                    $like = '%'.$search.'%';
                    $query->where('chassis', 'like', $like)
                        ->orWhere('license_plate', 'like', $like)
                        ->orWhere('renavam', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('model', 'like', $like);
                });
            }
        }

        $vehicles = $vehiclesQuery->paginate(20)->withQueryString();

        return view('admin.vehicles.index', [
            'vehicles' => $vehicles,
            'search' => $search,
        ]);
    }

    public function show(Vehicle $vehicle): View
    {
        $vehicle->load([
            'owners' => fn ($query) => $query->wherePivot('is_current_owner', true),
            'maintenances' => fn ($query) => $query->orderByDesc('maintenance_date')->with('workshop'),
        ]);

        return view('admin.vehicles.show', compact('vehicle'));
    }
}
