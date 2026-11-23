<?php

namespace App\Support\Seo;

use App\Models\BlogPost;

/**
 * Resumo em texto puro para assistentes. Os links são as rotas da aplicação,
 * para o arquivo servido em qualquer ambiente apontar para as páginas reais.
 */
final class LlmsTxt
{
    public static function body(): string
    {
        $home = CanonicalUrl::home();
        $workshops = CanonicalUrl::named('workshops.landing');
        $signup = CanonicalUrl::named('workshops.signup');
        $verify = CanonicalUrl::named('verification.lookup');
        $comparison = CanonicalUrl::named('guides.comparison');
        $contact = CanonicalUrl::named('contact.show');
        $blog = CanonicalUrl::named('blog.index');
        $terms = CanonicalUrl::named('legal.terms');
        $privacy = CanonicalUrl::named('legal.privacy');
        $store = (string) config('app.ios_app_store_url');
        $email = (string) config('legal.support_email');
        $articles = self::articleLines();

        return <<<TXT
        # RevisaLog

        > RevisaLog é um aplicativo brasileiro para iPhone (e site) em que o dono do carro guarda o histórico de manutenção do veículo. O histórico fica vinculado ao chassi, passa para o próximo dono na venda, e os serviços feitos por oficinas cadastradas recebem o Selo da oficina, com código conferível em {$verify}. O dono exporta um PDF do histórico para vender o carro. Grátis no lançamento. Android em breve.

        ## O que o RevisaLog é
        - App de histórico de manutenção para donos de carro (consumidor), no iPhone: {$store}
        - Histórico preso ao veículo (chassi, placa ou RENAVAM), não à conta do dono
        - Selo da oficina: serviço confirmado pela oficina, com código RVL-XXXX-XX
        - Declaração do proprietário ou do lojista: registro sem código
        - PDF do histórico, com a linha do tempo, o código do selo e as notas fiscais em PDF incorporadas ao arquivo
        - Oficinas: cadastro grátis, ordem de serviço com fotos, itens, garantia e nota, lembretes automáticos aos clientes por e-mail e notificação no app
        - O dono também recebe lembrete por e-mail e notificação quando a quilometragem estimada se aproxima da próxima revisão

        ## O que o RevisaLog não é
        - Não é sistema de gestão de frota
        - Não é CRM ou ERP de oficina
        - Não envia alertas por WhatsApp (alertas: e-mail e notificação no app)
        - Não é consulta de débitos, leilão ou sinistro
        - Ainda não tem app Android
        - Não controla abastecimento nem gastos do carro

        ## Páginas principais
        - [Início]({$home}): o que é e como funciona
        - [Para oficinas]({$workshops})
        - [Cadastro de oficina]({$signup})
        - [Conferir selo ou laudo]({$verify})
        - [Comparativo de apps de histórico de manutenção]({$comparison})
        - [Blog]({$blog})
        - [Contato]({$contact})
        - [Termos de uso]({$terms})
        - [Privacidade]({$privacy})

        ## Contato
        {$email} · Fundador: Felipe Gonçalves
        {$articles}
        TXT;
    }

    /**
     * Só entra o artigo que já está publicado. Sem o seeder, o link não aparece
     * e o arquivo não aponta para uma página 404.
     */
    private static function articleLines(): string
    {
        $slugs = [
            'perdi-o-manual-de-revisoes-como-comprovar',
            'planilha-ou-aplicativo-manutencao-do-carro',
            'alternativas-ao-manual-de-revisoes-de-papel',
            'como-verificar-se-revisoes-do-carro-usado-sao-verdadeiras',
            'como-o-revisalog-guarda-o-historico-do-seu-carro',
            'selo-da-oficina-manutencao-verificada',
            'vender-o-carro-com-historico-de-manutencao',
            'historico-de-carro-usado-como-consultar',
        ];

        $posts = BlogPost::query()->published()->whereIn('slug', $slugs)->get()->keyBy('slug');
        $lines = [];

        foreach ($slugs as $slug) {
            $post = $posts->get($slug);

            if ($post === null) {
                continue;
            }

            $lines[] = '- ['.$post->title.']('.CanonicalUrl::named('blog.show', $post).')';
        }

        if ($lines === []) {
            return '';
        }

        return "\n## Artigos\n".implode("\n", $lines)."\n";
    }
}
