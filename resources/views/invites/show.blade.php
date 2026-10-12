@extends('layouts.app')

@section('title', 'Serviço registrado no seu carro')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
<x-ui.container size="sm" padded>
    <x-ui.page-header title="Uma oficina registrou um serviço no seu carro">
        <x-slot:description>A oficina {{ $workshopName }} registrou um serviço no seu {{ $vehicleLabel }} em {{ $serviceDate }}.</x-slot:description>
    </x-ui.page-header>

    <x-ui.card padding="lg">
        <div class="space-y-4">
            <p class="text-sm text-muted-foreground">
                Crie sua conta grátis para ver o registro e guardar o histórico do carro. Você precisa do CRLV-e
                do veículo para confirmar que ele é seu. Nada entra no seu histórico sem a sua escolha.
            </p>
            <div class="flex flex-wrap gap-2">
                @auth
                    <x-ui.button :href="route('user.workshop-records.index')">Ver registros de oficinas</x-ui.button>
                @else
                    <x-ui.button :href="route('register')">Criar conta grátis</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('login.usuario')">Entrar</x-ui.button>
                @endauth
            </div>
        </div>
    </x-ui.card>
</x-ui.container>
@endsection
