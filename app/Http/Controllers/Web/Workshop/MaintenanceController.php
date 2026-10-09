<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Enums\ServiceCategory;
use App\Enums\WarrantyScope;
use App\Http\Controllers\Concerns\SyncsMaintenanceItems;
use App\Http\Controllers\Concerns\SyncsMaintenanceWarranties;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\StoresMaintenanceInvoices;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Rules\Chassis;
use App\Rules\InvoiceFile;
use App\Services\Maintenance\MaintenanceInviteService;
use App\Services\Maintenance\MaintenancePhotoService;
use App\Services\Maintenance\MaintenanceVerificationStamper;
use App\Services\Maintenance\OwnerlessMaintenanceService;
use App\Services\Vehicle\VehicleMileageService;
use App\Services\Vehicle\WorkshopVehicleRegistrar;
use App\Support\AppStorage;
use App\Support\Maintenance\WorkshopMaintenanceFilters;
use App\Support\VehiclePlateSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    use StoresMaintenanceInvoices;
    use SyncsMaintenanceItems;
    use SyncsMaintenanceWarranties;

    /**
     * Grupos de fotos da OS: campo do formulário => [assunto, etapa].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const PHOTO_GROUPS = [
        'vehicle_before' => [MaintenancePhoto::SUBJECT_VEHICLE, MaintenancePhoto::STAGE_BEFORE],
        'vehicle_after' => [MaintenancePhoto::SUBJECT_VEHICLE, MaintenancePhoto::STAGE_AFTER],
        'part_before' => [MaintenancePhoto::SUBJECT_PART, MaintenancePhoto::STAGE_BEFORE],
        'part_after' => [MaintenancePhoto::SUBJECT_PART, MaintenancePhoto::STAGE_AFTER],
    ];

    public function __construct(
        private MaintenancePhotoService $photos,
    ) {}

    public function index(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $filters = WorkshopMaintenanceFilters::fromRequest($request);
        $maintenances = null;
        $counts = ['' => 0, '1' => 0, '0' => 0];

        if ($workshop) {
            $maintenances = $filters->apply($workshop->maintenances())
                ->with(['vehicle', 'user', 'verifiedWorkshop', 'workshop'])
                ->withCount(['invoices', 'photos', 'items'])
                ->withSum('items as items_total', 'total_price')
                ->paginate(15)
                ->withQueryString();

            $totals = $filters->applyWithoutProvenance($workshop->maintenances())
                ->toBase()
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when verified_at is not null then 1 else 0 end) as sealed')
                ->first();

            $counts = [
                '' => (int) ($totals->total ?? 0),
                '1' => (int) ($totals->sealed ?? 0),
                '0' => (int) ($totals->total ?? 0) - (int) ($totals->sealed ?? 0),
            ];
        }

        return view('workshop.maintenances.index', compact('workshop', 'maintenances', 'filters', 'counts'));
    }

    /**
     * Nova OS em duas etapas na mesma página: a placa (GET ?license_plate=) ou o chassi
     * (GET ?chassis=) confirma o veículo e só então o resto do formulário aparece. Pelo chassi o
     * veículo que ainda não existe é criado ali mesmo (marca, modelo e ano). Depois de um erro de
     * validação, o dado enviado (old input) vale quando a URL não traz nada.
     */
    public function create(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $chassisInput = $request->query('chassis');
        $chassisInput = is_scalar($chassisInput) && (string) $chassisInput !== '' ? (string) $chassisInput : (string) $request->old('chassis', '');
        $chassis = substr(Vehicle::normalizeChassis($chassisInput), 0, 17);

        $plateInput = $request->query('license_plate');
        $plateInput = is_scalar($plateInput) && (string) $plateInput !== '' ? (string) $plateInput : (string) $request->old('license_plate', '');
        $licensePlate = $chassis === '' ? substr(VehiclePlateSearch::normalize($plateInput), 0, 10) : '';

        $lookup = $licensePlate !== '' ? VehiclePlateSearch::findByPlate($licensePlate) : null;
        $vehicle = $lookup?->vehicle;
        $chassisOwned = false;
        $creatingVehicle = false;
        $chassisInvalid = false;

        if ($chassis !== '') {
            if (strlen($chassis) !== 17) {
                $chassisInvalid = true;
            } else {
                $vehicle = Vehicle::findByChassis($chassis);

                if ($vehicle !== null && $vehicle->hasCurrentOwner()) {
                    $chassisOwned = true;
                    $vehicle = null;
                } else {
                    $creatingVehicle = $vehicle === null;
                }
            }
        }

        return view('workshop.maintenances.create', array_merge(
            [
                'workshop' => $workshop,
                'vehicle' => $vehicle,
                'licensePlate' => $licensePlate,
                'chassis' => $chassis,
                'chassisOwned' => $chassisOwned,
                'chassisInvalid' => $chassisInvalid,
                'creatingVehicle' => $creatingVehicle,
                'lookup' => $lookup,
                'vehicleHasOwner' => $vehicle !== null && $vehicle->hasCurrentOwner(),
            ],
            $this->warrantyTemplateOptions($workshop),
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create')
                ->with('error', 'Cadastre sua oficina antes de registrar OS.');
        }

        if ($redirect = $this->prepareInvoiceUploads($request)) {
            return $redirect;
        }

        $data = $request->validate(array_merge([
            'license_plate' => ['required_without:chassis', 'nullable', 'string', 'max:10'],
            'chassis' => ['required_without:license_plate', 'nullable', 'string', 'max:30'],
            'new_vehicle' => ['nullable', 'array'],
            'new_vehicle.brand' => ['nullable', 'string', 'max:100'],
            'new_vehicle.model' => ['nullable', 'string', 'max:100'],
            'new_vehicle.year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'maintenance_date' => ['required', 'date'],
            'kilometers' => ['required', 'integer', 'min:0', 'max:9999999'],
            'service_category' => ['required', Rule::in(ServiceCategory::values())],
            'is_manufacturer_required' => ['nullable', 'boolean'],
            'invoices' => ['nullable', 'array'],
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['nullable', 'array', 'max:'.MaintenancePhoto::MAX_PER_GROUP],
            'photos.*.*' => $this->photoFileRules(),
        ], $this->maintenanceItemValidationRules(), $this->maintenanceWarrantyValidationRules()), [
            'license_plate.required_without' => 'Informe a placa ou o chassi do veículo.',
            'chassis.required_without' => 'Informe a placa ou o chassi do veículo.',
            'photos.*.max' => 'Cada grupo aceita até '.MaintenancePhoto::MAX_PER_GROUP.' fotos.',
        ]);

        $newVehicle = null;
        $chassis = Vehicle::normalizeChassis((string) ($data['chassis'] ?? ''));

        if ($chassis !== '') {
            $vehicle = Vehicle::findByChassis($chassis);

            if ($vehicle !== null && $vehicle->hasCurrentOwner()) {
                return redirect()->back()->withInput()
                    ->withErrors(['chassis' => self::chassisOwnedMessage()]);
            }

            if ($vehicle === null) {
                $newVehicle = $request->validate([
                    'chassis' => ['required', 'string', new Chassis(isset($data['new_vehicle']['year']) ? (int) $data['new_vehicle']['year'] : null)],
                    'new_vehicle.brand' => ['required', 'string', 'max:100'],
                    'new_vehicle.model' => ['required', 'string', 'max:100'],
                    'new_vehicle.year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
                ], [
                    'new_vehicle.brand.required' => 'Informe a marca do veículo.',
                    'new_vehicle.model.required' => 'Informe o modelo do veículo.',
                    'new_vehicle.year.required' => 'Informe o ano do veículo.',
                ])['new_vehicle'];
            }
        } else {
            $licensePlate = VehiclePlateSearch::normalize((string) $data['license_plate']);
            $vehicle = VehiclePlateSearch::findByPlate($licensePlate)?->vehicle;

            if ($vehicle === null) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['license_plate' => self::vehicleNotFoundMessage($licensePlate)]);
            }
        }

        if ($vehicle !== null) {
            app(VehicleMileageService::class)->assertMaintenanceKilometers(
                $vehicle,
                (int) $data['kilometers'],
                $data['maintenance_date'],
            );
        }

        $maintenanceData = [
            'user_id' => $request->user()->id,
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
            function () use ($maintenanceData, $vehicle, $newVehicle, $chassis, $data, $request, $workshop) {
                return DB::transaction(function () use ($maintenanceData, $vehicle, $newVehicle, $chassis, $data, $request, $workshop) {
                    $vehicle ??= app(WorkshopVehicleRegistrar::class)->register(
                        $request->user(),
                        $chassis,
                        (string) $newVehicle['brand'],
                        (string) $newVehicle['model'],
                        (int) $newVehicle['year'],
                        (int) $data['kilometers'],
                    );

                    $ownerless = app(OwnerlessMaintenanceService::class);
                    $maintenance = Maintenance::create($maintenanceData + [
                        'vehicle_id' => $vehicle->id,
                        'tenant_id' => $ownerless->tenantIdFor($vehicle),
                    ]);

                    if ($ownerless->isOwnerless($vehicle)) {
                        $ownerless->markPending($maintenance);
                    }

                    app(MaintenanceVerificationStamper::class)->stamp($maintenance, $request->user());
                    app(VehicleMileageService::class)->applyMaintenanceKilometers(
                        $vehicle,
                        (int) $data['kilometers'],
                    );
                    $this->syncMaintenanceItems($maintenance, $request);
                    $this->syncMaintenanceWarranties($maintenance, $request, $workshop);

                    return $maintenance;
                });
            },
        );

        $maintenance = $result['maintenance']->fresh();

        $this->storePhotosFromRequest($request, $maintenance, $request->user());

        return $this->redirectWithInvoiceFeedback(
            redirect()->route('workshop.maintenances.show', $maintenance),
            $result['items_created'],
            $result['warnings'],
            $result['items_skipped'],
            self::createdMessage($maintenance),
        );
    }

    public function show(Request $request, Maintenance $maintenance): View
    {
        Gate::authorize('view', $maintenance);

        $maintenance->load(['vehicle', 'items.warranty', 'generalWarranty', 'invoices', 'checklists', 'photos', 'workshop', 'verifiedWorkshop', 'user']);

        $invites = app(MaintenanceInviteService::class);

        return view('workshop.maintenances.show', [
            'maintenance' => $maintenance,
            'canInvite' => $request->user()->workshop !== null && $invites->canInvite($maintenance, $request->user()->workshop),
            'invite' => $maintenance->owner_status !== null ? \App\Models\MaintenanceInvite::query()->where('maintenance_id', $maintenance->id)->first() : null,
            'canManage' => self::isAuthoredBy($maintenance, $request->user()->workshop),
            'updatedAfterSeal' => self::wasUpdatedAfterSeal($maintenance),
        ]);
    }

    public function edit(Request $request, Maintenance $maintenance): View|RedirectResponse
    {
        Gate::authorize('view', $maintenance);

        if ($redirect = $this->redirectUnlessAuthored($request, $maintenance)) {
            return $redirect;
        }

        Gate::authorize('update', $maintenance);

        $maintenance->load([
            'vehicle',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.warranty',
            'invoices',
            'photos',
            'generalWarranty',
            'warranties',
        ]);

        $workshop = $request->user()->workshop;

        return view('workshop.maintenances.edit', array_merge(
            compact('maintenance', 'workshop'),
            $this->warrantyTemplateOptions($workshop, $maintenance),
        ));
    }

    public function update(Request $request, Maintenance $maintenance): RedirectResponse
    {
        Gate::authorize('view', $maintenance);

        if ($redirect = $this->redirectUnlessAuthored($request, $maintenance)) {
            return $redirect;
        }

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
            'service_category' => ['required', Rule::in(ServiceCategory::values())],
            'is_manufacturer_required' => ['nullable', 'boolean'],
            'invoices' => ['nullable', 'array'],
            'invoices.*' => ['file', new InvoiceFile, 'max:10240'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['nullable', 'array', 'max:'.MaintenancePhoto::MAX_PER_GROUP],
            'photos.*.*' => $this->photoFileRules(),
            'delete_photos' => ['nullable', 'array'],
            'delete_photos.*' => ['integer', 'exists:maintenance_photos,id'],
        ], $this->maintenanceItemValidationRules(), $this->maintenanceWarrantyValidationRules()), [
            'photos.*.max' => 'Cada grupo aceita até '.MaintenancePhoto::MAX_PER_GROUP.' fotos.',
        ]);

        // Antes de gravar qualquer coisa: fotos que ficam + novas não passam do limite do grupo.
        $this->assertPhotoCapacity($request, $maintenance);

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

        $this->syncMaintenanceItems($maintenance, $request);
        $this->syncMaintenanceWarranties($maintenance, $request, $workshop);

        if ($previousDate !== $maintenance->maintenance_date?->toDateString()) {
            $this->recomputeMaintenanceWarrantyDates($maintenance->fresh(['warranties']));
        }

        // Depois dos itens: com a lista vazia, os itens da NF-e preenchem a OS.
        $invoiceResult = $this->processMaintenanceInvoices($request, $maintenance);

        foreach ($this->photoIdsToDelete($request) as $photoId) {
            $photo = $maintenance->photos()->whereKey($photoId)->first();
            if ($photo !== null) {
                Gate::authorize('delete', $photo);
                $this->photos->delete($photo);
            }
        }

        $this->storePhotosFromRequest($request, $maintenance, $request->user());

        return $this->redirectWithInvoiceFeedback(
            redirect()->route('workshop.maintenances.show', $maintenance),
            $invoiceResult['items_created'],
            $invoiceResult['warnings'],
            $invoiceResult['items_skipped'],
            'OS atualizada.',
        );
    }

    public function destroy(Request $request, Maintenance $maintenance): RedirectResponse
    {
        Gate::authorize('view', $maintenance);

        if ($redirect = $this->redirectUnlessAuthored($request, $maintenance)) {
            return $redirect;
        }

        Gate::authorize('delete', $maintenance);

        foreach ($maintenance->photos as $photo) {
            $this->photos->delete($photo);
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

        return redirect()->route('workshop.maintenances.index')
            ->with('success', 'OS excluída.');
    }

    /**
     * A OS é da oficina: ela aplicou o Selo da oficina. Registros que um proprietário ou lojista
     * declarou citando a oficina (workshop_id) aparecem para ela, mas só quem declarou pode
     * alterar ou excluir (a oficina não é autora e o registro continua "Declarada").
     */
    public static function isAuthoredBy(Maintenance $maintenance, ?Workshop $workshop): bool
    {
        return $workshop !== null
            && $maintenance->isVerified()
            && (int) $maintenance->verified_workshop_id === (int) $workshop->id;
    }

    /**
     * A OS mudou depois da emissão do selo (Maintenance::wasUpdatedAfterSeal).
     */
    public static function wasUpdatedAfterSeal(Maintenance $maintenance): bool
    {
        return $maintenance->wasUpdatedAfterSeal();
    }

    public static function vehicleNotFoundMessage(string $licensePlate): string
    {
        return 'Veículo '.$licensePlate.' ainda não está no RevisaLog. Cadastre-o pelo chassi para registrar a OS.';
    }

    public static function chassisOwnedMessage(): string
    {
        return 'Este chassi já tem proprietário no RevisaLog. Registre a OS pela placa do veículo.';
    }

    private static function createdMessage(Maintenance $maintenance): string
    {
        return $maintenance->verification_code
            ? 'OS registrada. Selo da oficina emitido: '.$maintenance->verification_code.'.'
            : 'OS registrada.';
    }

    private function redirectUnlessAuthored(Request $request, Maintenance $maintenance): ?RedirectResponse
    {
        if (self::isAuthoredBy($maintenance, $request->user()->workshop)) {
            return null;
        }

        return redirect()->route('workshop.maintenances.show', $maintenance)
            ->with('error', 'Este registro foi declarado por quem cuida do veículo e não tem o Selo da sua oficina. Só quem declarou pode alterar ou excluir.');
    }

    /**
     * @return array<int, mixed>
     */
    private function photoFileRules(): array
    {
        return [
            File::image(allowSvg: false)
                ->types(['jpg', 'jpeg', 'png', 'webp'])
                ->max(5 * 1024),
        ];
    }

    /**
     * @return list<int>
     */
    private function photoIdsToDelete(Request $request): array
    {
        $ids = $request->input('delete_photos', []);

        return is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
    }

    /**
     * Fotos que ficam (as existentes menos as marcadas para remover) + as novas de cada grupo não
     * podem passar de MaintenancePhoto::MAX_PER_GROUP. Roda antes de qualquer gravação, com o erro
     * na chave photos.{grupo}, mostrada ao lado do campo.
     */
    private function assertPhotoCapacity(Request $request, Maintenance $maintenance): void
    {
        $toDelete = $this->photoIdsToDelete($request);
        $existing = $maintenance->photos()->get(['id', 'subject', 'stage']);
        $messages = [];

        foreach (self::PHOTO_GROUPS as $field => [$subject, $stage]) {
            $kept = $existing
                ->where('subject', $subject)
                ->where('stage', $stage)
                ->reject(fn (MaintenancePhoto $photo): bool => in_array($photo->id, $toDelete, true))
                ->count();
            $files = $request->file("photos.{$field}", []);
            $incoming = is_array($files) ? count(array_filter($files)) : 0;

            if ($incoming > 0 && $kept + $incoming > MaintenancePhoto::MAX_PER_GROUP) {
                $room = max(0, MaintenancePhoto::MAX_PER_GROUP - $kept);
                $messages["photos.{$field}"] = $room === 0
                    ? 'Este grupo já tem '.MaintenancePhoto::MAX_PER_GROUP.' fotos. Remova alguma antes de adicionar outra.'
                    : 'Este grupo aceita até '.MaintenancePhoto::MAX_PER_GROUP.' fotos e já tem '.$kept.'. Envie no máximo '.$room.' ou remova alguma.';
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * Active templates for the form. On edit, the order template behind the
     * issued general warranty stays listed even when deactivated, so saving the
     * OS keeps that warranty. Item rows handle their own issued template.
     *
     * @return array{orderTemplates: Collection, itemTemplates: Collection, hasActiveTemplates: bool}
     */
    private function warrantyTemplateOptions(?Workshop $workshop, ?Maintenance $maintenance = null): array
    {
        if ($workshop === null) {
            return [
                'orderTemplates' => collect(),
                'itemTemplates' => collect(),
                'hasActiveTemplates' => false,
            ];
        }

        $issuedOrderTemplateIds = $maintenance?->warranties
            ->where('scope', WarrantyScope::Order)
            ->pluck('warranty_template_id')
            ->filter()
            ->values()
            ->all() ?? [];

        $templates = $workshop->warrantyTemplates()
            ->where(function ($query) use ($issuedOrderTemplateIds) {
                $query->where('is_active', true)
                    ->orWhereIn('id', $issuedOrderTemplateIds);
            })
            ->orderBy('name')
            ->get();

        return [
            'orderTemplates' => $templates->where('scope', WarrantyScope::Order)->values(),
            'itemTemplates' => $templates->where('scope', WarrantyScope::Item)->where('is_active', true)->values(),
            'hasActiveTemplates' => $templates->where('is_active', true)->isNotEmpty(),
        ];
    }

    private function storePhotosFromRequest(Request $request, Maintenance $maintenance, User $user): void
    {
        foreach (self::PHOTO_GROUPS as $field => [$subject, $stage]) {
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
