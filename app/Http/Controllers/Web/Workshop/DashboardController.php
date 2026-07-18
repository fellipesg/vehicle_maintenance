<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $maintenancesCount = 0;
        $recentMaintenances = collect();

        if ($workshop) {
            $maintenancesCount = $workshop->maintenances()->count();
            $recentMaintenances = $workshop->maintenances()
                ->with('vehicle')
                ->orderByDesc('maintenance_date')
                ->limit(5)
                ->get();
        }

        return view('workshop.dashboard', compact('workshop', 'maintenancesCount', 'recentMaintenances'));
    }
}
