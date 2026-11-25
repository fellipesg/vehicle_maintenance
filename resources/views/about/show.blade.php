@extends('layouts.app')

@php
    $aboutTitle = 'Sobre o RevisaLog';
    $aboutDescription = 'O que é o RevisaLog, como os registros de manutenção funcionam, o que ele não garante e quem escreve o blog. Grátis no lançamento, app para iPhone.';
    $aboutUrl = \App\Support\Seo\CanonicalUrl::named('about');
    $supportEmail = (string) config('legal.support_email');
    $authorName = \App\Support\Seo\BlogAuthor::OWNER_NAME;
@endphp

@section('title', $aboutTitle)
@section('meta_description', $aboutDescription)

@push('head')
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::aboutPage($aboutTitle, $aboutDescription, $aboutUrl)" />
    <x-seo.json-ld :data="\App\Support\Seo\StructuredData::breadcrumb([
        ['name' => 'Início', 'url' => \App\Support\Seo\CanonicalUrl::home()],
        ['name' => 'Sobre', 'url' => $aboutUrl],
    ])" />
@endpush

@section('content')
<x-ui.container size="lg" padded>
    <x-ui.page-header
        :title="$aboutTitle"
        :breadcrumbs="[['Início', \App\Support\Seo\CanonicalUrl::home()], ['Sobre']]"
    />

    <div class="max-w-[56ch] space-y-10 text-base leading-7 text-muted-foreground" data-slot="about-content">
        <x-ui.section title="O que é o RevisaLog" id="o-que-e">
            <p>{{ \App\Support\Seo\HomeFaq::definition() }}</p>
            <p>O registro fica vinculado ao veículo, não à conta de quem o anotou: quando o carro é vendido, o histórico continua nele. Hoje o app está disponível para iPhone, na <x-ui.link :href="config('app.ios_app_store_url')" variant="inline" external>App Store</x-ui.link>, e o site funciona no navegador do celular e do computador. A versão para Android ainda não foi lançada.</p>
        </x-ui.section>

        <x-ui.section title="Como os registros funcionam" id="como-funciona">
            <p>Cada serviço no histórico tem uma de duas origens, e a tela sempre mostra qual:</p>
            <ul role="list" class="list-disc space-y-2 pl-5">
                <li><strong class="text-foreground">Selo da oficina</strong>: uma oficina cadastrada registrou o serviço. O selo tem um código (no formato RVL-XXXX-XX) que qualquer pessoa confere em <x-ui.link :href="route('verification.lookup')" variant="inline">Conferir selo ou laudo</x-ui.link>, sem precisar de conta.</li>
                <li><strong class="text-foreground">Declarada</strong>: o proprietário ou o lojista registrou por conta própria, por exemplo o que fez sozinho ou já tinha no papel. Não tem código e nenhuma oficina da rede confirmou.</li>
            </ul>
            <p>Buscar um veículo pela placa, pelo chassi ou pelo RENAVAM exige uma conta gratuita. O proprietário também pode exportar o histórico em PDF, por exemplo para a venda.</p>
        </x-ui.section>

        <x-ui.section title="O que o RevisaLog não garante" id="limites">
            <ul role="list" class="list-disc space-y-2 pl-5">
                <li>O histórico mostra só o que foi registrado na plataforma. Um carro sem registros não é um carro sem manutenção.</li>
                <li>Em regra, não verificamos a veracidade do que o dono ou o lojista declara, e não garantimos que o histórico esteja completo. Só o Selo da oficina indica que uma oficina cadastrada registrou o serviço.</li>
                <li>Não é consulta de débitos, de leilão ou de sinistro, e não substitui a vistoria de quem vai comprar um carro.</li>
            </ul>
            <p>As regras completas estão nos <x-ui.link :href="route('legal.terms')" variant="inline">Termos de uso</x-ui.link> e na <x-ui.link :href="route('legal.privacy')" variant="inline">Política de privacidade</x-ui.link>.</p>
        </x-ui.section>

        <x-ui.section title="Quanto custa" id="preco">
            <p>No lançamento o RevisaLog é grátis para o dono do carro e não pede cartão. Oficinas se cadastram pelo site; os detalhes estão em <x-ui.link :href="route('workshops.landing')" variant="inline">Para oficinas</x-ui.link>.</p>
        </x-ui.section>

        <x-ui.section title="Como os artigos do blog são feitos" id="blog">
            <p>O <x-ui.link :href="route('blog.index')" variant="inline">blog</x-ui.link> explica manutenção, documentação e compra e venda de carros. Nos assuntos de regra, prazo e intervalo de troca, o texto parte de fontes oficiais, como o manual do fabricante, o Código de Trânsito, o Contran, o Detran e o Procon, e os guias citam essas fontes no fim. Onde a regra muda de estado para estado ou a dúvida é jurídica, o artigo orienta a consultar o órgão competente em vez de afirmar.</p>
            <p>Os artigos não substituem a avaliação de um mecânico nem orientação jurídica. Se encontrar um erro ou algo desatualizado, avise pelo <x-ui.link :href="route('contact.show')" variant="inline">contato</x-ui.link>, com o endereço do artigo.</p>
        </x-ui.section>

        <x-ui.section title="Quem escreve" id="autor" class="scroll-mt-24">
            <p><strong class="text-foreground" data-slot="about-author-name">{{ $authorName }}</strong> é o fundador e o responsável pelo RevisaLog e assina os artigos do blog.</p>
            @if($authorBio !== '')
                <p data-slot="about-author-bio">{{ $authorBio }}</p>
            @endif
        </x-ui.section>

        <x-ui.section title="Contato" id="contato">
            <p>Dúvidas, suporte, oficinas e pedidos sobre dados pessoais: <x-ui.link :href="route('contact.show')" variant="inline">fale com a gente</x-ui.link> ou escreva para <x-ui.link :href="'mailto:'.$supportEmail" variant="inline">{{ $supportEmail }}</x-ui.link>.</p>
        </x-ui.section>
    </div>
</x-ui.container>
@endsection
