@extends('layouts.app')

@section('title', 'Código não encontrado')

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
<x-ui.container size="sm" padded>
    <x-ui.page-header
        eyebrow="Verificação pública RevisaLog"
        title="Código não encontrado"
        description="Nenhum Selo da oficina corresponde a este código. Confira se ele foi digitado como aparece no relatório ou no QR code, no formato RVL-XXXX-XX."
    />

    <div class="space-y-6">
        <x-ui.card as="section" title="Digite o código de novo" heading-level="h2">
            @include('public._verification-code-form', [
                'typedCode' => $typedCode ?? '',
                'codeError' => null,
                'autofocus' => false,
            ])
        </x-ui.card>

        <x-ui.card as="section" title="Por que o código pode não aparecer" heading-level="h2">
            <ul role="list" class="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                <li>Erro de digitação: o código não usa as letras I e O nem os números 0 e 1.</li>
                <li>O serviço foi declarado pelo proprietário ou pelo lojista. Só serviços registrados por uma oficina cadastrada recebem o Selo da oficina e o código.</li>
                <li>O link foi copiado pela metade. Abra pelo QR code do relatório ou digite o código acima.</li>
            </ul>
            <x-slot:footer class="border-t border-border pt-4">
                <x-ui.button variant="secondary" icon="chat-bubble-left-right" :href="route('contact.show', ['assunto' => 'support'])">Falar com o suporte</x-ui.button>
                <x-ui.button variant="ghost" icon="home" :href="route('home')">Voltar ao início</x-ui.button>
            </x-slot:footer>
        </x-ui.card>

        <p class="text-center text-sm">
            <x-ui.link :href="route('home').'#procedencia'">O que é o Selo da oficina?</x-ui.link>
        </p>
    </div>
</x-ui.container>
@endsection
