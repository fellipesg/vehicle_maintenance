{{--
    Mock da busca por placa, chassi ou RENAVAM (aba "Busca" do showcase da landing), com o veículo
    de App\Support\LandingSampleVehicle. Mostra o que a busca mostra para quem não é o dono: chassi e
    RENAVAM parciais (VehicleIdentifierMask) e o selo "Dados parciais". Um ponto de procedência por
    serviço, do mais antigo ao mais novo. É uma imagem para leitor de tela (role="img"), com a
    descrição no aria-label.
--}}
@php
    $searchEvents = \App\Support\LandingSampleVehicle::events();
    $searchLabel = 'Exemplo de busca pela placa '.\App\Support\LandingSampleVehicle::PLATE.': '
        .\App\Support\LandingSampleVehicle::MODEL.' '.\App\Support\LandingSampleVehicle::YEAR.', '
        .\App\Support\LandingSampleVehicle::provenanceSummary().', com chassi e RENAVAM parciais para quem não é o dono.';
@endphp
<div role="img" aria-label="{{ $searchLabel }}" {{ $attributes->class(['mx-auto w-full max-w-sm rounded-card border border-border bg-surface p-5 text-left shadow-2xl']) }} data-landing-search-mock>
    <p class="text-sm font-medium text-foreground">Placa, chassi ou RENAVAM</p>
    <div class="mt-2 flex items-center gap-2 rounded-control border border-input bg-surface px-3 py-2.5 font-mono text-sm tracking-wider text-foreground">
        <x-ui.icon name="magnifying-glass" class="size-4 text-muted-foreground" />
        {{ \App\Support\LandingSampleVehicle::PLATE }}
    </div>
    <div class="mt-3 flex items-center justify-center gap-2 rounded-control bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground">
        <x-ui.icon name="magnifying-glass" class="size-4" />
        Buscar
    </div>

    <div class="mt-5 rounded-card border border-border bg-background p-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-foreground">{{ \App\Support\LandingSampleVehicle::MODEL }} · {{ \App\Support\LandingSampleVehicle::YEAR }}</p>
                <p class="font-mono text-xs tracking-wider text-muted-foreground">{{ \App\Support\LandingSampleVehicle::PLATE }}</p>
            </div>
            <x-ui.badge icon="lock-closed" size="sm">Dados parciais</x-ui.badge>
        </div>
        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Chassi</dt>
                <dd class="font-mono text-foreground">{{ \App\Support\LandingSampleVehicle::maskedChassis() }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">RENAVAM</dt>
                <dd class="font-mono text-foreground">{{ \App\Support\LandingSampleVehicle::maskedRenavam() }}</dd>
            </div>
        </dl>
        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-border pt-3">
            <span class="flex items-center gap-1" data-landing-dots>
                @foreach ($searchEvents as $event)
                    <span @class(['prov-dot', 'prov-dot--verified' => $event['sealed'], 'prov-dot--declared' => ! $event['sealed']])></span>
                @endforeach
            </span>
            <span class="text-xs text-muted-foreground">{{ \App\Support\LandingSampleVehicle::provenanceSummary() }}</span>
        </div>
    </div>
</div>
