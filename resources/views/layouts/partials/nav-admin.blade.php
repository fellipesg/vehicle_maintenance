{{--
    Menu lateral do admin. Os destinos vêm da fonte única (App\Enums\Portal::Admin->navigationItems():
    Visão geral sem grupo; Cadastros, Frota, Conteúdo e Catálogo agrupando o resto, cada item com
    ícone de 20px e aria-current="page" na área aberta) e são desenhados por <x-ui.nav> vertical.

    Mapas não são itens do menu: são outra forma de ver um cadastro (a troca Lista | Mapa fica na
    própria página), e o Portal já faz cada mapa ativar o item da lista dele. "Usuários" abre a
    lista própria (/admin/usuarios, admin.users.index), com busca, perfis e paginação.

    Sidebar recolhida (data-sidebar="collapsed" no <html>, a partir de md; ver layouts.admin): os
    rótulos dos itens e dos grupos ficam só para leitor de tela e os ícones se centralizam. O nome de
    cada item aparece na dica de resources/js/ui/sidebar.js.
--}}
<x-ui.nav
    label="Administração"
    variant="vertical"
    :items="\App\Enums\Portal::Admin->navigationItems()"
    class="md:in-data-[sidebar=collapsed]:[&_a]:justify-center md:in-data-[sidebar=collapsed]:[&_a]:px-0 md:in-data-[sidebar=collapsed]:[&_a>span]:sr-only md:in-data-[sidebar=collapsed]:[&_li>p]:sr-only"
    data-sidebar-nav
/>
