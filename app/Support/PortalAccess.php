<?php

namespace App\Support;

use App\Enums\Portal;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Qual área do site cada conta pode abrir e para onde mandar quem cai na área errada.
 *
 * Áreas restritas (primeiro segmento da URL): /usuario (proprietário), /garagem (lojista),
 * /oficina (oficina) e /admin (administrador). /buscar-veiculo, /notificacoes e /conta servem a
 * qualquer conta logada. Usada pelo login (url.intended só vale para área da conta), pelo
 * redirecionamento de visitante para o login do portal certo e pelo 403 de perfil errado, que
 * vira redirecionamento ao Início da conta.
 */
class PortalAccess
{
    /**
     * Área restrita => login do portal que a atende.
     *
     * @var array<string, string>
     */
    private const LOGIN_ROUTES = [
        'usuario' => 'login.usuario',
        'garagem' => 'login.lojista',
        'oficina' => 'login.oficina',
        'admin' => 'login.admin',
    ];

    /**
     * Área restrita => onde ela fica, com o nome que o usuário vê nas telas de entrada.
     *
     * @var array<string, string>
     */
    private const AREA_LOCATIONS = [
        'usuario' => 'na Área do Proprietário',
        'garagem' => 'na Área do Lojista',
        'oficina' => 'na Área da Oficina',
        'admin' => 'no Painel Administrador',
    ];

    /**
     * Primeiros segmentos de caminho que qualquer conta logada pode abrir.
     *
     * @var list<string>
     */
    private const SHARED_SEGMENTS = ['buscar-veiculo', 'notificacoes', 'conta'];

    /**
     * Área restrita do caminho ('usuario', 'garagem', 'oficina' ou 'admin'), ou null.
     */
    public static function areaForPath(string $path): ?string
    {
        $firstSegment = self::firstSegment($path);

        return array_key_exists($firstSegment, self::LOGIN_ROUTES) ? $firstSegment : null;
    }

    public static function canEnterArea(User $user, string $area): bool
    {
        return match ($area) {
            'usuario' => $user->isUser(),
            'garagem' => $user->isGarage(),
            'oficina' => $user->isWorkshop(),
            'admin' => $user->isAdmin(),
            default => false,
        };
    }

    /**
     * A conta pode abrir este caminho: área dela ou caminho comum a toda conta logada.
     */
    public static function canAccessPath(User $user, string $path): bool
    {
        $area = self::areaForPath($path);

        if ($area !== null) {
            return self::canEnterArea($user, $area);
        }

        return in_array(self::firstSegment($path), self::SHARED_SEGMENTS, true);
    }

    /**
     * url.intended só é seguida quando aponta para este site e para uma área da conta. Fora disso
     * (outro host, área de outro perfil, página pública), o login leva ao Início.
     */
    public static function allowsIntendedUrl(User $user, string $url, string $currentHost): bool
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        $host = $parts['host'] ?? null;

        if ($host !== null && strcasecmp($host, $currentHost) !== 0) {
            return false;
        }

        return self::canAccessPath($user, (string) ($parts['path'] ?? '/'));
    }

    /**
     * Nome da rota do Início da conta. O administrador vai ao painel admin (Portal::homeFor()).
     */
    public static function homeRouteName(User $user): string
    {
        return Portal::homeFor($user)->dashboardRoute();
    }

    /**
     * Perfil da conta como aparece para o usuário ("Conta de Lojista").
     */
    public static function accountLabel(User $user): string
    {
        return Portal::homeFor($user)->label();
    }

    /**
     * Rota da tela de entrada do portal da conta (depois de redefinir a senha, por exemplo).
     */
    public static function loginRouteName(User $user): string
    {
        return Portal::homeFor($user)->loginRoute();
    }

    public static function homeUrl(User $user): string
    {
        return route(self::homeRouteName($user));
    }

    /**
     * O Início da conta fica numa área que ela pode abrir (evita redirecionar em círculo).
     */
    public static function homeIsReachable(User $user): bool
    {
        return self::canAccessPath($user, (string) parse_url(self::homeUrl($user), PHP_URL_PATH));
    }

    /**
     * Login do portal que atende o caminho pedido, para o visitante que abriu um link protegido.
     * Caminhos comuns (/conta, /notificacoes...) levam à escolha do tipo de acesso.
     */
    public static function loginUrlFor(Request $request): string
    {
        $area = self::areaForPath($request->path());

        return route($area !== null ? self::LOGIN_ROUTES[$area] : 'login');
    }

    /**
     * Aviso para quem abriu a área de outro perfil e foi levado ao próprio Início.
     */
    public static function wrongAreaMessage(string $area): string
    {
        $areaLocation = self::AREA_LOCATIONS[$area] ?? 'em outra área';

        return "Essa página fica {$areaLocation}, que não faz parte desta conta. Trouxemos você para o seu Início.";
    }

    private static function firstSegment(string $path): string
    {
        $pathOnly = parse_url($path, PHP_URL_PATH);
        $pathOnly = is_string($pathOnly) ? $pathOnly : $path;

        return strtolower(explode('/', trim($pathOnly, '/'))[0]);
    }
}
