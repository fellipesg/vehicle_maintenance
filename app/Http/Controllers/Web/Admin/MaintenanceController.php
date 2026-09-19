<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $verified = $this->normalizeVerifiedFilter($request->query('verified'));

        $maintenances = $this->maintenancesQuery($verified)
            ->paginate(25)
            ->withQueryString();

        $viewData = [
            'maintenances' => $maintenances,
            'verified' => $verified,
        ];

        if ($request->ajax()) {
            return view('admin.maintenances._results', $viewData);
        }

        return view('admin.maintenances.index', $viewData);
    }

    private function normalizeVerifiedFilter(mixed $verified): ?string
    {
        if ($verified === '1' || $verified === '0') {
            return $verified;
        }

        return null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Maintenance>
     */
    private function maintenancesQuery(?string $verified)
    {
        return Maintenance::query()
            ->with(['vehicle', 'workshop', 'verifiedWorkshop', 'user'])
            ->when($verified === '1', fn ($query) => $query->whereNotNull('verified_at'))
            ->when($verified === '0', fn ($query) => $query->whereNull('verified_at'))
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id');
    }
}
