@extends('layouts.app')

@php
    $comparisonTitle = \App\Support\Seo\AppComparison::title();
    $comparisonDescription = \App\Support\Seo\AppComparison::metaDescription();
    $comparisonUrl = \App\Support\Seo\CanonicalUrl::named('guides.comparison');
@endphp

@section('title', $comparisonTitle)
@section('meta_description', $comparisonDescription)

@push('head')
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::webPage($comparisonTitle, $comparisonDescription, $comparisonUrl, \App\Support\Seo\AppComparison::verifiedOn())" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::breadcrumb([
        ['name' => 'Início', 'url' => \App\Support\Seo\CanonicalUrl::home()],
        ['name' => 'Comparativo de apps', 'url' => $comparisonUrl],
    ])" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::itemList('Apps de histórico de manutenção', \App\Support\Seo\AppComparison::listedApps())" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::faqPage(\App\Support\Seo\AppComparison::faq())" />
@endpush

@section('content')
<x-ui.container size="lg" padded>
    <x-ui.page-header
        eyebrow="Comparativo"
        :title="$comparisonTitle"
        :breadcrumbs="[['Início', \App\Support\Seo\CanonicalUrl::home()], ['Comparativo de apps']]"
    />

    <p class="max-w-3xl text-lg leading-relaxed text-pretty text-muted-foreground" data-slot="comparison-lead">{{ \App\Support\Seo\AppComparison::lead() }}</p>

    <div class="mt-10">
        <x-ui.table caption="Comparativo de apps de histórico de manutenção do carro" stack show-caption>
            <x-slot:head>
                <tr>
                    @foreach(\App\Support\Seo\AppComparison::columns() as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </x-slot:head>
            @foreach(\App\Support\Seo\AppComparison::rows() as $row)
                <tr>
                    <th scope="row">{{ $row['criterion'] }}</th>
                    @foreach($row['cells'] as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </x-ui.table>
        <p class="mt-4 text-sm text-muted-foreground">Informações dos concorrentes verificadas em {{ \App\Support\Seo\AppComparison::verifiedOnLabel() }} nas páginas oficiais. Onde a página não diz, a célula fica "não informado". Planilha e manual de papel são uma leitura nossa, não um produto de terceiro.</p>
    </div>

    <x-ui.section title="Fontes" class="mt-10" id="fontes">
        <ul role="list" class="space-y-3 text-sm text-muted-foreground">
            @foreach(\App\Support\Seo\AppComparison::sources() as $source)
                <li>
                    <x-ui.link :href="$source['url']" variant="inline" external>{{ $source['name'] }}</x-ui.link>
                    <span> — {{ $source['note'] }} Verificado em {{ \App\Support\Seo\AppComparison::verifiedOnLabel() }}.</span>
                </li>
            @endforeach
        </ul>
    </x-ui.section>

    <x-ui.section title="Como escolher" class="mt-10" id="como-escolher">
        <ul role="list" class="list-disc space-y-2 pl-5 text-muted-foreground">
            @foreach(\App\Support\Seo\AppComparison::choosing() as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </x-ui.section>

    <x-ui.section title="Quando o Drivvo ou o Carrorama fazem mais sentido" class="mt-10">
        <p class="text-muted-foreground">O Drivvo faz mais sentido para anotar combustível, despesas e serviços, com lembrete por data ou quilometragem, no Android, no iPhone ou no computador, de graça no uso pessoal. O Carrorama, no site atual, é uma plataforma de gestão de frota: o plano gratuito cobre até 3 veículos e o módulo de manutenção fala em custos, fornecedor, nota e alertas. Nas páginas consultadas, nenhum dos dois informa um selo público de oficina nem que o histórico passa para o próximo dono.</p>
    </x-ui.section>

    <x-ui.section title="Quando o RevisaLog faz mais sentido" class="mt-10">
        <p class="text-muted-foreground">O RevisaLog faz mais sentido para guardar o que foi feito no carro, com o serviço confirmado pela oficina e um PDF para a venda. O registro fica no chassi. Não controla abastecimento e não é sistema de gestão de frota.</p>
    </x-ui.section>

    <x-ui.section title="Outros apps que aparecem na busca" class="mt-10" id="outros-apps">
        <ul role="list" class="space-y-3 text-muted-foreground">
            <li>Fuelio registra combustível, custos e serviços no Android e no iPhone, sem login na web, grátis com Premium opcional, e passou a fazer parte da Sygic. A página não informa o país da empresa.</li>
            <li>Carango, da Tatu Mobi, registra combustível, despesas, serviços e lembretes. A ficha do Google Play consultada é do aplicativo Android. A página não informa um selo público de oficina.</li>
            <li>LembraCar monta o plano de serviços a partir do manual. No FAQ do site, o plano grátis tem lembrete por notificação, um veículo e controle de gastos, inclusive combustível. O Premium custa R$ 89 por ano e acrescenta laudo em PDF e WhatsApp.</li>
            <li>Autozis funciona na web, no Android, no iOS e no Windows, com manutenção, combustível, recarga e custos. O plano grátis cobre um veículo e a página nomeia David Moreira. O texto está em português europeu; não descrevemos a empresa como brasileira.</li>
        </ul>
    </x-ui.section>

    <x-ui.section title="Perguntas frequentes" class="mt-10" id="faq">
        <x-seo.faq-list :items="\App\Support\Seo\AppComparison::faq()" class="mt-0" />
    </x-ui.section>

    <x-ui.section title="Próximo passo" class="mt-10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <x-landing.app-store-badge />
            <x-ui.button variant="secondary" :href="route('verification.lookup')">Conferir um selo</x-ui.button>
        </div>
    </x-ui.section>

    <p class="mt-10 text-sm text-muted-foreground">Atualizado em {{ \App\Support\Seo\AppComparison::verifiedOnLabel() }}.</p>
</x-ui.container>
@endsection
