<?php

/*
| Datas de lastmod das páginas fixas do sitemap. Não há updated_at no banco
| para essas rotas: a data é a da última alteração relevante do HTML público
| (canonical, texto ou estrutura). Posts e categorias usam updated_at.
|
| Não incluir aqui rota com noindex.
*/

return [

    'static_lastmod' => [
        'home' => '2026-11-23',
        'blog.index' => '2026-11-23',
        'workshops.landing' => '2026-11-23',
        'workshops.signup' => '2026-11-23',
        'verification.lookup' => '2026-11-23',
        'guides.comparison' => '2026-11-23',
        'contact.show' => '2026-11-23',
        'legal.terms' => '2026-11-18',
        'legal.privacy' => '2026-11-18',
    ],

    'instagram_url' => 'https://www.instagram.com/revisalog/',

    // Dia em que as páginas oficiais dos concorrentes foram lidas para o comparativo.
    'competitors_verified_on' => '2026-11-23',

];
