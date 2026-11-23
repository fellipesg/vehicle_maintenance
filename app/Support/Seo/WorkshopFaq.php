<?php

namespace App\Support\Seo;

final class WorkshopFaq
{
    public static function metaDescription(): string
    {
        return 'RevisaLog para oficinas: o nome da sua oficina fica no histórico do carro com o Selo da oficina. Cadastro direto no site, grátis no lançamento.';
    }

    /**
     * @return list<array{question: string, parts: list<array{text: string, href?: string, external?: bool}>}>
     */
    public static function items(): array
    {
        return [
            [
                'question' => 'Preciso esperar a equipe aprovar o cadastro?',
                'parts' => [
                    ['text' => 'Não. A conta funciona assim que o cadastro termina: você já entra na área da oficina.'],
                ],
            ],
            [
                'question' => 'Por que pedem o CNPJ?',
                'parts' => [
                    ['text' => 'Para identificar a oficina que assina o Selo da oficina. O CNPJ é conferido no cadastro e uma oficina não se cadastra duas vezes.'],
                ],
            ],
            [
                'question' => 'O que é o Selo da oficina?',
                'parts' => [
                    ['text' => 'É a confirmação de que o serviço foi feito pela oficina. Cada Selo tem um código, e qualquer pessoa confere em '],
                    ['text' => 'Conferir selo da oficina', 'href' => route('verification.lookup')],
                    ['text' => ', sem conta.'],
                ],
            ],
            [
                'question' => 'E se o cliente registrar um serviço feito aqui?',
                'parts' => [
                    ['text' => 'O serviço aparece na fila de validação da oficina. Você confirma os que foram feitos aí e o registro recebe o Selo da oficina.'],
                ],
            ],
            [
                'question' => 'Vai ser cobrado?',
                'parts' => [
                    ['text' => 'No lançamento, não. Se no futuro houver planos pagos, avisamos com antecedência, e nada é cobrado sem a oficina contratar.'],
                ],
            ],
            [
                'question' => 'Tenho uma loja de veículos, não uma oficina.',
                'parts' => [
                    ['text' => 'O cadastro próprio é só para oficinas. Para lojas, '],
                    ['text' => 'fale com a equipe', 'href' => route('contact.show', ['assunto' => 'partnership'])],
                    ['text' => '.'],
                ],
            ],
            [
                'question' => 'Existe aplicativo em que a oficina registra o serviço feito no carro do cliente?',
                'parts' => [
                    ['text' => 'Sim. No RevisaLog a oficina cadastrada registra a ordem de serviço, com itens, fotos, garantia e nota fiscal. O serviço entra no histórico do carro com o Selo da oficina e um código que qualquer pessoa confere em '],
                    ['text' => 'conferir selo ou laudo', 'href' => route('verification.lookup')],
                    ['text' => '.'],
                ],
            ],
            [
                'question' => 'Como a oficina lembra o cliente da próxima revisão?',
                'parts' => [
                    ['text' => 'Por e-mail e por notificação no app, com o texto que a oficina escolhe. Há duas situações em uso: quando a quilometragem estimada se aproxima da próxima revisão e alguns dias depois de um serviço. Não é mensagem de WhatsApp.'],
                ],
            ],
            [
                'question' => 'O RevisaLog substitui o sistema de gestão da oficina?',
                'parts' => [
                    ['text' => 'Não. Ele registra o serviço no histórico do carro e avisa o cliente por e-mail e notificação no app. Não é um ERP nem um CRM completo de oficina.'],
                ],
            ],
        ];
    }
}
