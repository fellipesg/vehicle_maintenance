<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Concerns\SyncsMaintenanceItems;
use App\Http\Controllers\Concerns\SyncsMaintenanceWarranties;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMaintenanceRequest;
use App\Http\Requests\Api\V1\UpdateMaintenanceRequest;
use App\Http\Resources\Api\V1\MaintenanceResource;
use App\Models\Checklist;
use App\Models\Maintenance;
use App\Models\MaintenanceItem;
use App\Models\Workshop;
use App\Services\Invoice\InvoiceUploadProcessor;
use App\Services\Maintenance\MaintenanceWarrantyApplicator;
use App\Services\Maintenance\OwnerlessMaintenanceService;
use App\Services\Maintenance\WorkshopReviewService;
use App\Services\Vehicle\VehicleMileageService;
use App\Support\ApiResponse;
use App\Support\AppStorage;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

#[Group('Maintenances', weight: 12)]
class MaintenanceController extends Controller
{
    use ResolvesPagination;
    use SyncsMaintenanceItems;
    use SyncsMaintenanceWarranties;

    #[QueryParameter('vehicle_id', 'Filter by vehicle ID.', type: 'integer')]
    #[QueryParameter('service_category', 'Filter by category: mechanical, electrical, suspension, painting, finishing, interior, other.')]
    #[QueryParameter('verified', 'Filter by workshop seal: 1 = verified only, 0 = declared only.', type: 'integer')]
    #[QueryParameter('page', 'Page number (default 1).', type: 'integer')]
    #[QueryParameter('per_page', 'Results per page (default 15, max 100).', type: 'integer')]
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Maintenance::class);

        $user = $request->user();
        $query = Maintenance::with(['vehicle', 'user', 'items.warranty', 'generalWarranty', 'invoices', 'checklists', 'workshop', 'photos', 'invite']);

        if ($user->isWorkshop() && $user->workshop) {
            $query->where('workshop_id', $user->workshop->id);
        } else {
            $query->where('tenant_id', $user->tenant_id);
        }

        if ($request->vehicle_id) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        if ($request->service_category) {
            $query->where('service_category', $request->service_category);
        }

        if ($request->has('verified')) {
            $verified = filter_var($request->query('verified'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($verified === true) {
                $query->verified();
            } elseif ($verified === false) {
                $query->unverified();
            }
        }

        $maintenances = $query->orderBy('maintenance_date', 'desc')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($maintenances, MaintenanceResource::class);
    }

    /**
     * A workshop registers the order it performed on any vehicle. Every other account registers
     * only on a vehicle it currently owns (VehiclePolicy::addMaintenance, as in the web portals):
     * the record moves the odometer, so a dealer holding the car on consignment or an admin gets 403.
     */
    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $vehicle = \App\Models\Vehicle::findOrFail($request->vehicle_id);

        if ($user->isWorkshop() && $user->workshop) {
            if (! $user->workshop) {
                return ApiResponse::error('Workshop profile is required.', 403);
            }
        } else {
            Gate::authorize('addMaintenance', $vehicle);
        }

        try {
            app(VehicleMileageService::class)->assertMaintenanceKilometers(
                $vehicle,
                (int) $request->integer('kilometers'),
                $request->input('maintenance_date'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::validation($e->errors());
        }

        $processor = app(InvoiceUploadProcessor::class);
        $storedInvoices = $processor->storeUploads($request->file('invoices'));

        try {
            DB::beginTransaction();

            $isManufacturerRequired = false;
            if ($request->has('is_manufacturer_required')) {
                $value = $request->is_manufacturer_required;
                if (is_string($value)) {
                    $isManufacturerRequired = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
                } elseif (is_int($value)) {
                    $isManufacturerRequired = $value === 1;
                } else {
                    $isManufacturerRequired = (bool) $value;
                }
            }

            $workshopName = $request->workshop_name;
            $workshopId = $request->workshop_id;
            $tenantId = $user->tenant_id;

            if ($user->isWorkshop() && $user->workshop) {
                $workshopId = $user->workshop->id;
                $workshopName = $user->workshop->name;
                // Sem proprietário atual a OS nasce sem tenant e à espera da decisão dele.
                $tenantId = app(OwnerlessMaintenanceService::class)->tenantIdFor($vehicle);
            } elseif ($request->workshop_id) {
                $workshop = Workshop::find($request->workshop_id);
                if ($workshop) {
                    $workshopName = $workshop->name;
                }
            }

            $maintenance = Maintenance::create([
                'vehicle_id' => $request->vehicle_id,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'workshop_id' => $workshopId,
                'maintenance_type' => $request->maintenance_type,
                'description' => $request->description,
                'workshop_name' => $workshopName,
                'maintenance_date' => $request->maintenance_date,
                'kilometers' => (int) $request->integer('kilometers'),
                'service_category' => $request->service_category,
                'is_manufacturer_required' => $isManufacturerRequired,
            ]);

            if ($user->isWorkshop() && app(OwnerlessMaintenanceService::class)->isOwnerless($vehicle)) {
                app(OwnerlessMaintenanceService::class)->markPending($maintenance);
            }

            app(\App\Services\Maintenance\MaintenanceVerificationStamper::class)->stamp($maintenance, $user);

            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $itemData) {
                    MaintenanceItem::create([
                        'maintenance_id' => $maintenance->id,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'] ?? null,
                        'total_price' => $itemData['total_price'] ?? null,
                        'part_number' => $itemData['part_number'] ?? null,
                    ]);
                }
            }

            $workshop = null;
            if ($user->isWorkshop() && $user->workshop) {
                $workshop = $user->workshop;
            } elseif ($workshopId !== null) {
                $workshop = Workshop::find($workshopId);
            }

            if ($workshop !== null) {
                app(MaintenanceWarrantyApplicator::class)->sync($maintenance, $request, $workshop);
            }

            if ($request->has('checklists') && is_array($request->checklists)) {
                foreach ($request->checklists as $checklistData) {
                    Checklist::create([
                        'maintenance_id' => $maintenance->id,
                        'checklist_type' => $checklistData['checklist_type'],
                        'items' => $checklistData['items'],
                        'notes' => $checklistData['notes'] ?? null,
                    ]);
                }
            }

            $processor->createInvoiceRecords($maintenance, $storedInvoices);

            app(VehicleMileageService::class)->applyMaintenanceKilometers(
                $vehicle->fresh(),
                (int) $maintenance->kilometers,
            );

            DB::commit();

            $uploadResult = $processor->parseStoredUploads($maintenance, $storedInvoices);

            $maintenance->load([
                'items.warranty',
                'generalWarranty',
                'invoices',
                'checklists',
                'vehicle',
                'user',
                'workshop',
                'photos',
            ]);

            $extra = [];

            if ($uploadResult['items_created'] > 0) {
                $extra['parsed_items_count'] = $uploadResult['items_created'];
            }

            if ($uploadResult['warnings'] !== []) {
                $extra['parse_warnings'] = $uploadResult['warnings'];
            }

            $payload = array_merge(
                ['success' => true, 'data' => new MaintenanceResource($maintenance), 'message' => 'Maintenance created successfully'],
                $extra,
            );

            return response()->json($payload, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $processor->discardUploads($storedInvoices);
            Log::error('Error creating maintenance', ['exception' => $e->getMessage()]);

            return ApiResponse::error(
                app()->hasDebugModeEnabled()
                    ? 'Error creating maintenance: '.$e->getMessage()
                    : 'Unable to create maintenance.',
                500,
            );
        }
    }

    public function show(string $id): JsonResponse
    {
        $maintenance = Maintenance::with([
            'vehicle',
            'user',
            'items.warranty',
            'generalWarranty',
            'warranties',
            'invoices',
            'checklists',
            'workshop',
            'photos',
            'invite',
        ])->findOrFail($id);

        Gate::authorize('view', $maintenance);

        return ApiResponse::success(new MaintenanceResource($maintenance));
    }

    public function update(UpdateMaintenanceRequest $request, string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);

        $data = $request->validated();

        if ($request->user()->isWorkshop() && $request->user()->workshop) {
            $data['workshop_id'] = $request->user()->workshop->id;
            $data['workshop_name'] = $request->user()->workshop->name;
        } elseif (isset($data['workshop_id'])) {
            $workshop = Workshop::find($data['workshop_id']);
            if ($workshop) {
                $data['workshop_name'] = $workshop->name;
            }
        }

        if (isset($data['kilometers'])) {
            try {
                app(VehicleMileageService::class)->assertMaintenanceKilometers(
                    $maintenance->vehicle,
                    (int) $data['kilometers'],
                    $data['maintenance_date'] ?? $maintenance->maintenance_date,
                    $maintenance,
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                return ApiResponse::validation($e->errors());
            }
        }

        $previousWorkshopId = $maintenance->workshop_id;
        $previousDate = $maintenance->maintenance_date?->toDateString();

        // Relações, não colunas: sincronizadas abaixo.
        unset($data['items'], $data['general_warranty_template_id'], $data['invoices']);

        $maintenance->update($data);

        if (! $request->user()->isWorkshop()) {
            app(WorkshopReviewService::class)->syncAfterDeclaration($maintenance->fresh(), $previousWorkshopId);
        }

        if (isset($data['kilometers'])) {
            app(VehicleMileageService::class)->refreshCurrentKilometers($maintenance->vehicle->fresh());
        }

        // Mesma ordem do portal web: itens primeiro, para que as garantias
        // casem com os itens que ficaram. Sem a chave `items` a lista fica como
        // está — um PUT parcial não deve zerar os itens da OS.
        if ($request->has('items')) {
            $this->syncMaintenanceItems($maintenance, $request);

            // Garantia é promessa da oficina, então só o portal dela aplica
            // template. Item removido já leva a garantia junto: a FK
            // maintenance_warranties.maintenance_item_id é cascadeOnDelete.
            $workshop = $request->user()->isWorkshop() ? $request->user()->workshop : null;

            if ($workshop !== null) {
                $this->syncMaintenanceWarranties($maintenance, $request, $workshop);
            }
        }

        if ($previousDate !== $maintenance->maintenance_date?->toDateString()) {
            $this->recomputeMaintenanceWarrantyDates($maintenance->fresh(['warranties']));
        }

        return ApiResponse::success(
            new MaintenanceResource($maintenance->fresh([
                'vehicle', 'user', 'items.warranty', 'generalWarranty', 'invoices', 'checklists', 'workshop',
            ])),
            'Maintenance updated successfully',
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $maintenance = Maintenance::findOrFail($id);
        Gate::authorize('delete', $maintenance);

        foreach ($maintenance->photos as $photo) {
            if (AppStorage::disk()->exists($photo->path)) {
                AppStorage::disk()->delete($photo->path);
            }
        }

        foreach ($maintenance->invoices as $invoice) {
            if (AppStorage::disk()->exists($invoice->file_path)) {
                AppStorage::disk()->delete($invoice->file_path);
            }
        }

        $vehicle = $maintenance->vehicle;
        $maintenance->delete();

        if ($vehicle !== null) {
            app(VehicleMileageService::class)->refreshCurrentKilometers($vehicle->fresh());
        }

        return ApiResponse::success(message: 'Maintenance deleted successfully');
    }
}
