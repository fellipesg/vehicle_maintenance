<?php

namespace App\Http\Controllers\Web\Garage;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\StoresMaintenanceInvoices;
use App\Models\Maintenance;
use App\Models\Workshop;
use App\Rules\InvoiceFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    use StoresMaintenanceInvoices;
    public function index(Request $request): View
    {
        $maintenances = Maintenance::where('tenant_id', $request->user()->tenant_id)
            ->with(['vehicle', 'workshop'])
            ->orderByDesc('maintenance_date')
            ->paginate(15);

        return view('garage.maintenances.index', compact('maintenances'));
    }

    public function create(Request $request): View
    {
        $vehicles = $request->user()->currentVehicles()->get();
        $workshops = Workshop::orderBy('name')->get();

        return view('garage.maintenances.create', compact('vehicles', 'workshops'));
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->prepareInvoiceUploads($request)) {
            return $redirect;
        }

        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'workshop_id' => ['nullable', 'exists:workshops,id'],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'workshop_name' => ['nullable', 'string', 'max:255'],
            'maintenance_date' => ['required', 'date'],
            'kilometers' => ['nullable', 'integer', 'min:0'],
            'service_category' => ['required', 'in:mechanical,electrical,suspension,painting,finishing,interior,other'],
            'is_manufacturer_required' => ['nullable', 'boolean'],
            'invoices' => ['nullable', 'array'],
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
        ]);

        if (! empty($data['workshop_id'])) {
            $workshop = Workshop::find($data['workshop_id']);
            $data['workshop_name'] = $workshop?->name;
        }

        $data['user_id'] = $request->user()->id;
        $data['tenant_id'] = $request->user()->tenant_id;
        $data['is_manufacturer_required'] = $request->boolean('is_manufacturer_required');

        $uploadResult = ['items_created' => 0, 'warnings' => []];

        DB::transaction(function () use ($request, $data, &$uploadResult) {
            $maintenance = Maintenance::create($data);
            $uploadResult = $this->processMaintenanceInvoices($request, $maintenance);
        });

        return $this->redirectWithInvoiceFeedback(
            redirect()->route('garage.maintenances.index'),
            $uploadResult['items_created'],
            $uploadResult['warnings'],
        );
    }
}
