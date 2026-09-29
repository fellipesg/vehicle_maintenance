<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Erros HTTP do site que viram redirecionamento com aviso, em vez de página de erro.
 *
 * - 419 (sessão expirada) nos formulários de entrada, cadastro, recuperação de senha e contato:
 *   volta ao formulário com o que foi digitado (menos senhas) e um aviso. No "Sair", leva ao
 *   Início (se a sessão ainda existe) ou ao site (se já tinha expirado).
 * - 403 de quem abre a área de outro perfil (/usuario, /garagem, /oficina, /admin): leva ao Início
 *   da conta com um aviso. O 403 dentro da própria área (registro de outra pessoa) continua na
 *   página de erro.
 *
 * Registrado em bootstrap/app.php. Só vale para páginas web: a API responde JSON.
 */
class FriendlyHttpErrors
{
    public const SESSION_EXPIRED_MESSAGE = 'Sua sessão expirou por segurança. Confira os dados e envie de novo.';

    /**
     * Campos que nunca voltam preenchidos depois do redirecionamento.
     *
     * @var list<string>
     */
    private const NEVER_FLASHED = [
        '_token',
        '_method',
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'cf-turnstile-response',
    ];

    public static function sessionExpired(Request $request): ?RedirectResponse
    {
        if ($request->routeIs('logout')) {
            return self::expiredLogout($request);
        }

        $formUrl = self::formUrlFor($request);

        if ($formUrl === null) {
            return null;
        }

        return redirect()->to($formUrl)
            ->withInput($request->except(self::NEVER_FLASHED))
            ->with('warning', self::SESSION_EXPIRED_MESSAGE);
    }

    public static function wrongArea(Request $request): ?RedirectResponse
    {
        $user = $request->user();
        $area = PortalAccess::areaForPath($request->path());

        if ($user === null || $area === null || PortalAccess::canEnterArea($user, $area) || ! PortalAccess::homeIsReachable($user)) {
            return null;
        }

        return redirect()->to(PortalAccess::homeUrl($user))
            ->with('info', PortalAccess::wrongAreaMessage($area));
    }

    private static function expiredLogout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('home')
                ->with('info', 'Sua sessão já tinha expirado, então você já está fora da conta.');
        }

        return redirect()->to(PortalAccess::homeUrl($user))
            ->with('warning', 'A página estava aberta havia muito tempo e não conseguimos confirmar a saída. Clique em Sair de novo.');
    }

    /**
     * Página (GET) do formulário que foi enviado, ou null quando não é um formulário de entrada.
     */
    private static function formUrlFor(Request $request): ?string
    {
        if ($request->routeIs('login.submit')) {
            $portal = (string) $request->route('portal');

            return in_array($portal, ['admin', 'lojista', 'usuario', 'oficina'], true)
                ? route("login.{$portal}")
                : route('login');
        }

        if ($request->routeIs('password.email')) {
            return route('password.request');
        }

        if ($request->routeIs('password.update')) {
            $token = (string) $request->input('token', '');

            return $token === '' ? route('password.request') : route('password.reset', ['token' => $token]);
        }

        if ($request->routeIs('contact.store')) {
            return route('contact.show');
        }

        if ($request->isMethod('post') && $request->is('register')) {
            return route('register');
        }

        return null;
    }
}
