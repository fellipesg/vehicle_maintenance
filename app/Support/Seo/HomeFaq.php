<?php

namespace App\Support\Seo;

/**
 * Perguntas visíveis da home. O JSON-LD FAQPage sai daqui, com o mesmo texto.
 */
final class HomeFaq
{
    /**
     * Frase de definição do produto. A home e o JSON-LD MobileApplication usam esta string.
     */
    public static function definition(): string
    {
        return 'O RevisaLog é um app para iPhone em que o dono do carro guarda o histórico de manutenção do veículo: o registro fica preso ao chassi, a oficina confirma cada serviço com um selo verificável e você exporta um PDF para vender o carro.';
    }

    public static function metaDescription(): string
    {
        return 'RevisaLog: app de iPhone que guarda o histórico de manutenção do carro no chassi, com selo da oficina e PDF para vender. Grátis no lançamento.';
    }

    /**
     * @return list<array{question: string, parts: list<array{text: string, href?: string, external?: bool}>}>
     */
    public static function items(): array
    {
        return [
            [
                'question' => 'O que é o RevisaLog?',
                'parts' => [
                    ['text' => 'O RevisaLog é um aplicativo brasileiro para iPhone, também acessível pelo site, que guarda o histórico de manutenção do carro vinculado ao chassi. Oficinas cadastradas confirmam os serviços com o Selo da oficina, conferível em '],
                    ['text' => 'revisalog.com.br/verificar', 'href' => route('verification.lookup')],
                    ['text' => ', e o dono exporta um PDF para a venda. Não é sistema de frota e os avisos chegam por e-mail e notificação no app.'],
                ],
            ],
            [
                'question' => 'O RevisaLog é para frotas ou empresas?',
                'parts' => [
                    ['text' => 'O RevisaLog é feito para o dono do carro. Oficinas têm cadastro no site e confirmam o serviço com o Selo da oficina. Lojas usam a área do lojista; a entrada dessa área é com a equipe, sem cadastro aberto. Não é um sistema de gestão de frota.'],
                ],
            ],
            [
                'question' => 'Como chegam os lembretes?',
                'parts' => [
                    ['text' => 'Os lembretes chegam por e-mail e por notificação dentro do app. O dono recebe um aviso quando a quilometragem estimada se aproxima da próxima revisão. A oficina pode avisar o cliente nesse mesmo momento e alguns dias depois de um serviço. O RevisaLog não envia esses alertas por WhatsApp.'],
                ],
            ],
            [
                'question' => 'É de graça mesmo?',
                'parts' => [
                    ['text' => 'Sim. No lançamento o RevisaLog não cobra nada e não pede cartão.'],
                ],
            ],
            [
                'question' => 'O histórico muda de dono junto com o carro?',
                'parts' => [
                    ['text' => 'Sim. O registro fica no veículo: depois da transferência, o histórico continua consultável pela placa, pelo chassi ou pelo RENAVAM.'],
                ],
            ],
            [
                'question' => 'Qual a diferença entre selo e declaração?',
                'parts' => [
                    ['text' => 'O Selo da oficina é o serviço confirmado por uma oficina da rede, com código de conferência. A declaração é o que o proprietário ou o lojista registrou por conta própria, sem essa confirmação.'],
                ],
            ],
            [
                'question' => 'Preciso de conta para consultar um veículo?',
                'parts' => [
                    ['text' => 'Para buscar pela placa, pelo chassi ou pelo RENAVAM, sim: uma conta gratuita. Para conferir o código de um selo, não: qualquer pessoa confere em '],
                    ['text' => 'Conferir selo da oficina', 'href' => route('verification.lookup')],
                    ['text' => '.'],
                ],
            ],
            [
                'question' => 'Oficinas e lojas também podem usar?',
                'parts' => [
                    ['text' => 'Sim. Oficinas aplicam o Selo da oficina e aparecem em Oficinas da rede: '],
                    ['text' => 'cadastre sua oficina', 'href' => route('workshops.landing')],
                    ['text' => ' direto no site. Lojas registram a revisão antes da venda no próprio estoque; para entrar, '],
                    ['text' => 'fale com a equipe', 'href' => route('contact.show', ['assunto' => 'partnership'])],
                    ['text' => '.'],
                ],
            ],
            [
                'question' => 'Tem aplicativo?',
                'parts' => [
                    ['text' => 'Tem. O app para iPhone está na '],
                    ['text' => 'App Store', 'href' => (string) config('app.ios_app_store_url')],
                    ['text' => ', com a mesma conta e o mesmo histórico do site. A versão para Android está a caminho; enquanto isso, cadastro e consulta funcionam no navegador do celular.'],
                ],
            ],
        ];
    }
}
