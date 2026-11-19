{{--
    Card de uma vistoria cautelar na ficha do veículo, irmão de <x-provenance-card> e deliberadamente
    diferente dele.

    **Sem o contrato visual de procedência** (.prov-card, .prov-rail, .prov-marker, .prov-seal): o
    trilho e o marcador dizem "Selo da oficina", que é a afirmação "eu fiz este serviço". O laudo
    afirma "eu examinei e atesto o que encontrei", e misturar as duas dilui a única primitiva de
    confiança do produto (.ai/rules/theme.md tem exatamente dois termos; o laudo não é um terceiro
    tom dos mesmos). Quem carrega o significado aqui é o veredito, com a variante de badge do
    próprio enum — a mesma de /laudo/{código}.

    O card mostra só os eixos **com algo a dizer** (atenção e reprovado): a lista dos oito, com
    "Não avaliado" item por item, é o corpo da página pública, não de um card de lista. O que aparece
    depende do que chegou: quem monta a lista passa a coleção por
    App\Support\Inspection\InspectionRedactor::redactAll, então para quem não tem o histórico do
    veículo o resumo e as observações já vêm nulos e o card não as desenha. A view não decide isso.

    Props:
    - inspection (obrigatório). Carregue workshop para o emissor sair sem consulta por card.
    - as: li (o padrão, a lista da ficha) | div | article.
    - heading-level: p (o padrão) | h2 | h3 | h4.
    - href: destino do título. O padrão é a conferência pública /laudo/{código} — é a única tela de
      detalhe que existe para quem não é a oficina emissora, e é a mesma que o comprador abre.
      Passe :href="false" para o card não virar link.
    - anchor: id="vistoria-{id}" (o padrão), para a ficha poder linkar um laudo.

    Ex.: <x-inspection.card :inspection="$inspection" as="li" heading-level="h3" />
--}}
@props([
    'inspection',
    'as' => 'li',
    'headingLevel' => 'p',
    'href' => null,
    'anchor' => true,
])

@php
    use App\Enums\InspectionFinding;
    use App\Support\Inspection\InspectionFindings;

    $cardTag = \App\Support\UiProps::oneOf('x-inspection.card', 'as', $as, ['li', 'div', 'article'], 'li');
    $cardHeadingTag = \App\Support\UiProps::oneOf('x-inspection.card', 'heading-level', $headingLevel, ['p', 'h2', 'h3', 'h4'], 'p');

    $verdict = $inspection->verdict;
    $verdictLabel = $verdict?->label() ?? 'Sem veredito';
    $verdictVariant = $verdict?->badgeVariant() ?? 'neutral';

    // false desliga o link; null (o padrão) cai na conferência pública, que existe só com código.
    $cardHref = $href === false || $inspection->verification_code === null
        ? null
        : ($href ?? route('inspections.public', $inspection->verification_code));

    $issuer = $inspection->provenance_sublabel;
    $odometer = $inspection->odometer_km !== null
        ? number_format((int) $inspection->odometer_km, 0, ',', '.').' km lidos'
        : null;

    // Só os eixos com algo a dizer, na ordem do enum, agrupados pelo que o laudo afirma deles.
    $findings = $inspection->findings;
    $reportable = [];
    foreach (InspectionFinding::cases() as $axis) {
        $status = InspectionFindings::statusOf($findings, $axis);

        if ($status->isReportable()) {
            $reportable[$status->value]['status'] = $status;
            $reportable[$status->value]['axes'][] = $axis->label();
        }
    }
@endphp

<{{ $cardTag }}
    @if ($anchor) id="vistoria-{{ $inspection->id }}" @endif
    {{ $attributes->class(['scroll-mt-20']) }}
    data-inspection-card="{{ $inspection->id }}"
    data-verdict="{{ $verdict?->value ?? '' }}"
>
    <x-ui.card :href="$cardHref" :label="$cardHref ? 'Laudo '.$inspection->verification_code : null" padding="sm">
        <x-slot:header>
            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge :variant="$verdictVariant" data-inspection-verdict>{{ $verdictLabel }}</x-ui.badge>
                        @if ($inspection->isExpired())
                            <x-ui.badge variant="neutral" size="sm" data-inspection-expired>Vencido</x-ui.badge>
                        @endif
                        @if ($inspection->isDisputed())
                            <x-ui.badge variant="warning" size="sm" data-inspection-disputed>Contestado pelo proprietário</x-ui.badge>
                        @endif
                    </div>
                    <{{ $cardHeadingTag }} class="mt-1.5 font-semibold text-foreground">Vistoria cautelar</{{ $cardHeadingTag }}>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ $issuer }}</p>
                </div>
                <time
                    datetime="{{ $inspection->inspected_at->format('Y-m-d') }}"
                    class="shrink-0 text-sm text-muted-foreground tabular-nums"
                >{{ $inspection->inspected_at->format('d/m/Y') }}</time>
            </div>
        </x-slot:header>

        <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            @if ($odometer)
                <div class="flex gap-1.5">
                    <dt class="text-muted-foreground">Hodômetro</dt>
                    <dd class="font-medium text-foreground tabular-nums">{{ $odometer }}</dd>
                </div>
            @endif
            <div class="flex gap-1.5">
                <dt class="text-muted-foreground">Validade</dt>
                <dd class="font-medium text-foreground tabular-nums">
                    @if ($inspection->valid_until === null)
                        <span class="font-normal text-muted-foreground">sem validade</span>
                    @else
                        <time datetime="{{ $inspection->valid_until->format('Y-m-d') }}">{{ $inspection->valid_until->format('d/m/Y') }}</time>
                    @endif
                </dd>
            </div>
            @if ($inspection->verification_code !== null)
                <div class="flex gap-1.5">
                    <dt class="text-muted-foreground">Código</dt>
                    <dd class="font-mono font-medium tracking-wider text-foreground">{{ $inspection->verification_code }}</dd>
                </div>
            @endif
        </dl>

        @if ($reportable !== [])
            <ul role="list" class="mt-3 space-y-1.5 text-sm" data-inspection-findings>
                @foreach ($reportable as $group)
                    <li class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <x-ui.badge :variant="$group['status']->badgeVariant()" size="sm" class="shrink-0">{{ $group['status']->label() }}</x-ui.badge>
                        <span class="min-w-0 text-foreground">{{ implode(' · ', $group['axes']) }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-3 text-sm text-muted-foreground" data-inspection-findings="none">
                Nenhum item com apontamento. Abra o laudo para ver o que foi examinado e o que a vistoria não olhou.
            </p>
        @endif

        @if (filled($inspection->summary))
            <p class="mt-3 max-w-prose text-sm whitespace-pre-line text-muted-foreground" data-inspection-summary>{{ $inspection->summary }}</p>
        @endif

        @if (filled($inspection->report_path))
            <x-slot:footer class="mt-3">
                {{-- Nunca pelo caminho do storage: a rota responde a mesma pergunta que a redação (.ai/rules/views.md). --}}
                <x-ui.link :href="route('inspections.report', $inspection)" icon="document-text" class="break-all" data-inspection-report>
                    {{ filled($inspection->report_original_name) ? $inspection->report_original_name : 'Abrir o laudo em PDF' }}
                </x-ui.link>
            </x-slot:footer>
        @endif
    </x-ui.card>
</{{ $cardTag }}>
