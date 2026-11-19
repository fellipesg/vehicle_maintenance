{{--
    Laudo de vistoria cautelar na conferência pública (/laudo/{código}), irmã de public.verification.

    **Sem o contrato visual de procedência** (.prov-seal, .prov-verified, x-provenance-marker): o
    anel teal diz "Selo da oficina", que é a afirmação "eu fiz este serviço". O laudo afirma outra
    coisa — "eu examinei e atesto o que encontrei" —, e misturar as duas dilui a única primitiva de
    confiança do produto (ver App\Models\VehicleInspection::getProvenanceLabelAttribute). Aqui quem
    carrega o significado é o veredito, com a variante de badge do próprio enum.

    O que chega nesta view já passou por App\Support\Inspection\InspectionRedactor: para quem não tem
    o histórico do veículo, resumo, observações de cada eixo e PDF vêm nulos, e os oito status ficam.
    A view não decide nada disso — ela desenha o que recebeu.

    Variáveis: $inspection (já redigido), $showsFullReport, $maskedChassis, $publicUrl, $qrSvg.
--}}
@extends('layouts.app')

@section('title', 'Laudo de vistoria cautelar')

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
@php
    use App\Enums\InspectionFinding;
    use App\Support\Inspection\InspectionFindings;

    $vehicle = $inspection->vehicle;
    $vehicleLabel = $vehicle
        ? collect([$vehicle->brand, $vehicle->model, $vehicle->year])->filter(fn (mixed $part): bool => filled($part))->implode(' · ')
        : '';
    $issuer = $inspection->provenance_sublabel;
    $findings = InspectionFindings::normalize($inspection->findings);
    $shareUrl = $publicUrl ?? url()->current();
    $termClass = 'text-xs font-semibold tracking-wide text-muted-foreground uppercase';
    $valueClass = 'mt-1 text-sm font-medium text-foreground';
@endphp
<x-ui.container size="sm" padded>
    @if ($inspection->isDisputed())
        <x-ui.alert variant="warning" role="status" class="mb-6" title="O proprietário contesta este laudo" data-inspection-disputed>
            O laudo é autêntico e foi assinado pela empresa abaixo, mas o proprietário atual do veículo não reconhece o que ele afirma. A RevisaLog está analisando. Leve isso em conta.
        </x-ui.alert>
    @endif

    @if ($inspection->isExpired())
        <x-ui.alert variant="info" role="status" class="mb-6" title="Este laudo está vencido" data-inspection-expired>
            A validade terminou em {{ $inspection->valid_until->format('d/m/Y') }}. O que ele afirma vale para o dia da vistoria: o carro rodou desde então.
        </x-ui.alert>
    @endif

    <x-ui.page-header eyebrow="Verificação pública RevisaLog" title="Laudo de vistoria cautelar">
        <x-slot:description>
            {{ $issuer }} examinou este veículo em {{ $inspection->inspected_at->format('d/m/Y') }} e registrou o laudo na plataforma RevisaLog.
        </x-slot:description>
    </x-ui.page-header>

    <div class="space-y-6">
        <x-ui.card as="section" title="Resultado da vistoria" heading-level="h2" data-inspection-verdict>
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.badge :variant="$inspection->verdict->badgeVariant()" size="lg">{{ $inspection->verdict->label() }}</x-ui.badge>
                @if ($inspection->isExpired())
                    <x-ui.badge variant="neutral">Vencido em {{ $inspection->valid_until->format('d/m/Y') }}</x-ui.badge>
                @endif
            </div>

            <dl class="mt-6 grid gap-4 border-t border-border pt-6 sm:grid-cols-2">
                @if ($vehicleLabel !== '')
                    <div>
                        <dt class="{{ $termClass }}">Veículo</dt>
                        <dd class="{{ $valueClass }}">{{ $vehicleLabel }}</dd>
                    </div>
                @endif
                @if ($maskedChassis)
                    <div>
                        <dt class="{{ $termClass }}">Chassi</dt>
                        <dd class="mt-1">
                            <span class="block font-mono text-sm tracking-wider text-foreground">{{ $maskedChassis }}</span>
                            <span class="mt-1 block text-xs text-muted-foreground">Parcial, para proteger o proprietário.</span>
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="{{ $termClass }}">Data da vistoria</dt>
                    <dd class="{{ $valueClass }} tabular-nums">
                        <time datetime="{{ $inspection->inspected_at->format('Y-m-d') }}">{{ $inspection->inspected_at->format('d/m/Y') }}</time>
                    </dd>
                </div>
                <div>
                    <dt class="{{ $termClass }}">Hodômetro lido</dt>
                    <dd class="{{ $valueClass }} tabular-nums" data-inspection-odometer>
                        @if ($inspection->odometer_km === null)
                            <span class="font-normal text-muted-foreground">Não informado</span>
                        @else
                            {{ number_format((int) $inspection->odometer_km, 0, ',', '.') }} km
                            <span class="mt-1 block text-xs font-normal text-muted-foreground">Leitura do dia da vistoria.</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="{{ $termClass }}">Validade</dt>
                    <dd class="{{ $valueClass }} tabular-nums">
                        @if ($inspection->valid_until === null)
                            <span class="font-normal text-muted-foreground">Sem validade</span>
                        @else
                            <time datetime="{{ $inspection->valid_until->format('Y-m-d') }}">{{ $inspection->valid_until->format('d/m/Y') }}</time>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="{{ $termClass }}">Assinado por</dt>
                    <dd class="{{ $valueClass }}">{{ $issuer }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="{{ $termClass }}">Código de conferência</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="mr-1 font-mono text-base font-semibold tracking-wider text-foreground">{{ $inspection->verification_code }}</span>
                        <x-ui.copy-button :value="$inspection->verification_code" label="Copiar código" copied-label="Código copiado" />
                        <x-ui.copy-button :value="$shareUrl" label="Copiar link" copied-label="Link copiado" />
                        {{-- Só aparece (resources/js/provenance-actions.js) onde o navegador tem compartilhamento nativo. --}}
                        <x-ui.button variant="secondary" size="sm" data-share-verification :data-url="$shareUrl" hidden>Compartilhar</x-ui.button>
                    </dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card as="section" title="O que foi examinado" heading-level="h2"
                   description="Item por item, na ordem do laudo. “Não avaliado” é o que a vistoria não olhou — laudo que afirma mais do que examinou é pior que laudo incompleto."
                   data-inspection-findings>
            <ul role="list" class="divide-y divide-border">
                @foreach (InspectionFinding::cases() as $axis)
                    @php
                        $status = InspectionFindings::statusOf($findings, $axis);
                        $note = InspectionFindings::noteOf($findings, $axis);
                    @endphp
                    <li class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4" data-finding="{{ $axis->value }}">
                        <div class="min-w-0">
                            <p class="font-medium text-foreground">{{ $axis->label() }}</p>
                            @if (filled($note))
                                <p class="mt-0.5 max-w-prose text-sm text-muted-foreground">{{ $note }}</p>
                            @endif
                        </div>
                        <x-ui.badge :variant="$status->badgeVariant()" class="shrink-0" data-finding-status>{{ $status->label() }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>

            @unless ($showsFullReport)
                <x-slot:footer class="border-t border-border pt-4">
                    <p class="text-sm text-muted-foreground" data-inspection-redacted>
                        O resumo escrito pelo vistoriador, as observações de cada item e o laudo em PDF ficam com o proprietário do veículo e com a empresa que assinou. O resultado de cada item, acima, é público.
                    </p>
                </x-slot:footer>
            @endunless
        </x-ui.card>

        @if ($showsFullReport && (filled($inspection->summary) || filled($inspection->report_path)))
            <x-ui.card as="section" title="Resumo e laudo em PDF" heading-level="h2" data-inspection-report>
                @if (filled($inspection->summary))
                    <p class="max-w-prose text-sm whitespace-pre-line text-foreground">{{ $inspection->summary }}</p>
                @endif

                @if (filled($inspection->report_path))
                    <p class="mt-4">
                        {{-- Nunca pelo caminho do storage: a rota responde a mesma pergunta que a redação (.ai/rules/views.md). --}}
                        <x-ui.link :href="route('inspections.report', $inspection)" icon="document-text" class="break-all">
                            {{ filled($inspection->report_original_name) ? $inspection->report_original_name : 'Abrir o laudo em PDF' }}
                        </x-ui.link>
                    </p>
                @endif
            </x-ui.card>
        @endif

        @if ($qrSvg)
            <details class="group rounded-card border border-border bg-surface p-4 shadow-sm sm:p-6">
                <summary class="flex min-h-10 cursor-pointer list-none items-center gap-2 font-semibold text-foreground [&::-webkit-details-marker]:hidden">
                    <x-ui.icon name="qr-code" class="size-5 text-muted-foreground" />
                    QR code deste laudo
                    <x-ui.icon name="chevron-down" class="ml-auto size-4 text-muted-foreground transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
                </summary>
                <p class="mt-3 text-sm text-muted-foreground">Imprima junto do laudo ou mostre em outro aparelho para abrir esta mesma página.</p>
                <div class="mt-4 inline-block rounded-control bg-surface p-2" role="img" aria-label="QR code para conferir este laudo">{!! $qrSvg !!}</div>
            </details>
        @endif

        <nav aria-label="Sobre a conferência pública" class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
            <x-ui.link :href="route('verification.lookup')">Conferir outro código</x-ui.link>
            @guest
                <x-ui.link :href="route('home')">Conheça o RevisaLog</x-ui.link>
            @endguest
        </nav>
    </div>
</x-ui.container>
@endsection
