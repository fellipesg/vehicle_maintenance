<?php

namespace App\Enums;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Área da conta (portal): fonte única do rótulo do perfil, da rota de Início, dos destinos do menu
 * e da ação principal de cada área. O shell (topbar, menu mobile, menu de conta), o <title> e as
 * notificações leem daqui; nenhuma view decide o portal por conta própria.
 *
 * Os valores são os de users.user_type ('user', 'garage', 'workshop'), mais 'admin', que é uma área
 * extra das contas com is_admin (a conta continua tendo o user_type dela).
 */
enum Portal: string
{
    case Owner = 'user';
    case Dealer = 'garage';
    case Workshop = 'workshop';
    case Admin = 'admin';

    /**
     * Portal da conta pelo user_type. Valor desconhecido ou vazio cai no Proprietário.
     */
    public static function forUserType(?string $userType): self
    {
        return match ($userType) {
            self::Dealer->value => self::Dealer,
            self::Workshop->value => self::Workshop,
            default => self::Owner,
        };
    }

    /**
     * Área em que a pessoa está agora: o Admin nas rotas admin.* (só para quem é admin) e, fora
     * delas, o portal do user_type. Visitante não tem área.
     */
    public static function current(?User $user, ?Request $request = null): ?self
    {
        if ($user === null) {
            return null;
        }

        $request ??= request();

        if ($user->isAdmin() && $request->routeIs('admin.*')) {
            return self::Admin;
        }

        return $user->portal();
    }

    /**
     * Área de entrada da conta, fora de qualquer rota: o Admin para quem é admin, o portal do
     * user_type para os demais. É para onde o login, o 403 de área errada e o "Voltar ao Início" da
     * página de erro levam (App\Support\PortalAccess).
     */
    public static function homeFor(User $user): self
    {
        return $user->isAdmin() ? self::Admin : $user->portal();
    }

    /**
     * Áreas que a conta pode abrir, na ordem do menu "Trocar de área": o portal do user_type e,
     * para admin, o Painel admin.
     *
     * @return list<self>
     */
    public static function accessibleBy(User $user): array
    {
        return $user->isAdmin() ? [$user->portal(), self::Admin] : [$user->portal()];
    }

    /**
     * Nome do perfil (chip do menu de conta e do menu mobile).
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::Dealer => 'Lojista',
            self::Workshop => 'Oficina',
            self::Admin => 'Administrador',
        };
    }

    /**
     * Área no <title> ("Início · Proprietário · RevisaLog"). O admin usa a forma curta "Admin".
     */
    public function titleLabel(): string
    {
        return $this === self::Admin ? 'Admin' : $this->label();
    }

    /**
     * Rótulo do item que leva a esta área no menu "Trocar de área".
     */
    public function switchLabel(): string
    {
        return $this === self::Admin
            ? 'Painel admin'
            : 'Minha área de '.mb_strtolower($this->label());
    }

    /**
     * Rota do Início da área (destino do logo e de "Trocar de área").
     */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Owner => 'user.dashboard',
            self::Dealer => 'garage.dashboard',
            self::Workshop => 'workshop.dashboard',
            self::Admin => 'admin.dashboard',
        };
    }

    /**
     * Tela de entrada do portal (/login/usuario, /login/lojista, /login/oficina, /login/admin).
     */
    public function loginRoute(): string
    {
        return match ($this) {
            self::Owner => 'login.usuario',
            self::Dealer => 'login.lojista',
            self::Workshop => 'login.oficina',
            self::Admin => 'login.admin',
        };
    }

    /**
     * Ficha do veículo nesta área, ou null quando a área não tem ficha (a oficina abre o veículo
     * pela OS).
     */
    public function vehicleRoute(): ?string
    {
        return match ($this) {
            self::Owner => 'user.vehicles.show',
            self::Dealer => 'garage.vehicles.show',
            self::Workshop => null,
            self::Admin => 'admin.vehicles.show',
        };
    }

    /**
     * Destinos do menu principal, na ordem Início, objeto principal, registros e perfil. O Admin
     * agrupa os itens (sidebar); os portais têm no máximo 5 itens soltos.
     *
     * @return list<array{label: string, route: string, icon: string, activePatterns: list<string>, fragment?: string}|array{label: string, items: list<array{label: string, route: string, icon: string, activePatterns: list<string>, fragment?: string}>}>
     */
    public function navigation(): array
    {
        return match ($this) {
            self::Owner => [
                self::item('Início', 'user.dashboard', 'home', ['user.dashboard']),
                self::item('Meus veículos', 'user.vehicles.index', 'truck', ['user.vehicles.*']),
                self::item('Manutenções', 'user.maintenances.index', 'wrench-screwdriver', ['user.maintenances.*']),
                self::item('Oficinas', 'user.workshops.index', 'building-storefront', ['user.workshops.*']),
            ],
            self::Dealer => [
                self::item('Início', 'garage.dashboard', 'home', ['garage.dashboard']),
                self::item('Estoque', 'garage.vehicles.index', 'truck', ['garage.vehicles.*']),
                self::item('Manutenções', 'garage.maintenances.index', 'wrench-screwdriver', ['garage.maintenances.*']),
            ],
            self::Workshop => [
                self::item('Início', 'workshop.dashboard', 'home', ['workshop.dashboard']),
                self::item('Ordens de serviço', 'workshop.maintenances.index', 'clipboard-document', ['workshop.maintenances.*']),
                self::item('Validações', 'workshop.reviews.index', 'check-circle', ['workshop.reviews.*']),
                self::item('Modelos de garantia', 'workshop.warranty-templates.index', 'shield-check', ['workshop.warranty-templates.*']),
                self::item('Minha oficina', 'workshop.profile.show', 'building-storefront', ['workshop.profile.*']),
            ],
            self::Admin => [
                self::item('Visão geral', 'admin.dashboard', 'chart-bar', ['admin.dashboard']),
                // Mapa é outra forma de ver um cadastro, não um destino do menu: a troca Lista | Mapa
                // fica na própria página, e cada mapa ativa o item da lista dele.
                ['label' => 'Cadastros', 'items' => [
                    self::item('Usuários', 'admin.users.index', 'users', ['admin.users.*', 'admin.maps.users']),
                    self::item('Oficinas', 'admin.workshops.index', 'building-storefront', ['admin.workshops.*', 'admin.maps.workshops']),
                    self::item('Prospecção', 'admin.outreach.index', 'envelope', ['admin.outreach.*']),
                ]],
                ['label' => 'Frota', 'items' => [
                    self::item('Veículos', 'admin.vehicles.index', 'truck', ['admin.vehicles.*']),
                    self::item('Manutenções', 'admin.maintenances.index', 'wrench-screwdriver', ['admin.maintenances.*']),
                ]],
                ['label' => 'Conteúdo', 'items' => [
                    self::item('Artigos do blog', 'admin.blog.index', 'newspaper', ['admin.blog.index', 'admin.blog.create', 'admin.blog.edit']),
                    self::item('Categorias do blog', 'admin.blog.categories.index', 'tag', ['admin.blog.categories.*']),
                ]],
                ['label' => 'Catálogo', 'items' => [
                    self::item('Marcas e modelos', 'admin.brands.index', 'squares-2x2', ['admin.brands.*']),
                ]],
            ],
        };
    }

    /**
     * Ação principal da área (botão primário da topbar e do topo do menu mobile), ou null.
     *
     * @return array{label: string, route: string, icon: string}|null
     */
    public function primaryAction(): ?array
    {
        return match ($this) {
            self::Owner => ['label' => 'Adicionar veículo', 'route' => 'user.vehicles.create', 'icon' => 'plus'],
            self::Dealer => ['label' => 'Adicionar ao estoque', 'route' => 'garage.vehicles.create', 'icon' => 'plus'],
            self::Workshop => ['label' => 'Nova OS', 'route' => 'workshop.maintenances.create', 'icon' => 'plus'],
            self::Admin => null,
        };
    }

    /**
     * navigation() pronta para <x-ui.nav :items>: href resolvido, active pelo nome da rota atual e
     * grupos preservados. Rotas que não existem ficam de fora (o menu não quebra se uma for
     * renomeada), assim como grupos que ficarem vazios.
     *
     * @return list<array{label: string, href: string, active: bool, icon: string}|array{label: string, items: list<array{label: string, href: string, active: bool, icon: string}>}>
     */
    public function navigationItems(?Request $request = null): array
    {
        $request ??= request();
        $entries = [];

        foreach ($this->navigation() as $entry) {
            if (isset($entry['items'])) {
                $groupItems = array_values(array_filter(array_map(
                    fn (array $item): ?array => self::resolveItem($item, $request),
                    $entry['items'],
                )));

                if ($groupItems !== []) {
                    $entries[] = ['label' => $entry['label'], 'items' => $groupItems];
                }

                continue;
            }

            $resolvedItem = self::resolveItem($entry, $request);

            if ($resolvedItem !== null) {
                $entries[] = $resolvedItem;
            }
        }

        return $entries;
    }

    /**
     * @param  list<string>  $activePatterns
     * @return array{label: string, route: string, icon: string, activePatterns: list<string>, fragment?: string}
     */
    private static function item(string $label, string $route, string $icon, array $activePatterns, ?string $fragment = null): array
    {
        $item = ['label' => $label, 'route' => $route, 'icon' => $icon, 'activePatterns' => $activePatterns];

        if ($fragment !== null) {
            $item['fragment'] = $fragment;
        }

        return $item;
    }

    /**
     * @param  array{label: string, route: string, icon: string, activePatterns: list<string>, fragment?: string}  $item
     * @return array{label: string, href: string, active: bool, icon: string}|null
     */
    private static function resolveItem(array $item, Request $request): ?array
    {
        if (! Route::has($item['route'])) {
            return null;
        }

        $href = route($item['route']);

        if (isset($item['fragment'])) {
            $href .= '#'.$item['fragment'];
        }

        return [
            'label' => $item['label'],
            'href' => $href,
            'active' => $request->routeIs(...$item['activePatterns']),
            'icon' => $item['icon'],
        ];
    }
}
