<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Enums\WarrantyScope;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\StoresMaintenanceInvoices;
use App\Http\Controllers\Web\Concerns\SyncsMaintenanceItems;
use App\Http\Controllers\Web\Concerns\SyncsMaintenanceWarranties;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Rules\InvoiceFile;
use App\Services\Maintenance\MaintenancePhotoService;
use App\Services\Vehicle\VehicleMileageService;
use App\Support\VehiclePlateSearch;
use App\Support\VehicleTenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    use StoresMaintenanceInvoices;
    use SyncsMaintenanceItems;
    use SyncsMaintenanceWarranties;

    public function __construct(
        private MaintenancePhotoService $photos,
    ) {}

    public function index(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $maintenances = collect();

        if ($workshop) {
            $maintenances = Maintenance::where('workshop_id', $workshop->id)
                ->with(['vehicle', 'user', 'verifiedWorkshop', 'workshop'])
                ->orderByDesc('maintenance_date')
                ->paginate(15);
        }

        return view('workshop.maintenances.index', compact('workshop', 'maintenances'));
    }

    public function create(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $vehicle = null;
        $licensePlate = VehiclePlateSearch::normalize((string) $request->query('license_plate', ''));

        if ($licensePlate !== '') {
            $vehicle = VehiclePlateSearch::findByPlate($licensePlate)?->vehicle;
        }

        return view('workshop.maintenances.create', array_merge(
            compact('workshop', 'vehicle', 'licensePlate'),
            $this->warrantyTemplateOptions($workshop),
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create')
                ->with('error', 'Cadastre sua oficina antes de registrar serviços.');
        }

        if ($redirect = $this->prepareInvoiceUploads($request)) {
            return $redirect;
        }

        $data = $request->validate(array_merge([
            'license_plate' => ['required', 'string', 'max:10'],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'maintenance_date' => ['required', 'date'],
            'kilometers' => ['required', 'integer', 'min:0', 'max:9999999'],
            'service_category' => ['required', 'in:mechanical,electrical,suspension,painting,finishing,interior,other'],
            'is_manufacturer_required' => ['nullable', 'boolean'],
            'invoices' => ['nullable', 'array'],
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['nullable', 'array', 'max:'.MaintenancePhoto::MAX_PER_GROUP],
            'photos.*.*' => [
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
        ], $this->maintenanceItemValidationRules(), $this->maintenanceWarrantyValidationRules()), [
            'license_plate.required' => 'Informe a placa do veículo.',
        ]);

        $vehicle = VehiclePlateSearch::findByPlate($data['license_plate'])?->vehicle;

        if ($vehicle === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['license_plate' => 'Veículo não encontrado para a placa informada. Verifique e tente novamente.']);
        }

        $tenantId = VehicleTenantResolver::resolveTenantId($vehicle);

        if ($tenantId === null) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['license_plate' => 'Não foi possível identificar o proprietário deste veículo.']);
        }

        app(VehicleMileageService::class)->assertMaintenanceKilometers(
            $vehicle,
            (int) $data['kilometers'],
            $data['maintenance_date'],
        );

        $maintenanceData = [
            'vehicle_id' => $vehicle->id,
            'user_id' => $request->user()->id,
            'tenant_id' => $tenantId,
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
            'maintenance_type' => $data['maintenance_type'],
            'description' => $data['description'] ?? null,
            'maintenance_date' => $data['maintenance_date'],
            'kilometers' => (int) $data['kilometers'],
            'service_category' => $data['service_category'],
            'is_manufacturer_required' => $request->boolean('is_manufacturer_required'),
        ];

        $result = $this->storeMaintenanceWithInvoices(
            $request,
            function () use ($maintenanceData, $vehicle, $data, $request, $workshop) {
                $maintenance = Maintenance::create($maintenanceData);
                app(\App\Services\Maintenance\MaintenanceVerificationStamper::class)->stamp($maintenance, $request->user());
                app(VehicleMileageService::class)->applyMaintenanceKilometers(
                    $vehicle,
                    (int) $data['kilometers'],
                );
                $this->syncMaintenanceItems($maintenance, $request);
                $this->syncMaintenanceWarranties($maintenance, $request, $workshop);

                return $maintenance;
            },
        );

        $this->storePhotosFromRequest($request, $result['maintenance'], $request->user());

        return $this->redirectWithInvoiceFeedback(
            redirect()->route('workshop.maintenances.show', $result['maintenance']),
            $result['items_created'],
            $result['warnings'],
        );
    }

    public function show(Request $request, Maintenance $maintenance): View
    {
        Gate::authorize('view', $maintenance);

        $maintenance->load(['vehicle', 'items.warranty', 'generalWarranty', 'invoices', 'checklists', 'photos', 'workshop', 'verifiedWorkshop', 'user']);

        return view('workshop.maintenances.show', compact('maintenance'));
    }

    public function edit(Request $request, Maintenance $maintenance): View
    {
        Gate::authorize('update', $maintenance);

        $maintenance->load(['vehicle', 'items', 'photos', 'generalWarranty', 'warranties']);

        $workshop = $request->user()->workshop;

        return view('workshop.maintenances.edit', array_merge(
            compact('maintenance', 'workshop'),
            $this->warrantyTemplateOptions($workshop),
        ));
    }

    public function update(Request $request, Maintenance $maintenance): RedirectResponse
    {
        Gate::authorize('update', $maintenance);

        if ($redirect = $this->prepareInvoiceUploads($request)) {
            return $redirect;
        }

        $workshop = $request->user()->workshop;

        $data = $request->validate(array_merge([
            'maintenance_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'maintenance_date' => ['required', 'date'],
            'kilometers' => ['required', 'integer', 'min:0', 'max:9999999'],
            'service_category' => ['required', 'in:mechanical,electrical,suspension,painting,finishing,interior,other'],
            'is_manufacturer_required' => ['nullable', 'boolean'],
            'invoices' => ['nullable', 'array'],
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['nullable', 'array', 'max:'.MaintenancePhoto::MAX_PER_GROUP],
            'photos.*.*' => [
                File::image(allowSvg: false)
                    ->types(['jpg', 'jpeg', 'png', 'webp'])
                    ->max(5 * 1024),
            ],
            'delete_photos' => ['nullable', 'array'],
            'delete_photos.*' => ['integer', 'exists:maintenance_photos,id'],
        ], $this->maintenanceItemValidationRules(), $this->maintenanceWarrantyValidationRules()));

        app(VehicleMileageService::class)->assertMaintenanceKilometers(
            $maintenance->vehicle,
            (int) $data['kilometers'],
            $data['maintenance_date'],
            $maintenance,
        );

        $previousDate = $maintenance->maintenance_date?->toDateString();

        $maintenance->update([
            'maintenance_type' => $data['maintenance_type'],
            'description' => $data['description'] ?? null,
            'maintenance_date' => $data['maintenance_date'],
            'kilometers' => (int) $data['kilometers'],
            'service_category' => $data['service_category'],
            'is_manufacturer_required' => $request->boolean('is_manufacturer_required'),
            'workshop_id' => $workshop->id,
            'workshop_name' => $workshop->name,
        ]);

        app(VehicleMileageService::class)->refreshCurrentKilometers($maintenance->vehicle->fresh());

        $warnings = $this->storeMaintenanceInvoices($request, $maintenance);

        $this->syncMaintenanceItems($maintenance, $request);
        $this->syncMaintenanceWarranties($maintenance, $request, $workshop);

        if ($previousDate !== $maintenance->maintenance_date?->toDateString()) {
            $this->recomputeMaintenanceWarrantyDates($maintenance->fresh(['warranties']));
        }

        foreach ($request->input('delete_photos', []) as $photoId) {
            $photo = $maintenance->photos()->whereKey($photoId)->first();
            if ($photo !== null) {
                Gate::authorize('delete', $photo);
                $this->photos->delete($photo);
            }
        }

        $this->storePhotosFromRequest($request, $maintenance, $request->user());

        return $this->redirectWithInvoiceFeedback(
            redirect()->route('workshop.maintenances.show', $maintenance),
            0,
            $warnings,
        )->with('success', 'Serviço atualizado com sucesso!');
    }

    public function destroy(Request $request, Maintenance $maintenance): RedirectResponse
    {
        Gate::authorize('delete', $maintenance);

        foreach ($maintenance->photos as $photo) {
            $this->photos->delete($photo);
        }

        foreach ($maintenance->invoices as $invoice) {
            if (\App\Support\AppStorage::disk()->exists($invoice->file_path)) {
                \App\Support\AppStorage::disk()->delete($invoice->file_path);
            }
        }

        $vehicle = $maintenance->vehicle;
        $maintenance->delete();

        if ($vehicle !== null) {
            app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());
        }

        return redirect()->route('workshop.maintenances.index')
            ->with('success', 'Serviço removido com sucesso!');
    }

    /**
     * @return array{orderTemplates: \Illuminate\Support\Collection, itemTemplates: \Illuminate\Support\Collection}
     */
    private function warrantyTemplateOptions(?\App\Models\Workshop $workshop): array
    {
        if ($workshop === null) {
            return [
                'orderTemplates' => collect(),
                'itemTemplates' => collect(),
            ];
        }

        $templates = $workshop->warrantyTemplates()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return [
            'orderTemplates' => $templates->where('scope', WarrantyScope::Order)->values(),
            'itemTemplates' => $templates->where('scope', WarrantyScope::Item)->values(),
        ];
    }

    private function storePhotosFromRequest(Request $request, Maintenance $maintenance, \App\Models\User $user): void
    {
        $groups = [
            'vehicle_before' => [MaintenancePhoto::SUBJECT_VEHICLE, MaintenancePhoto::STAGE_BEFORE],
            'vehicle_after' => [MaintenancePhoto::SUBJECT_VEHICLE, MaintenancePhoto::STAGE_AFTER],
            'part_before' => [MaintenancePhoto::SUBJECT_PART, MaintenancePhoto::STAGE_BEFORE],
            'part_after' => [MaintenancePhoto::SUBJECT_PART, MaintenancePhoto::STAGE_AFTER],
        ];

        foreach ($groups as $field => [$subject, $stage]) {
            $files = $request->file("photos.{$field}", []);
            if (! is_array($files)) {
                continue;
            }

            foreach ($files as $file) {
                if ($file === null) {
                    continue;
                }

                $this->photos->store($maintenance, $file, $subject, $stage, $user);
            }
        }
    }
}
