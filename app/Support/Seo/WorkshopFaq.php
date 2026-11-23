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
        ];
    }
}
