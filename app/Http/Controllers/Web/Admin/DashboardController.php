<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->withCount([
                'vehicles as vehicles_count' => fn ($query) => $query->where('user_vehicles.is_current_owner', true),
                'maintenances',
            ])
            ->orderBy('name')
            ->get();

        return view('admin.dashboard', [
            'brandCount' => VehicleBrand::count(),
            'modelCount' => VehicleModel::count(),
            'activeBrandCount' => VehicleBrand::where('is_active', true)->count(),
            'userCount' => User::count(),
            'vehicleCount' => Vehicle::count(),
            'maintenanceCount' => Maintenance::count(),
            'usersByType' => User::query()
                ->selectRaw('user_type, count(*) as total')
                ->groupBy('user_type')
                ->pluck('total', 'user_type'),
            'users' => $users,
        ]);
    }
}
