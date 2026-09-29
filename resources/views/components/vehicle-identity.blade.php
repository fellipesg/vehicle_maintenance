{{--
    Identidade do veículo: chassi (o identificador que não muda) e a placa atual.

    Props:
    - vehicle (obrigatório).
    - size: card (o padrão) | hero (chassi maior e o x-ui.copy-button "Copiar chassi").
    - edit-route: URL de edição do veículo no portal de quem está vendo. Sem ela o aviso
      "Chassi não informado" não vira link: não adivinhamos rota, porque user.vehicles.edit só serve
      ao proprietário (lojista, oficina e busca pública recebiam 403).
    - masked: mostra o chassi parcial (App\Support\Vehicle\VehicleIdentifierMask), sem copiar,
      para quem não é dono (busca pública).
    - show-plate: mostra "Placa atual" (padrão: sim). A ficha já diz a placa na descrição do
      cabeçalho e passa :show-plate="false".
--}}
@props([
    'vehicle',
    'size' => 'card',
    'editRoute' => null,
    'masked' => false,
    'showPlate' => true,
])

@php
    $isHero = $size === 'hero';
    $isMasked = (bool) $masked;
    $chassisClass = $isHero
        ? 'font-mono tracking-wider font-semibold text-foreground text-lg sm:text-xl break-all'
        : 'font-mono tracking-wider font-semibold text-foreground text-sm break-all';
    $chassis = filled($vehicle->chassis) ? (string) $vehicle->chassis : null;
    $displayChassis = $isMasked ? \App\Support\Vehicle\VehicleIdentifierMask::chassis($chassis) : $chassis;
    $plate = $vehicle->license_plate;
    $editUrl = is_string($editRoute) && $editRoute !== '' ? $editRoute : null;
    $missingChassisClass = 'inline-flex w-fit items-center gap-1 rounded-full border border-warning/25 bg-warning-soft px-2.5 py-0.5 text-xs font-medium text-warning';
@endphp

<div {{ $attributes->class(['space-y-1'])->merge(['data-slot' => 'vehicle-identity']) }}>
    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Chassi</p>
    @if ($chassis)
        <div class="flex flex-wrap items-center gap-2">
            <p class="{{ $chassisClass }}" data-chassis-value @if ($isMasked) aria-describedby="chassi-parcial-{{ $vehicle->id }}" @endif>{{ $displayChassis }}</p>
            @if ($isHero && ! $isMasked)
                <x-ui.copy-button :value="$chassis" label="Copiar chassi" copied-label="Chassi copiado" />
            @endif
        </div>
        @if ($isMasked)
            <p id="chassi-parcial-{{ $vehicle->id }}" class="text-xs text-muted-foreground">Chassi parcial: o número completo aparece só para o dono do veículo.</p>
        @endif
    @elseif ($editUrl)
        <a href="{{ $editUrl }}" class="{{ $missingChassisClass }} underline-offset-2 hover:underline">
            Chassi não informado
            <span class="sr-only">(informar agora)</span>
        </a>
    @else
        <span class="{{ $missingChassisClass }}">Chassi não informado</span>
    @endif
    @if ($plate && $showPlate)
        <p class="pt-1">
            <span class="inline-flex items-center gap-1.5 rounded-md border border-border-strong px-2 py-0.5 text-xs text-muted-foreground">
                Placa atual <span class="font-mono font-semibold tracking-wider text-foreground">{{ $plate }}</span>
            </span>
        </p>
    @endif
</div>
