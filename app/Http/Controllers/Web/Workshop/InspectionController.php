<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ResolvesWorkshopVehicle;
use App\Http\Requests\Web\Workshop\StoreVehicleInspectionRequest;
use App\Http\Requests\Web\Workshop\UpdateVehicleInspectionRequest;
use App\Models\VehicleInspection;
use App\Policies\VehicleInspectionPolicy;
use App\Services\Inspection\VehicleInspectionService;
use App\Services\Maintenance\WorkshopRecordsArrivalNotifier;
use App\Services\Vehicle\WorkshopVehicleRegistrar;
use App\Support\Workshop\WorkshopVehicleStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * As telas de vistoria cautelar da oficina. O laudo é irmão da OS, não uma categoria de serviço
 * (.ai/rules/inspections.md), então tem lista, formulário e detalhe próprios.
 *
 * Emitir exige **duas** coisas: ser oficina aprovada e ter a funcionalidade ligada na conta
 * (App\Features\VehicleInspections, desligada por padrão). Quem não tem a segunda vê a explicação
 * em vez de um 403 seco — e, como manda .ai/rules/features.md, a tela também não *oferece* o que a
 * conta não pode fazer: sem a flag não há item de menu (App\Enums\Portal) nem botão.
 *
 * O veredito, os achados e o código público nunca são gravados daqui: quem escreve é o
 * VehicleInspectionService, que é quem recusa um "Aprovado" sobre um eixo reprovado.
 */
class InspectionController extends Controller
{
    use ResolvesWorkshopVehicle;

    public function __construct(private readonly VehicleInspectionService $inspections) {}

    public function index(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $inspections = null;

        if ($workshop !== null) {
            $inspections = $workshop->inspections()
                ->with('vehicle')
                ->orderByDesc('inspected_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();
        }

        return view('workshop.inspections.index', [
            'workshop' => $workshop,
            'inspections' => $inspections,
            'mayIssue' => Gate::allows('create', VehicleInspection::class),
        ]);
    }

    /**
     * Nova vistoria em duas etapas na mesma página, como a Nova OS: a placa (?license_plate=) ou o
     * chassi (?chassis=) confirma o veículo e só então o resto do formulário aparece
     * (App\Support\Workshop\WorkshopVehicleStep, com o mascaramento de quem não tem vínculo com o
     * veículo).
     */
    public function create(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $decision = Gate::inspect('create', VehicleInspection::class);
        $featureOff = ! $decision->allowed() && $decision->code() === VehicleInspectionPolicy::DENIED_FEATURE_OFF;

        // Sem oficina cadastrada a tela mostra o onboarding, como nas OS; com oficina, a única
        // recusa que a tela explica é a da funcionalidade desligada.
        if ($workshop !== null && ! $decision->allowed() && ! $featureOff) {
            abort(403);
        }

        return view('workshop.inspections.create', array_merge(
            [
                'workshop' => $workshop,
                'featureOff' => $featureOff,
            ],
            $decision->allowed()
                ? WorkshopVehicleStep::resolve($request, $workshop)
                : WorkshopVehicleStep::none(),
        ));
    }

    public function store(StoreVehicleInspectionRequest $request): RedirectResponse
    {
        $workshop = $request->user()->workshop;
        abort_if($workshop === null, 403);

        $data = $request->validated();

        [
            'vehicle' => $vehicle,
            'newVehicle' => $newVehicle,
            'chassis' => $chassis,
        ] = $this->resolveWorkshopVehicle(
            $request,
            $data,
            self::chassisOwnedMessage(),
            fn (string $licensePlate): string => self::vehicleNotFoundMessage($licensePlate),
        );

        // Conflito de interesse: a oficina não emite laudo sobre veículo do tenant dela
        // (.ai/rules/inspections.md). A recusa sai aqui, antes da transação, e em `license_plate`
        // porque é o único caminho que chega a um veículo com dono — pelo chassi,
        // ResolvesWorkshopVehicle já recusa carro que tem proprietário.
        if ($vehicle !== null && Gate::inspect('create', [VehicleInspection::class, $vehicle])->code() === VehicleInspectionPolicy::DENIED_OWN_VEHICLE) {
            throw ValidationException::withMessages([
                'license_plate' => self::ownVehicleMessage(),
            ]);
        }

        // O PDF sobe **antes** do laudo existir, e fora da transação (o I/O do storage não segura
        // conexão aberta — .ai/rules/invoices.md). É o que torna a emissão retentável: a recusa do
        // storage volta como erro de validação no formulário, com nenhum laudo emitido. Quando o
        // arquivo era anexado depois do commit, a oficina via "não foi possível guardar o laudo"
        // sobre um laudo que já tinha código e já estava no histórico do chassi, e a retentativa
        // emitia o segundo.
        $reportPath = $request->hasFile('report')
            ? $this->inspections->storeReportFile($request->file('report'))
            : null;

        try {
            $inspection = DB::transaction(function () use ($vehicle, $newVehicle, $chassis, $data, $request, $workshop, $reportPath): VehicleInspection {
                // Veículo criado pelo chassi nasce **sem** hodômetro: a leitura do laudo é do laudo, e
                // alimentar vehicles.current_kilometers com ela é o que .ai/rules/inspections.md proíbe.
                $vehicle ??= app(WorkshopVehicleRegistrar::class)->register(
                    $request->user(),
                    $chassis,
                    (string) $newVehicle['brand'],
                    (string) $newVehicle['model'],
                    (int) $newVehicle['year'],
                );

                return $this->inspections->create(
                    $vehicle,
                    $workshop,
                    $request->user(),
                    $data,
                    $reportPath,
                    $reportPath === null ? null : $request->file('report')->getClientOriginalName(),
                );
            });
        } catch (\Throwable $exception) {
            // Nenhuma linha aponta para o arquivo: sem esta limpeza ele ficaria em `inspections/`
            // para sempre (o varredor PurgePendingMaintenanceAttachments é só de fotos de OS).
            $this->inspections->discardReportFile($reportPath);

            throw $exception;
        }

        // O dono atual precisa **saber** que existe um laudo sobre o carro dele: a contestação só
        // vale com a descoberta do lado dela (.ai/rules/inspections.md). Mesmo notificador do
        // registro de OS, e depois do commit, como no store da oficina.
        app(WorkshopRecordsArrivalNotifier::class)->notifyOwnerOfInspection($inspection);

        return redirect()->route('workshop.inspections.show', $inspection)
            ->with('success', 'Laudo emitido. Código de conferência: '.$inspection->verification_code.'.');
    }

    public function show(Request $request, VehicleInspection $inspection): View
    {
        $inspection->loadMissing('vehicle');

        Gate::authorize('view', $inspection);

        $inspection->loadMissing(['workshop', 'user']);

        return view('workshop.inspections.show', [
            'inspection' => $inspection,
            'canManage' => Gate::allows('update', $inspection),
        ]);
    }

    public function edit(Request $request, VehicleInspection $inspection): View
    {
        $inspection->loadMissing('vehicle');

        Gate::authorize('update', $inspection);

        return view('workshop.inspections.edit', [
            'inspection' => $inspection,
            'vehicle' => $inspection->vehicle,
            'workshop' => $request->user()->workshop,
        ]);
    }

    public function update(UpdateVehicleInspectionRequest $request, VehicleInspection $inspection): RedirectResponse
    {
        $inspection->loadMissing('vehicle');

        $inspection = $this->inspections->update($inspection, $request->validated());

        // Arquivo novo substitui e apaga o anterior, então "remover" só vale sem arquivo novo.
        if ($request->hasFile('report')) {
            $this->inspections->attachReport($inspection, $request->file('report'));
        } elseif ($request->boolean('remove_report')) {
            $this->inspections->detachReport($inspection);
        }

        return redirect()->route('workshop.inspections.show', $inspection)
            ->with('success', 'Laudo atualizado.');
    }

    public function destroy(Request $request, VehicleInspection $inspection): RedirectResponse
    {
        $inspection->loadMissing('vehicle');

        Gate::authorize('delete', $inspection);

        // Pelo serviço, para o PDF sair do storage junto: laudo excluído não deixa arquivo órfão.
        $this->inspections->detachReport($inspection)->delete();

        return redirect()->route('workshop.inspections.index')
            ->with('success', 'Laudo excluído.');
    }

    public static function chassisOwnedMessage(): string
    {
        return 'Este chassi já tem proprietário no RevisaLog. Emita o laudo pela placa do veículo.';
    }

    public static function vehicleNotFoundMessage(string $licensePlate): string
    {
        return 'Veículo '.$licensePlate.' ainda não está no RevisaLog. Cadastre-o pelo chassi para emitir o laudo.';
    }

    public static function ownVehicleMessage(): string
    {
        return 'Este veículo está na sua própria conta. A vistoria cautelar é a palavra de quem não tem interesse na venda, então o laudo não pode ser emitido sobre o próprio carro.';
    }
}
