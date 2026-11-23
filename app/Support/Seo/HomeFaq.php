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
        return 'Cada manutenção fica vinculada ao chassi, não ao dono. A oficina confirma o serviço com o Selo da oficina, você declara o que fez, e o histórico passa para o próximo dono na venda.';
    }

    public static function metaDescription(): string
    {
        return 'RevisaLog: histórico permanente de manutenções vinculado ao veículo. Selo da oficina, busca por placa, chassi ou RENAVAM e PDF com notas. Grátis no lançamento.';
    }

    /**
     * @return list<array{question: string, parts: list<array{text: string, href?: string, external?: bool}>}>
     */
    public static function items(): array
    {
        return [
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
