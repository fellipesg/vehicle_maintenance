{{--
    Mock do PDF do histórico (aba "PDF" do showcase da landing), com o veículo de
    App\Support\LandingSampleVehicle: identidade no cabeçalho e os serviços do mais antigo ao mais
    novo, quilometragem subindo, marcador com glifo e a procedência escrita ("Selo da oficina" ou
    "Declarada"). O PDF é do dono, então chassi e RENAVAM saem inteiros. É uma imagem para leitor de
    tela (role="img"), com a descrição no aria-label.
--}}
@php
    $pdfEvents = \App\Support\LandingSampleVehicle::events();
    $pdfLabel = 'Exemplo do PDF do histórico do '.\App\Support\LandingSampleVehicle::MODEL.' '.\App\Support\LandingSampleVehicle::YEAR
        .', placa '.\App\Support\LandingSampleVehicle::PLATE.': '.count($pdfEvents).' serviços do mais antigo ao mais recente, de '
        .\App\Support\LandingSampleVehicle::kilometers($pdfEvents[0]['km']).' a '.\App\Support\LandingSampleVehicle::kilometers($pdfEvents[count($pdfEvents) - 1]['km'])
        .', '.\App\Support\LandingSampleVehicle::provenanceSummary().'.';
@endphp
<div role="img" aria-label="{{ $pdfLabel }}" {{ $attributes->class(['mx-auto w-full max-w-sm rounded-card bg-surface p-6 text-left shadow-2xl ring-1 ring-border']) }} data-landing-pdf-mock>
    <div class="flex items-center justify-between gap-3 border-b border-border pb-3">
        <p class="text-sm font-bold text-foreground">RevisaLog</p>
        <p class="text-xs text-muted-foreground">Histórico de manutenções</p>
    </div>
    <p class="mt-4 font-mono text-lg font-semibold tracking-wider text-foreground">{{ \App\Support\LandingSampleVehicle::PLATE }}</p>
    <p class="text-sm text-muted-foreground">{{ \App\Support\LandingSampleVehicle::MODEL }} · {{ \App\Support\LandingSampleVehicle::YEAR }}</p>
    <p class="mt-1 font-mono text-xs break-all text-muted-foreground">Chassi {{ \App\Support\LandingSampleVehicle::CHASSIS }} · RENAVAM {{ \App\Support\LandingSampleVehicle::RENAVAM }}</p>

    <ol class="mt-5 space-y-2">
        @foreach ($pdfEvents as $event)
            <li @class([
                'flex items-start gap-3 rounded-control px-3 py-2',
                'border border-border bg-background' => $event['sealed'],
                'border border-dashed border-prov-declared bg-prov-declared-surface' => ! $event['sealed'],
            ])>
                <x-landing.provenance-glyph :sealed="$event['sealed']" class="mt-0.5" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="truncate text-sm font-medium text-foreground">{{ $event['title'] }}</p>
                        <p class="shrink-0 text-xs font-semibold text-foreground tabular-nums">{{ \App\Support\LandingSampleVehicle::kilometers($event['km']) }}</p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <span @class(['font-medium', 'text-prov-verified' => $event['sealed'], 'text-prov-declared' => ! $event['sealed']])>{{ $event['sealed'] ? 'Selo da oficina' : 'Declarada' }}</span>@if ($event['invoice']) · NF-e @endif · {{ $event['date'] }}
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
</div>
