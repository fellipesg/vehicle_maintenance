@extends('layouts.app')

@section('title', 'Selo da oficina confirmado')

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
@php
    $workshopName = $maintenance->verifiedWorkshop?->name ?? $maintenance->workshop?->name ?? $maintenance->workshop_name;
    $vehicle = $maintenance->vehicle;
    $vehicleLabel = $vehicle
        ? collect([$vehicle->brand, $vehicle->model, $vehicle->year])->filter(fn (mixed $part): bool => filled($part))->implode(' · ')
        : '';
    // Horário de Brasília, o mesmo do PDF do histórico (App\Support\DisplayTime).
    $sealedAt = \App\Support\DisplayTime::local($maintenance->verified_at);
    $updatedAt = \App\Support\DisplayTime::local($maintenance->updated_at);
    $updatedAfterSeal = $maintenance->wasUpdatedAfterSeal();
    $shareUrl = $verificationUrl ?? url()->current();
    $termClass = 'text-xs font-semibold tracking-wide text-muted-foreground uppercase';
    $valueClass = 'mt-1 text-sm font-medium text-foreground';
@endphp
<x-ui.container size="sm" padded>
    <x-ui.page-header eyebrow="Verificação pública RevisaLog" title="Selo da oficina confirmado">
        <x-slot:description>
            @if ($workshopName)
                {{ $workshopName }} registrou este serviço
            @else
                Uma oficina cadastrada registrou este serviço
            @endif
            @if ($sealedAt)
                em {{ $sealedAt->format('d/m/Y') }} às {{ $sealedAt->format('H:i') }}
            @endif
            na plataforma RevisaLog.
        </x-slot:description>
    </x-ui.page-header>

    <div class="space-y-6">
        {{-- Contrato de procedência (.ai/rules/theme.md): anel teal e marcador com o logo da oficina. --}}
        <section class="prov-seal prov-verified bg-surface" aria-labelledby="selo-oficina-titulo" data-slot="verification-seal">
            <div class="sm:p-2">
                <div class="flex items-center gap-4">
                    <x-provenance-marker :maintenance="$maintenance" size="lg" />
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wide text-[color:var(--prov-ink)] uppercase">Selo da oficina</p>
                        <h2 id="selo-oficina-titulo" class="text-lg font-semibold text-balance text-foreground">{{ $workshopName ?? 'Oficina cadastrada no RevisaLog' }}</h2>
                    </div>
                </div>

                <dl class="mt-6 grid gap-4 border-t border-border pt-6 sm:grid-cols-2">
                    <div>
                        <dt class="{{ $termClass }}">Serviço</dt>
                        <dd class="{{ $valueClass }}">{{ $maintenance->maintenance_type }}</dd>
                    </div>
                    @if ($maintenance->maintenance_date)
                        <div>
                            <dt class="{{ $termClass }}">Data do serviço</dt>
                            <dd class="{{ $valueClass }} tabular-nums">
                                <time datetime="{{ $maintenance->maintenance_date->format('Y-m-d') }}">{{ $maintenance->maintenance_date->format('d/m/Y') }}</time>
                            </dd>
                        </div>
                    @endif
                    @if ($updatedAfterSeal)
                        <div data-updated-after-seal>
                            <dt class="{{ $termClass }}">Atualizada em</dt>
                            <dd class="{{ $valueClass }}">
                                <time datetime="{{ $updatedAt->toIso8601String() }}" class="tabular-nums">{{ $updatedAt->format('d/m/Y') }}</time>
                                <span class="mt-1 block text-xs font-normal text-muted-foreground">A oficina alterou a OS depois de emitir o selo.</span>
                            </dd>
                        </div>
                    @endif
                    @if ($maintenance->kilometers)
                        <div>
                            <dt class="{{ $termClass }}">Quilometragem</dt>
                            <dd class="{{ $valueClass }} tabular-nums">{{ number_format($maintenance->kilometers, 0, ',', '.') }} km</dd>
                        </div>
                    @endif
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
                    <div class="sm:col-span-2">
                        <dt class="{{ $termClass }}">Código de verificação</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-2">
                            <span class="mr-1 font-mono text-base font-semibold tracking-wider text-foreground">{{ $maintenance->verification_code }}</span>
                            {{-- Copiar: x-ui.copy-button (check por 1,5s e o aviso anunciado ao leitor de tela). --}}
                            <x-ui.copy-button :value="$maintenance->verification_code" label="Copiar código" copied-label="Código copiado" />
                            <x-ui.copy-button :value="$shareUrl" label="Copiar link" copied-label="Link copiado" />
                            {{-- Só aparece (resources/js/provenance-actions.js) onde o navegador tem compartilhamento nativo. --}}
                            <x-ui.button variant="secondary" size="sm" data-share-verification :data-url="$shareUrl" hidden>Compartilhar</x-ui.button>
                        </dd>
                    </div>
                </dl>

                <p class="mt-6">
                    <x-ui.badge icon="lock-closed" size="lg">Não pode ser alterado pelo proprietário</x-ui.badge>
                </p>
            </div>
        </section>

        @if ($qrSvg)
            <details class="group rounded-card border border-border bg-surface p-4 shadow-sm sm:p-6">
                <summary class="flex min-h-10 cursor-pointer list-none items-center gap-2 font-semibold text-foreground [&::-webkit-details-marker]:hidden">
                    <x-ui.icon name="qr-code" class="size-5 text-muted-foreground" />
                    QR code desta verificação
                    <x-ui.icon name="chevron-down" class="ml-auto size-4 text-muted-foreground transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
                </summary>
                <p class="mt-3 text-sm text-muted-foreground">Imprima junto do relatório ou mostre em outro aparelho para abrir esta mesma página.</p>
                <div class="mt-4 inline-block rounded-control bg-surface p-2" role="img" aria-label="QR code para conferir este selo">{!! $qrSvg !!}</div>
            </details>
        @endif

        <nav aria-label="Sobre o Selo da oficina" class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
            <x-ui.link :href="route('home').'#procedencia'">O que é o Selo da oficina?</x-ui.link>
            <x-ui.link :href="route('verification.lookup')">Conferir outro código</x-ui.link>
            @guest
                <x-ui.link :href="route('home')">Conheça o RevisaLog</x-ui.link>
            @endguest
        </nav>
    </div>
</x-ui.container>
@endsection
