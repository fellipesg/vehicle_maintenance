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
    - dispute-url: rota de contestação deste laudo (POST contesta, DELETE retira). Passe só para
      quem pode contestar — o dono atual do veículo, respondido por
      App\Services\Inspection\InspectionDisputeService::canDispute. Null (o padrão) não desenha
      ação nenhuma; a marca "Contestado pelo proprietário" continua aparecendo para todo mundo,
      porque ela é informação de quem está comprando, não uma ação.

    Ex.: <x-inspection.card :inspection="$inspection" as="li" heading-level="h3" />
--}}
@props([
    'inspection',
    'as' => 'li',
    'headingLevel' => 'p',
    'href' => null,
    'anchor' => true,
    'disputeUrl' => null,
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

        @if (filled($inspection->report_path) || $disputeUrl)
            {{--
                Rodapé, e não corpo: o card com href desenha um link esticado por cima de tudo
                (<x-ui.card>), e só os slots action e footer ficam clicáveis acima dele. Formulário
                no corpo seria coberto por esse link.
            --}}
            <x-slot:footer class="mt-3">
                <div class="w-full space-y-3">
                    @if (filled($inspection->report_path))
                        {{-- Nunca pelo caminho do storage: a rota responde a mesma pergunta que a redação (.ai/rules/views.md). --}}
                        <x-ui.link :href="route('inspections.report', $inspection)" icon="document-text" class="break-all" data-inspection-report>
                            {{ filled($inspection->report_original_name) ? $inspection->report_original_name : 'Abrir o laudo em PDF' }}
                        </x-ui.link>
                    @endif

                    @if ($disputeUrl && $inspection->isDisputed())
                        <x-ui.alert variant="warning" role="status" title="Você contestou este laudo" data-inspection-dispute-alert>
                            O laudo continua no histórico do veículo e na conferência pública, agora com o aviso de que
                            você discorda — quem for comprar o carro vê as duas versões. Avisamos a oficina que o
                            emitiu, que é quem pode corrigir ou excluir, e a nossa equipe analisa.
                            @if (filled($inspection->owner_dispute_reason))
                                <span class="mt-1 block">O que você informou: {{ $inspection->owner_dispute_reason }}</span>
                            @endif
                            <x-slot:actions>
                                <form method="POST" action="{{ $disputeUrl }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Retirando…">Retirar contestação</x-ui.button>
                                </form>
                            </x-slot:actions>
                        </x-ui.alert>
                    @elseif ($disputeUrl)
                        <details class="rounded-lg border border-border p-3" data-inspection-dispute-form>
                            <summary class="cursor-pointer text-sm font-medium text-foreground">Não concordo com este laudo</summary>
                            <form method="POST" action="{{ $disputeUrl }}" class="mt-3 space-y-3">
                                @csrf
                                <p class="text-sm text-muted-foreground">
                                    O laudo <strong>não sai</strong> do histórico: ele continua à vista de quem consultar
                                    o veículo, marcado como contestado por você. Essa é a diferença em relação a
                                    esconder — quem vai comprar precisa saber que houve contestação, e não encontrar um
                                    carro sem laudo nenhum. Avisamos a oficina que emitiu, que é quem pode corrigir ou
                                    excluir, e a nossa equipe analisa. Você não precisa do CRLV-e para isso.
                                </p>
                                {{-- O rótulo vem do x-ui.field: x-ui.textarea não tem prop label. --}}
                                <x-ui.field name="reason" label="O que você contesta" optional>
                                    <x-ui.textarea
                                        :id="'inspection-dispute-reason-'.$inspection->id"
                                        rows="2"
                                        maxlength="500"
                                        placeholder="Ex.: o chassi do laudo não é o do meu carro."
                                    />
                                </x-ui.field>
                                <x-ui.button variant="secondary" type="submit" loading-label="Contestando…">Contestar laudo</x-ui.button>
                            </form>
                        </details>
                    @endif
                </div>
            </x-slot:footer>
        @endif
    </x-ui.card>
</{{ $cardTag }}>
