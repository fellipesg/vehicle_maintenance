{{--
    Ficha do veículo, a mesma em todos os portais (Proprietário, Lojista, Admin e busca pública):

    1. Cabeçalho: o único H1 (x-ui.page-header) com "Marca Modelo", "Ano · Cor · Placa", o chassi
       (com Copiar) e as ações do portal. Com heading-level="h2" vira o título de um resultado
       (busca pública, onde o H1 é "Buscar histórico de veículo").
    2. Capa hero (x-vehicle-cover, foto inteira).
    3. Resumo em x-ui.stat: Km atual, Próxima revisão (com barra), Procedência e Total em itens. O
       chassi não se repete aqui.
    4. Abas (x-ui.tabs, com #linha-do-tempo, #historico e #documentos na URL):
       - Linha do tempo: x-vehicle-timeline e, abaixo dela, os pontos de procedência e o filtro
         Todas / Selo da oficina / Declaradas (.ai/rules/theme.md e provenance.md).
       - Histórico: as manutenções na MESMA ordem da linha do tempo (da mais antiga para a mais
         recente), com o mesmo filtro; cada card leva ao detalhe da manutenção no portal.
       - Documentos: placa, RENAVAM, CRV, motor e o histórico de placas (x-ui.table empilhada).

    Filtro de procedência: botões com aria-pressed num <form method="get"> (x-ui.segmented
    name="verified"). Sem JS, o envio recarrega com ?verified= e o servidor filtra; com JS
    (initProvenanceFilters de resources/js/provenance-ui.js), filtra no lugar e mantém a URL.

    Props:
    - vehicle (obrigatório).
    - portal: App\Enums\Portal (ou o valor) de quem vê; null na busca pública. Define os links.
    - timeline: array de App\Services\Vehicle\VehicleTimelineBuilder::build(); sem ele o
      componente monta.
    - heading-level: h1 (o padrão, x-ui.page-header) | h2 (resultado de busca).
    - breadcrumbs: trilha do x-ui.page-header (formato do x-ui.breadcrumb).
    - description: troca "Ano · Cor · Placa".
    - maintenance-url: link de cada manutenção. Closure(Maintenance), padrão com "{id}", false (sem
      link: os cards abrem "Ver serviços" no lugar) ou null (o padrão do portal).
    - edit-url: edição do veículo no portal (liga "Chassi não informado" e "Adicionar capa").
    - masked: mostra chassi e RENAVAM parciais e esconde CRV e motor (quem não é dono).
    - filter: procedência filtrada ('1', '0' ou ''); padrão: ?verified da URL.
    - filter-url: ação dos formulários de filtro (padrão: a URL atual).
    - show-documents: mostra a aba Documentos (padrão: sim).

    Slots:
    - actions: ações do cabeçalho, com o primário por último (ex.: "Registrar manutenção"; "Exportar
      PDF" com o link sem download de .ai/rules/js.md).
    - notice: aviso entre o resumo e as abas (ex.: status da consignação).
    - empty-actions: CTA do histórico vazio (ex.: "Registrar manutenção").
    - documents: conteúdo extra no fim da aba Documentos.

    Ex.: <x-vehicle.detail :vehicle="$vehicle" :portal="\App\Enums\Portal::Dealer"
             :breadcrumbs="[['Estoque', route('garage.vehicles.index')], [$vehicle->brand.' '.$vehicle->model]]">
             <x-slot:actions><x-ui.button icon="plus" :href="route('garage.maintenances.create', ['vehicle_id' => $vehicle->id])">Registrar manutenção</x-ui.button></x-slot:actions>
         </x-vehicle.detail>
--}}
@props([
    'vehicle',
    'portal' => null,
    'timeline' => null,
    'headingLevel' => 'h1',
    'breadcrumbs' => [],
    'description' => null,
    'maintenanceUrl' => null,
    'editUrl' => null,
    'masked' => false,
    'filter' => null,
    'filterUrl' => null,
    'showDocuments' => true,
])

@php
    use App\Models\Maintenance;
    use App\Services\Vehicle\VehicleTimelineBuilder;
    use App\Support\Maintenance\MaintenanceLinks;
    use App\Support\Vehicle\VehicleIdentifierMask;
    use App\Support\Vehicle\VehicleMaintenanceHistory;

    $detailHeadingLevel = \App\Support\UiProps::oneOf('x-vehicle.detail', 'heading-level', $headingLevel, ['h1', 'h2'], 'h1');
    $detailSectionHeading = $detailHeadingLevel === 'h1' ? 'h2' : 'h3';
    $detailCardHeading = $detailHeadingLevel === 'h1' ? 'h3' : 'h4';
    $detailPortal = MaintenanceLinks::portal($portal);
    $detailMasked = (bool) $masked;

    // A linha do tempo carrega as manutenções com itens, garantia, oficina e NF; o histórico reusa.
    if (is_array($timeline)) {
        $detailTimeline = $timeline;
        // Relação já carregada manda: pode vir limitada ao que o usuário pode ler
        // (Vehicle::restrictHistoryTo), e uma consulta nova aqui desfaria isso.
        $detailMaintenances = $vehicle->relationLoaded('maintenances')
            ? $vehicle->maintenances
            : $vehicle->maintenances()
                ->with(['items.warranty', 'generalWarranty', 'workshop', 'verifiedWorkshop', 'user', 'invoices'])
                ->withCount('photos')
                ->get();
        $detailMaintenances->loadMissing(['items.warranty', 'generalWarranty', 'workshop', 'verifiedWorkshop', 'user', 'invoices']);
        $detailMaintenances->loadCount('photos');
    } else {
        $detailTimeline = app(VehicleTimelineBuilder::class)->build($vehicle);
        $detailMaintenances = $vehicle->maintenances;
        $detailMaintenances->loadMissing(['verifiedWorkshop', 'user']);
        $detailMaintenances->loadCount('photos');
    }

    // Sem portal (busca pública), o card abre "Ver serviços" com as fotos do depois, as únicas públicas.
    if ($detailPortal === null) {
        $detailMaintenances->load(['photos' => fn ($query) => $query
            ->where('subject', \App\Models\MaintenancePhoto::SUBJECT_VEHICLE)
            ->where('stage', \App\Models\MaintenancePhoto::STAGE_AFTER)]);
    }

    // OS de oficina sem proprietário: forma mínima para quem não é a oficina autora; as ocultadas
    // pelo proprietário saem (App\Support\Maintenance\MaintenanceRedactor).
    $detailMaintenances = \App\Support\Maintenance\MaintenanceRedactor::redactAll($detailMaintenances, auth()->user());

    $detailHistory = VehicleMaintenanceHistory::inTimelineOrder($detailMaintenances);
    $detailById = $detailHistory->keyBy('id');
    $detailTotal = $detailHistory->count();
    $detailSealed = $detailHistory->filter(fn (Maintenance $maintenance): bool => $maintenance->isVerified())->count();
    $detailDeclared = $detailTotal - $detailSealed;
    $detailFilter = VehicleMaintenanceHistory::normalizeFilter($filter ?? request()->query('verified'));
    $detailVisible = $detailHistory->filter(fn (Maintenance $maintenance): bool => VehicleMaintenanceHistory::matchesFilter($maintenance, $detailFilter))->count();
    $detailLatest = $detailHistory->last();

    // Os pontos da faixa vêm da mesma coleção, sem outra consulta.
    $vehicle->setRelation('provenanceStripMaintenances', $detailHistory);
    $vehicle->setAttribute('maintenances_count', $detailTotal);
    $vehicle->setAttribute('verified_maintenances_count', $detailSealed);

    $detailMaintenanceUrl = MaintenanceLinks::resolver($maintenanceUrl, fn (Maintenance $maintenance): ?string => MaintenanceLinks::detailUrl($maintenance, $detailPortal));
    $detailUrlById = fn (int $maintenanceId): ?string => $detailById->has($maintenanceId) ? $detailMaintenanceUrl($detailById->get($maintenanceId)) : null;

    $detailTitle = trim($vehicle->brand.' '.$vehicle->model);
    $detailDescription = $description ?? collect([
        $vehicle->year,
        $vehicle->color,
        filled($vehicle->license_plate) ? 'Placa '.$vehicle->license_plate : null,
    ])->filter(fn (mixed $part): bool => filled($part))->implode(' · ');

    $detailSummary = $detailTimeline['summary'] ?? [];
    $detailNextDue = $detailSummary['next_due_kilometers'] ?? null;
    $detailRemaining = $detailSummary['kilometers_remaining'] ?? null;
    $detailOverdue = (bool) ($detailSummary['is_overdue'] ?? false);
    $detailProgress = (int) min(100, max(0, (float) ($detailSummary['odometer_progress_percent'] ?? $detailSummary['progress_percent'] ?? 0)));
    $detailAnnualKm = $detailSummary['approximate_annual_kilometers'] ?? null;
    $detailKm = fn (mixed $value): string => number_format((int) $value, 0, ',', '.').' km';
    $detailNextDueText = $detailNextDue === null
        ? null
        : ($detailOverdue ? 'Em atraso: a revisão era aos '.$detailKm($detailNextDue) : 'Faltam '.$detailKm($detailRemaining ?? 0));

    $detailFilterAction = $filterUrl ?? request()->url();
    $detailFilterQuery = collect(request()->query())->except(['verified', 'page'])->filter(fn (mixed $value): bool => is_scalar($value))->all();
    $detailFilterOptions = [
        ['value' => '', 'label' => 'Todas', 'count' => $detailTotal],
        ['value' => '1', 'label' => 'Selo da oficina', 'count' => $detailSealed],
        ['value' => '0', 'label' => 'Declaradas', 'count' => $detailDeclared],
    ];
    $detailClearUrl = $detailFilterAction.($detailFilterQuery !== [] ? '?'.http_build_query($detailFilterQuery) : '');
    $detailCountText = $detailFilter === ''
        ? VehicleMaintenanceHistory::countLabel($detailTotal)
        : 'Mostrando '.$detailVisible.' de '.VehicleMaintenanceHistory::countLabel($detailTotal);
    $detailPlates = $vehicle->relationLoaded('plates')
        ? $vehicle->plates
        : $vehicle->plates()->orderByDesc('started_at')->orderByDesc('id')->get();
    $detailRenavam = $detailMasked ? VehicleIdentifierMask::renavam($vehicle->renavam) : $vehicle->renavam;
    $detailDocuments = array_filter([
        'Placa atual' => filled($vehicle->license_plate) ? $vehicle->license_plate : ($detailMasked ? null : 'Placa não informada'),
        'RENAVAM' => $detailRenavam,
        'Número do CRV' => $detailMasked ? null : $vehicle->crv_number,
        'Motorização' => $vehicle->motorization,
        'Número do motor' => $detailMasked ? null : $vehicle->engine,
        'Ano' => $vehicle->year,
        'Cor' => $vehicle->color,
        'Quilometragem no cadastro' => $vehicle->odometer_at_registration !== null ? $detailKm($vehicle->odometer_at_registration) : null,
    ], fn (mixed $value): bool => filled($value));
    $detailMonoDocuments = ['Placa atual', 'RENAVAM', 'Número do CRV', 'Número do motor'];
    $detailHasActions = isset($actions) && ! \App\Support\UiProps::isBlank($actions);
    $detailHasNotice = isset($notice) && ! \App\Support\UiProps::isBlank($notice);
    $detailHasEmptyActions = isset($emptyActions) && ! \App\Support\UiProps::isBlank($emptyActions);
@endphp

<div {{ $attributes->class(['space-y-6']) }} data-vehicle-detail data-provenance-filter-root data-portal="{{ $detailPortal?->value ?? 'public' }}">
    @if ($detailHeadingLevel === 'h1')
        <x-ui.page-header :title="$detailTitle" :description="$detailDescription ?: null" :breadcrumbs="$breadcrumbs">
            <x-vehicle-identity :vehicle="$vehicle" size="hero" :edit-route="$editUrl" :masked="$detailMasked" :show-plate="false" />
            <x-slot:actions>{{ $actions ?? '' }}</x-slot:actions>
        </x-ui.page-header>
    @else
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between" data-slot="vehicle-detail-header">
            <div class="min-w-0">
                <h2 class="text-2xl font-bold tracking-tight text-balance text-foreground">{{ $detailTitle }}</h2>
                @if ($detailDescription !== '')
                    <p class="mt-1 text-sm text-muted-foreground">{{ $detailDescription }}</p>
                @endif
                <x-vehicle-identity :vehicle="$vehicle" size="hero" :edit-route="$editUrl" :masked="$detailMasked" :show-plate="false" class="mt-3" />
            </div>
            @if ($detailHasActions)
                <div class="flex flex-wrap items-center gap-2 max-sm:*:grow sm:shrink-0 sm:justify-end">{{ $actions }}</div>
            @endif
        </header>
    @endif

    <x-vehicle-cover :vehicle="$vehicle" variant="hero" :add-cover-url="$editUrl" />

    <div class="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4" data-slot="vehicle-detail-summary">
        <x-ui.stat
            label="Km atual"
            :value="$vehicle->current_kilometers !== null ? $detailKm($vehicle->current_kilometers) : 'Não informada'"
            :hint="$detailAnnualKm ? 'Cerca de '.$detailKm($detailAnnualKm).' por ano' : null"
            icon="map-pin"
        />
        <x-ui.stat
            label="Próxima revisão"
            :value="$detailNextDue !== null ? $detailKm($detailNextDue) : 'Sem estimativa'"
            :hint="$detailNextDueText ?? 'Registre uma manutenção com a quilometragem para estimar.'"
            icon="calendar-days"
        >
            @if ($detailNextDue !== null)
                <div
                    class="h-2 overflow-hidden rounded-full bg-surface-muted"
                    role="progressbar"
                    aria-label="Quilometragem até a próxima revisão"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ $detailProgress }}"
                    aria-valuetext="{{ $detailNextDueText }}"
                >
                    <div @class(['h-full rounded-full', 'bg-danger' => $detailOverdue, 'bg-primary' => ! $detailOverdue]) style="width: {{ $detailProgress }}%"></div>
                </div>
            @endif
        </x-ui.stat>
        <x-ui.stat
            label="Procedência"
            :value="$detailSealed.' com selo'"
            :hint="VehicleMaintenanceHistory::declaredLabel($detailDeclared)"
            icon="shield-check"
        />
        <x-ui.stat
            label="Total em itens"
            :value="'R$ '.number_format((float) ($detailSummary['total_spent'] ?? 0), 2, ',', '.')"
            :hint="'Em '.VehicleMaintenanceHistory::countLabel($detailTotal)"
            icon="document-text"
        />
    </div>

    @if ($detailHasNotice)
        <div data-slot="vehicle-detail-notice">{{ $notice }}</div>
    @endif

    <x-ui.tabs label="Seções do veículo" variant="line" sync-url>
        <x-slot:tabs>
            <x-ui.tab target="linha-do-tempo" :active="true" icon="clock">Linha do tempo</x-ui.tab>
            <x-ui.tab target="historico" icon="wrench-screwdriver" :badge="$detailTotal" badge-label="manutenções">Histórico</x-ui.tab>
            @if ($showDocuments)
                <x-ui.tab target="documentos" icon="document-text">Documentos</x-ui.tab>
            @endif
        </x-slot:tabs>

        <x-ui.tab-panel id="linha-do-tempo" :active="true">
            @if (count($detailTimeline['events'] ?? []) > 0)
                <x-vehicle-timeline
                    :timeline="$detailTimeline"
                    :maintenance-url="$detailUrlById"
                    :filter="$detailFilter"
                    :summary="false"
                    :heading-level="$detailSectionHeading"
                    id-prefix="timeline"
                >
                    <x-slot:footer>
                        @if ($detailTotal > 0)
                            <x-provenance-strip :vehicle="$vehicle" :summary="false" :dot-href="$detailUrlById" class="mb-0" />
                            <form method="get" action="{{ $detailFilterAction }}#linha-do-tempo" class="mt-2" data-provenance-filter-form data-submit-busy="off">
                                @foreach ($detailFilterQuery as $queryKey => $queryValue)
                                    <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                                @endforeach
                                <x-ui.segmented mode="buttons" name="verified" label="Filtrar a linha do tempo por procedência" controls="timeline-colunas" :options="$detailFilterOptions" :value="$detailFilter" />
                            </form>
                        @endif
                        <x-provenance-legend class="mt-4" />
                    </x-slot:footer>
                </x-vehicle-timeline>
            @else
                <x-ui.empty-state icon="clock" title="Nenhuma manutenção registrada" description="A linha do tempo começa quando o veículo tem a quilometragem do cadastro ou a primeira manutenção.">
                    @if ($detailHasEmptyActions)
                        <x-slot:actions>{{ $emptyActions }}</x-slot:actions>
                    @endif
                </x-ui.empty-state>
            @endif
        </x-ui.tab-panel>

        <x-ui.tab-panel id="historico">
            <x-ui.section id="historico-manutencoes" title="Histórico de manutenções" description="Da mais antiga para a mais recente, na mesma ordem da linha do tempo." :heading-level="$detailSectionHeading">
                @if ($detailTotal > 0)
                    <x-slot:actions>
                        <form method="get" action="{{ $detailFilterAction }}#historico" data-provenance-filter-form data-submit-busy="off">
                            @foreach ($detailFilterQuery as $queryKey => $queryValue)
                                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                            @endforeach
                            <x-ui.segmented mode="buttons" name="verified" label="Filtrar o histórico por procedência" controls="historico-lista" :options="$detailFilterOptions" :value="$detailFilter" />
                        </form>
                    </x-slot:actions>

                    <p
                        class="mb-3 text-sm text-muted-foreground"
                        aria-live="polite"
                        data-provenance-count
                        data-total="{{ $detailTotal }}"
                        data-template-all="{{ VehicleMaintenanceHistory::countLabel($detailTotal) }}"
                        data-template-filtered="Mostrando {shown} de {{ VehicleMaintenanceHistory::countLabel($detailTotal) }}"
                    >{{ $detailCountText }}</p>

                    <ol id="historico-lista" role="list" class="space-y-3" aria-label="Manutenções, da mais antiga à mais recente" data-provenance-list>
                        @foreach ($detailHistory as $maintenance)
                            @php
                                $historyUrl = $detailMaintenanceUrl($maintenance);
                            @endphp
                            <x-provenance-card
                                :maintenance="$maintenance"
                                as="li"
                                :heading-level="$detailCardHeading"
                                :href="$historyUrl"
                                :expandable="$historyUrl === null"
                                :hidden="! VehicleMaintenanceHistory::matchesFilter($maintenance, $detailFilter)"
                            />
                        @endforeach
                    </ol>

                    <div
                        data-provenance-empty
                        data-title-sealed="{{ VehicleMaintenanceHistory::emptyFilterTitle(VehicleMaintenanceHistory::FILTER_SEALED) }}"
                        data-title-declared="{{ VehicleMaintenanceHistory::emptyFilterTitle(VehicleMaintenanceHistory::FILTER_DECLARED) }}"
                        @unless ($detailFilter !== '' && $detailVisible === 0) hidden @endunless
                    >
                        <x-ui.empty-state
                            icon="funnel"
                            :title="VehicleMaintenanceHistory::emptyFilterTitle($detailFilter === '' ? VehicleMaintenanceHistory::FILTER_SEALED : $detailFilter)"
                            description="Mostre todas as manutenções para ver o histórico completo do veículo."
                        >
                            <x-slot:actions>
                                <x-ui.button variant="secondary" :href="$detailClearUrl.'#historico'" data-provenance-clear>Limpar filtros</x-ui.button>
                            </x-slot:actions>
                        </x-ui.empty-state>
                    </div>

                    @if ($detailTotal > 5 && $detailLatest)
                        <p class="mt-4">
                            <x-ui.link :href="'#manutencao-'.$detailLatest->id" icon="chevron-down">Ir para a mais recente</x-ui.link>
                        </p>
                    @endif
                @else
                    <x-ui.empty-state icon="wrench-screwdriver" title="Nenhuma manutenção registrada" description="As manutenções aparecem aqui, com a procedência de cada uma: Selo da oficina ou Declarada.">
                        @if ($detailHasEmptyActions)
                            <x-slot:actions>{{ $emptyActions }}</x-slot:actions>
                        @endif
                    </x-ui.empty-state>
                @endif
            </x-ui.section>
        </x-ui.tab-panel>

        @if ($showDocuments)
            <x-ui.tab-panel id="documentos" class="space-y-6">
                <x-ui.section id="documentos-veiculo" title="Documentos do veículo" :heading-level="$detailSectionHeading">
                    @if ($detailDocuments !== [])
                        <dl class="grid gap-x-6 gap-y-4 rounded-card border border-border bg-surface p-4 text-sm sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                            @foreach ($detailDocuments as $documentLabel => $documentValue)
                                <div class="min-w-0">
                                    <dt class="text-muted-foreground">{{ $documentLabel }}</dt>
                                    <dd @class(['mt-0.5 font-medium break-all text-foreground', 'font-mono tracking-wider' => in_array($documentLabel, $detailMonoDocuments, true)])>{{ $documentValue }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                    @if ($detailMasked)
                        <p class="mt-3 text-xs text-muted-foreground">RENAVAM parcial: o número completo, o CRV e o motor aparecem só para o dono do veículo.</p>
                    @endif
                    @isset($documents)
                        <div class="mt-4">{{ $documents }}</div>
                    @endisset
                </x-ui.section>

                <x-ui.section id="historico-placas" title="Histórico de placas" :heading-level="$detailSectionHeading">
                    <x-ui.table caption="Histórico de placas" stack empty="Nenhuma placa registrada">
                        <x-slot:head>
                            <tr>
                                <th>Placa</th>
                                <th>De</th>
                                <th>Até</th>
                                <th>Origem</th>
                            </tr>
                        </x-slot:head>
                        @foreach ($detailPlates as $plateRow)
                            <tr>
                                <th scope="row" class="font-mono font-semibold tracking-wider">{{ $plateRow->plate }}</th>
                                <td class="tabular-nums">{{ $plateRow->started_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="tabular-nums">{{ $plateRow->ended_at?->format('d/m/Y') ?? 'Vigente' }}</td>
                                <td>{{ \App\Models\VehiclePlate::sourceLabel($plateRow->source) }}</td>
                            </tr>
                        @endforeach
                    </x-ui.table>
                </x-ui.section>
            </x-ui.tab-panel>
        @endif
    </x-ui.tabs>
</div>
