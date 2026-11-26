@extends('layouts.app')

@section('title', 'Conferir selo ou laudo')

@php
    $verifyDescription = 'Digite o código de verificação do RevisaLog para confirmar um serviço com Selo da oficina ou um laudo de vistoria cautelar.';
@endphp

@section('meta_description', $verifyDescription)

@push('head')
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::webPage('Conferir selo ou laudo', $verifyDescription, \App\Support\Seo\CanonicalUrl::named('verification.lookup'))" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::breadcrumb([
        ['name' => 'Início', 'url' => \App\Support\Seo\CanonicalUrl::home()],
        ['name' => 'Conferir selo ou laudo', 'url' => \App\Support\Seo\CanonicalUrl::named('verification.lookup')],
    ])" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::faqPage(\App\Support\Seo\VerificationFaq::items())" />
@endpush

@section('content')
<x-ui.container size="sm" padded>
    <x-ui.page-header
        eyebrow="Verificação pública RevisaLog"
        title="Conferir selo ou laudo"
        description="Digite o código para confirmar um serviço com Selo da oficina (RVL) ou um laudo de vistoria cautelar (LDO) registrado no RevisaLog."
        :breadcrumbs="[['Início', \App\Support\Seo\CanonicalUrl::home()], ['Conferir selo ou laudo']]"
    />

    <div class="space-y-6">
        <x-ui.card>
            @include('public._verification-code-form', [
                'typedCode' => $typedCode,
                'codeError' => $codeError,
                'autofocus' => $typedCode === '',
            ])
        </x-ui.card>

        <x-ui.card as="section" title="O que é o código" heading-level="h2">
            <p class="text-sm leading-relaxed text-muted-foreground">{{ \App\Support\Seo\VerificationFaq::intro() }}</p>
        </x-ui.card>

        <x-ui.section title="Perguntas frequentes" id="faq">
            <x-seo.faq-list :items="\App\Support\Seo\VerificationFaq::items()" class="mt-0" />
        </x-ui.section>

        <x-ui.card as="section" title="Onde encontrar o código" heading-level="h2">
            <ul role="list" class="list-disc space-y-2 pl-5 text-sm text-muted-foreground">
                <li>No relatório em PDF do veículo, ao lado de cada serviço com Selo da oficina (RVL-XXXX-XX).</li>
                <li>No laudo entregue pela empresa que fez a vistoria cautelar (LDO-XXXX-XX).</li>
                <li>No QR code impresso: a câmera do celular abre esta conferência direto.</li>
                <li>Só serviços registrados por uma oficina cadastrada têm código. Manutenções declaradas pelo proprietário ou pelo lojista não têm.</li>
            </ul>
        </x-ui.card>

        <nav aria-label="Sobre a conferência pública" class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-sm">
            <x-ui.link :href="route('home').'#procedencia'">O que é o Selo da oficina?</x-ui.link>
            @guest
                <x-ui.link :href="route('home')">Conheça o RevisaLog</x-ui.link>
            @endguest
        </nav>
    </div>
</x-ui.container>
@endsection
