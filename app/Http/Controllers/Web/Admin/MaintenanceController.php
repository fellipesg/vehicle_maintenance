<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $verified = $request->query('verified');

        $maintenances = Maintenance::query()
            ->with(['vehicle', 'workshop'])
            ->when($verified === '1', fn ($query) => $query->whereNotNull('verified_at'))
            ->when($verified === '0', fn ($query) => $query->whereNull('verified_at'))
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.maintenances.index', [
            'maintenances' => $maintenances,
            'verified' => $verified,
        ]);
    }
}
