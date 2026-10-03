@extends('layouts.app')

@section('title', 'Conferir selo da oficina')

@push('head')
    <meta name="description" content="Digite o código de verificação do relatório do RevisaLog para confirmar um serviço com Selo da oficina.">
@endpush

@section('content')
<x-ui.container size="sm" padded>
    <x-ui.page-header
        eyebrow="Selo da oficina"
        title="Conferir selo da oficina"
        description="Digite o código de verificação do relatório para confirmar que o serviço foi registrado por uma oficina cadastrada no RevisaLog."
    />

    <div class="space-y-6">
        <x-ui.card>
            @include('public._verification-code-form', [
                'typedCode' => $typedCode,
                'codeError' => $codeError,
                'autofocus' => $typedCode === '',
            ])
        </x-ui.card>

        <x-ui.card as="section" title="Onde encontrar o código" heading-level="h2">
            <ul role="list" class="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                <li>No relatório em PDF do veículo, ao lado de cada serviço com Selo da oficina.</li>
                <li>No QR code impresso no relatório: a câmera do celular abre esta conferência direto.</li>
                <li>Só serviços registrados por uma oficina cadastrada têm código. Manutenções declaradas pelo proprietário ou pelo lojista não têm.</li>
            </ul>
        </x-ui.card>

        <nav aria-label="Sobre o Selo da oficina" class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
            <x-ui.link :href="route('home').'#procedencia'">O que é o Selo da oficina?</x-ui.link>
            @guest
                <x-ui.link :href="route('home')">Conheça o RevisaLog</x-ui.link>
            @endguest
        </nav>
    </div>
</x-ui.container>
@endsection
