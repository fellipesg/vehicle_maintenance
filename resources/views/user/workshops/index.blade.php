{{--
    Oficinas da rede (proprietário), renderizadas no servidor: busca por nome, cidade ou bairro na
    URL (?busca=), paginação e, em cada oficina, o logo (ou as iniciais), o endereço, o Selo da
    oficina que ela emite e as ações: Ligar (tel:), WhatsApp, E-mail (mailto:) e "Registrar
    manutenção aqui" (o formulário já vem com a oficina escolhida).
--}}
@extends('layouts.app')

@section('title', 'Oficinas da rede')

@php
    $directoryDigits = fn (?string $value): string => preg_replace('/\D/', '', (string) $value) ?? '';
    $directoryPhoneLabel = function (?string $value) use ($directoryDigits): ?string {
        $digits = $directoryDigits($value);

        return match (strlen($digits)) {
            11 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
            10 => sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
            0 => null,
            default => (string) $value,
        };
    };
    $directoryPhoneHref = function (?string $value) use ($directoryDigits): ?string {
        $digits = $directoryDigits($value);

        return match (true) {
            $digits === '' => null,
            in_array(strlen($digits), [10, 11], true) => 'tel:+55'.$digits,
            default => 'tel:'.$digits,
        };
    };
    $directoryWhatsappHref = function (?string $value) use ($directoryDigits): ?string {
        $digits = $directoryDigits($value);

        return in_array(strlen($digits), [10, 11], true) ? 'https://wa.me/55'.$digits : null;
    };
    $directoryInitials = fn (string $name): string => \Illuminate\Support\Str::of($name)
        ->explode(' ')
        ->filter(fn (string $part): bool => $part !== '' && mb_strlen($part) > 2)
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('') ?: mb_strtoupper(mb_substr($name, 0, 2));
    $directoryHasSearch = $search !== '';
@endphp

@section('content')
    <x-ui.container padded data-owner-page="workshops">
        <x-ui.page-header
            title="Oficinas da rede"
            description="Oficinas cadastradas no RevisaLog. O serviço que elas registram entra no histórico do veículo com o Selo da oficina."
            :breadcrumbs="[['Início', route('user.dashboard')], ['Oficinas da rede']]"
        />

        <form method="GET" action="{{ route('user.workshops.index') }}" role="search" aria-label="Buscar oficinas" class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end" data-submit-busy="off">
            <x-ui.field name="busca" label="Buscar oficina por nome, cidade ou bairro" label-sr-only class="flex-1">
                <x-ui.input type="search" id="workshop-search" :value="$search" placeholder="Nome, cidade ou bairro" leading-icon="magnifying-glass" autocomplete="off" enterkeyhint="search" maxlength="100" />
            </x-ui.field>
            <div class="flex gap-2 max-sm:*:grow">
                <x-ui.button type="submit" icon="magnifying-glass">Buscar</x-ui.button>
                @if ($directoryHasSearch)
                    <x-ui.button variant="secondary" icon="x-mark" :href="route('user.workshops.index')">Limpar busca</x-ui.button>
                @endif
            </div>
        </form>

        @if ($workshops->isEmpty())
            @if ($directoryHasSearch)
                <x-ui.empty-state
                    icon="magnifying-glass"
                    heading-level="h2"
                    :title="'Nenhuma oficina para “'.$search.'”'"
                    description="Tente outro bairro ou cidade, ou só uma parte do nome da oficina."
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('user.workshops.index')">Limpar busca</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="building-storefront"
                    heading-level="h2"
                    title="Nenhuma oficina cadastrada ainda"
                    description="Quando as oficinas da sua região entrarem no RevisaLog, elas aparecem aqui."
                />
            @endif
        @else
            <p class="mb-4 text-sm text-muted-foreground" role="status" data-workshops-count>
                @if ($directoryHasSearch)
                    {{ $workshops->total() === 1 ? '1 oficina encontrada' : number_format($workshops->total(), 0, ',', '.').' oficinas encontradas' }} para “{{ $search }}”
                @else
                    {{ $workshops->total() === 1 ? '1 oficina na rede' : number_format($workshops->total(), 0, ',', '.').' oficinas na rede' }}
                @endif
            </p>

            <ul role="list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-workshops-list>
                @foreach ($workshops as $workshop)
                    @php
                        $workshopLogo = $workshop->logoUrl();
                        $workshopPhone = $directoryPhoneLabel($workshop->phone);
                        $workshopPhoneHref = $directoryPhoneHref($workshop->phone);
                        $workshopWhatsappHref = $directoryWhatsappHref($workshop->whatsapp);
                        $workshopPlace = collect([
                            $workshop->neighborhood,
                            filled($workshop->city) ? $workshop->city.(filled($workshop->state) ? '/'.$workshop->state : '') : null,
                        ])->filter(fn ($part): bool => filled($part))->implode(' · ');
                    @endphp
                    <li class="flex flex-col gap-4 rounded-card border border-border bg-surface p-4 text-foreground shadow-sm sm:p-5" data-workshop-id="{{ $workshop->id }}">
                        <div class="flex items-start gap-3">
                            @if ($workshopLogo)
                                <img src="{{ $workshopLogo }}" alt="Logo da {{ $workshop->name }}" loading="lazy" class="size-12 shrink-0 rounded-control border border-border bg-surface object-contain p-1">
                            @else
                                <span class="inline-flex size-12 shrink-0 items-center justify-center rounded-control bg-accent text-sm font-semibold text-accent-foreground" aria-hidden="true">{{ $directoryInitials($workshop->name) }}</span>
                            @endif
                            <div class="min-w-0 space-y-1">
                                <h2 class="text-base leading-6 font-semibold text-foreground">{{ $workshop->name }}</h2>
                                @if ($workshopPlace !== '')
                                    <p class="flex items-start gap-1 text-sm text-muted-foreground">
                                        <x-ui.icon name="map-pin" class="mt-0.5 size-4 shrink-0" />
                                        <span>{{ $workshopPlace }}</span>
                                    </p>
                                @endif
                                <x-ui.badge variant="seal" size="sm">Emite Selo da oficina</x-ui.badge>
                            </div>
                        </div>

                        @if ($workshopPhone || $workshop->email)
                            <dl class="grid gap-1 text-sm">
                                @if ($workshopPhone)
                                    <div class="flex gap-2">
                                        <dt class="text-muted-foreground">Telefone</dt>
                                        <dd class="font-medium tabular-nums">{{ $workshopPhone }}</dd>
                                    </div>
                                @endif
                                @if ($workshop->email)
                                    <div class="flex min-w-0 gap-2">
                                        <dt class="text-muted-foreground">E-mail</dt>
                                        <dd class="min-w-0 truncate font-medium">{{ $workshop->email }}</dd>
                                    </div>
                                @endif
                            </dl>
                        @endif

                        <div class="mt-auto flex flex-wrap gap-2 border-t border-border pt-4">
                            @if ($workshopPhoneHref)
                                <x-ui.button variant="secondary" size="sm" icon="phone" :href="$workshopPhoneHref">Ligar<span class="sr-only"> para {{ $workshop->name }}</span></x-ui.button>
                            @endif
                            @if ($workshopWhatsappHref)
                                <x-ui.button variant="secondary" size="sm" icon="chat-bubble-left-right" :href="$workshopWhatsappHref" target="_blank" rel="noopener">WhatsApp<span class="sr-only"> da {{ $workshop->name }} (abre em nova aba)</span></x-ui.button>
                            @endif
                            @if ($workshop->email)
                                <x-ui.button variant="secondary" size="sm" icon="envelope" :href="'mailto:'.$workshop->email">E-mail<span class="sr-only"> para {{ $workshop->name }}</span></x-ui.button>
                            @endif
                            <x-ui.button variant="secondary" size="sm" icon="wrench-screwdriver" :href="route('user.maintenances.create', ['workshop_id' => $workshop->id])" class="sm:ml-auto">Registrar manutenção aqui<span class="sr-only">: {{ $workshop->name }}</span></x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($workshops->hasPages())
                <div class="mt-6">{{ $workshops->links() }}</div>
            @endif
        @endif
    </x-ui.container>
@endsection
