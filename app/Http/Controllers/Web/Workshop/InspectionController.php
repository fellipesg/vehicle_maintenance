<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\ResolvesWorkshopVehicle;
use App\Http\Requests\Web\Workshop\StoreVehicleInspectionRequest;
use App\Http\Requests\Web\Workshop\UpdateVehicleInspectionRequest;
use App\Models\VehicleInspection;
use App\Policies\VehicleInspectionPolicy;
use App\Services\Inspection\VehicleInspectionService;
use App\Services\Vehicle\WorkshopVehicleRegistrar;
use App\Support\Workshop\WorkshopVehicleStep;
use App\Support\Workshop\WorkshopVehicleVisibility;
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
 *
 * As quatro telas decidem a placa atual do veículo pelo **mesmo** mascaramento da etapa 1
 * (App\Support\Workshop\WorkshopVehicleVisibility, .ai/rules/public-lookup.md), e não por ter
 * emitido o laudo: emitir não cria OS, então o emissor não ganha vínculo por esse caminho, e tratar
 * o laudo como vínculo faria da emissão um oráculo de rotação de placa — digita-se uma placa antiga
 * que se conhece, emite-se um laudo e passa-se a ler a placa de hoje. O laudo inteiro é do emissor
 * (VehicleInspection::showsFullReportTo); o veículo de terceiro não é.
 */
class InspectionController extends Controller
{
    use ResolvesWorkshopVehicle;

    public function __construct(private readonly VehicleInspectionService $inspections) {}

    public function index(Request $request): View
    {
        $workshop = $request->user()->workshop;
        $inspections = null;
        $fullVehicleIds = [];

        if ($workshop !== null) {
            $inspections = $workshop->inspections()
                ->with('vehicle')
                ->orderByDesc('inspected_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString();

            // A página inteira em duas consultas, não duas por linha.
            $fullVehicleIds = WorkshopVehicleVisibility::fullVehicleIdsFor(
                $workshop,
                $request->user(),
                $inspections->pluck('vehicle_id')->all(),
            );
        }

        return view('workshop.inspections.index', [
            'workshop' => $workshop,
            'inspections' => $inspections,
            'fullVehicleIds' => $fullVehicleIds,
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

        $inspection = DB::transaction(function () use ($vehicle, $newVehicle, $chassis, $data, $request, $workshop): VehicleInspection {
            // Veículo criado pelo chassi nasce **sem** hodômetro: a leitura do laudo é do laudo, e
            // alimentar vehicles.current_kilometers com ela é o que .ai/rules/inspections.md proíbe.
            $vehicle ??= app(WorkshopVehicleRegistrar::class)->register(
                $request->user(),
                $chassis,
                (string) $newVehicle['brand'],
                (string) $newVehicle['model'],
                (int) $newVehicle['year'],
            );

            return $this->inspections->create($vehicle, $workshop, $request->user(), $data);
        });

        // Fora da transação, como a nota fiscal da OS: o I/O do storage não segura conexão aberta.
        if ($request->hasFile('report')) {
            $inspection = $this->inspections->attachReport($inspection, $request->file('report'));
        }

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
            'showsFullVehicle' => $this->showsFullVehicle($request, $inspection),
            // A tela é da oficina, mas o leitor pode ter chegado pelo ramo de tenant da policy: a
            // explicação da placa oculta não pode falar de "a sua oficina" para quem não tem uma.
            'viewerIsWorkshop' => $request->user()->workshop !== null,
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
            'showsFullVehicle' => $this->showsFullVehicle($request, $inspection),
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

    /**
     * Este leitor alcança a placa atual do veículo deste laudo?
     *
     * A mesma pergunta da lista (`WorkshopVehicleVisibility::fullVehicleIdsFor`), de propósito: a
     * união do vínculo de oficina com a posse de hoje. Perguntar só o vínculo esconde a placa do
     * próprio dono — a oficina cuja tenant comprou o carro depois de ter emitido o laudo, e a conta
     * que ainda não cadastrou oficina e chegou aqui pelo ramo de tenant de
     * `VehicleInspection::isVisibleInHistoryTo` (esse ramo compara o tenant do dono **na emissão**,
     * então aceitar só ele entregaria a placa de hoje a quem já vendeu o carro: o mesmo furo pelo
     * outro lado). Nada de `$foundByFullChassis`: a posse dos 17 caracteres libera o card no
     * instante da busca da etapa 1, e nada a registra para depois.
     */
    private function showsFullVehicle(Request $request, VehicleInspection $inspection): bool
    {
        $vehicle = $inspection->vehicle;

        return $vehicle !== null && WorkshopVehicleVisibility::showsFullVehicleTo(
            $request->user()->workshop,
            $request->user(),
            $vehicle,
        );
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
