<?php

namespace App\Support\Seo;

final class VerificationFaq
{
    public static function intro(): string
    {
        return 'O código é a conferência pública de um documento do RevisaLog. RVL-XXXX-XX é o Selo da oficina: a oficina cadastrada registrou aquele serviço. LDO-XXXX-XX é o laudo de vistoria cautelar. Serviço declarado pelo proprietário ou pelo lojista não tem código.';
    }

    /**
     * @return list<array{question: string, parts: list<array{text: string, href?: string}>}>
     */
    public static function items(): array
    {
        return [
            [
                'question' => 'O que significa o código RVL?',
                'parts' => [
                    ['text' => 'RVL-XXXX-XX é o Selo da oficina. A oficina cadastrada registrou o serviço, e qualquer pessoa confere o código nesta página, sem conta.'],
                ],
            ],
            [
                'question' => 'O que significa o código LDO?',
                'parts' => [
                    ['text' => 'LDO-XXXX-XX é o laudo de vistoria cautelar. O código entra no mesmo campo desta página e abre o laudo público.'],
                ],
            ],
            [
                'question' => 'Por que alguns serviços não têm código?',
                'parts' => [
                    ['text' => 'Porque foram declarados pelo proprietário ou pelo lojista. Só o serviço registrado por uma oficina cadastrada, e o laudo de vistoria, recebem código.'],
                ],
            ],
        ];
    }
}
