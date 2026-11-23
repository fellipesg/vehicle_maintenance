<?php

/**
 * Posts de resposta direta (AEO). A data é o dia em que o texto entrou no
 * repositório, no horário de Brasília. Não faz parte da série de terça e quinta:
 * esses quatro já nascem publicados, para a página, o sitemap e o llms.txt.
 *
 * @return list<array{slug: string, publish_at: string, category: string, art: string, title: string, excerpt: string, meta_description: string}>
 */
return [
    [
        'slug' => 'perdi-o-manual-de-revisoes-como-comprovar',
        'publish_at' => '2026-11-23 00:00',
        'category' => 'documentacao',
        'art' => 'documento',
        'title' => 'Perdi o manual de revisões do carro: como comprovar as manutenções',
        'excerpt' => 'O manual perdido não apaga notas, ordens de serviço nem o que a oficina ainda tiver registrado. Veja como reunir essa prova.',
        'meta_description' => 'Perdeu o manual de revisões? Veja como reunir notas, ordens de serviço e registros da concessionária para comprovar as manutenções do carro.',
    ],
    [
        'slug' => 'planilha-ou-aplicativo-manutencao-do-carro',
        'publish_at' => '2026-11-23 00:00',
        'category' => 'manutencao',
        'art' => 'revisao',
        'title' => 'Planilha ou aplicativo para a manutenção do carro?',
        'excerpt' => 'A planilha é flexível e pode não custar nada. Ela não confirma o serviço nem viaja com o carro. O aplicativo certo faz as duas coisas.',
        'meta_description' => 'Planilha ou aplicativo para a manutenção do carro? Veja o que cada um guarda, o que se perde na venda e quando o RevisaLog ajuda.',
    ],
    [
        'slug' => 'alternativas-ao-manual-de-revisoes-de-papel',
        'publish_at' => '2026-11-23 00:00',
        'category' => 'documentacao',
        'art' => 'documento',
        'title' => 'Alternativas ao manual de revisões de papel',
        'excerpt' => 'Notas, ordens de serviço e um registro preso ao chassi cobrem o que o manual de papel faz, sem depender de um único caderno.',
        'meta_description' => 'Quais alternativas ao manual de revisões de papel: notas, ordens de serviço e um histórico digital que fica no carro.',
    ],
    [
        'slug' => 'como-verificar-se-revisoes-do-carro-usado-sao-verdadeiras',
        'publish_at' => '2026-11-23 00:00',
        'category' => 'compra-e-venda',
        'art' => 'selo',
        'title' => 'Como verificar se as revisões de um carro usado são verdadeiras',
        'excerpt' => 'Antes de comprar, confira data, quilometragem e quem fez cada serviço. Código de selo dá para checar sem conta. Declaração não tem esse código.',
        'meta_description' => 'Como checar se as revisões de um carro usado são verdadeiras: notas, quilometragem e o código do selo da oficina.',
    ],
];
