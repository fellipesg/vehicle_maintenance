<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enquanto a loja não é aprovada, a conta de garagem não escreve nada: na web só abre "Minha loja",
 * e na API só lê.
 *
 * O motivo não é cosmético: uma garagem escreve no histórico permanente de um chassi e dispara
 * e-mail para terceiros — VehicleConsignmentStartedNotification para o endereço que ela digitar,
 * VehicleClaimedByAnotherAccountNotification para quem perdeu o veículo, e os convites de
 * /maintenances/{id}/invites. Nada disso pode sair de uma loja que ninguém conferiu.
 *
 * A web trabalha com lista de liberação e a API bloqueia por método: rota nova na área do lojista,
 * e endpoint novo de escrita, nascem fechados para a loja pendente — o lado seguro de errar.
 */
class EnsureGarageApproved
{
    /**
     * Rotas da web que a loja em análise continua abrindo.
     *
     * @var list<string>
     */
    private const ALLOWED_WEB_ROUTES = [
        'garage.profile.*',
    ];

    /**
     * Métodos que não mudam nada e seguem liberados na API, para o app poder mostrar a conta e
     * explicar a espera em vez de parecer quebrado.
     *
     * @var list<string>
     */
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * $mode é 'api' no grupo da API (routes/api.php). Explícito de propósito: um cliente que não
     * manda Accept: application/json receberia o redirect da web em vez do 403.
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isGarage() || ($user->garage?->isApproved() ?? false)) {
            return $next($request);
        }

        if ($mode === 'api' || $request->expectsJson()) {
            if (in_array($request->method(), self::READ_METHODS, true)) {
                return $next($request);
            }

            return ApiResponse::error('Your store is still under review. You cannot create or change records yet.', 403);
        }

        if ($request->routeIs(...self::ALLOWED_WEB_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('garage.profile.show')
            ->with('info', 'Sua loja ainda está em análise. Enquanto isso, você pode completar o cadastro aqui.');
    }
}
