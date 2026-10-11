<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enquanto a loja não é aprovada, a conta de garagem só abre "Minha loja".
 *
 * O motivo não é cosmético: uma garagem escreve no histórico permanente de um chassi e dispara
 * e-mail para terceiros (VehicleConsignmentStartedNotification para o endereço que ela digitar,
 * VehicleClaimedByAnotherAccountNotification para quem perdeu o veículo). Nada disso pode sair de
 * uma loja que ninguém conferiu.
 *
 * A lista é de liberação, não de bloqueio: rota nova na área do lojista nasce fechada para a loja
 * pendente, que é o lado seguro de errar.
 */
class EnsureGarageApproved
{
    /**
     * Rotas que a loja em análise continua abrindo.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'garage.profile.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isGarage() || ($user->garage?->isApproved() ?? false)) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->route('garage.profile.show')
            ->with('info', 'Sua loja ainda está em análise. Enquanto isso, você pode completar o cadastro aqui.');
    }
}
