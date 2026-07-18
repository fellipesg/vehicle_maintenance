<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $vehicles = $user->currentVehicles()->withCount('maintenances')->get();
        $recentMaintenances = Maintenance::where('tenant_id', $user->tenant_id)
            ->with('vehicle')
            ->orderByDesc('maintenance_date')
            ->limit(5)
            ->get();

        return view('user.dashboard', compact('vehicles', 'recentMaintenances'));
    }
}
