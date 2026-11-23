<?php

namespace App\Support\Seo;

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

        return <<<TXT
        # RevisaLog

        > RevisaLog é um aplicativo brasileiro para iPhone (e site) em que o dono do carro guarda o histórico de manutenção do veículo. O histórico fica vinculado ao chassi, passa para o próximo dono na venda, e os serviços feitos por oficinas cadastradas recebem o Selo da oficina, com código conferível em {$verify}. O dono exporta um PDF do histórico para vender o carro. Grátis no lançamento. Android em breve.

        ## O que o RevisaLog é
        - App de histórico de manutenção para donos de carro (consumidor), no iPhone: {$store}
        - Histórico preso ao veículo (chassi, placa ou RENAVAM), não à conta do dono
        - Selo da oficina: serviço confirmado pela oficina, com código RVL-XXXX-XX
        - Declaração do proprietário: registro feito pelo dono, sem código
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
        TXT;
    }
}
