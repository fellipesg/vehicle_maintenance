<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enquanto a oficina não é aprovada, a conta de oficina não escreve nada: na web só abre "Minha
 * oficina", e na API só lê.
 *
 * É o mesmo portão do lojista (App\Http\Middleware\EnsureGarageApproved) e pelo mesmo motivo, só
 * que mais forte: a oficina escreve no histórico permanente de um chassi **com o Selo da oficina**,
 * que é o que o comprador confere em /v/{código}; move o hodômetro do veículo; e dispara convite
 * por e-mail e WhatsApp para terceiros (/manutencoes/{id}/convite). Nada disso pode sair de uma
 * oficina que ninguém conferiu — e um selo emitido não volta atrás.
 *
 * A web trabalha com lista de liberação e a API bloqueia por método: rota nova na área da oficina,
 * e endpoint novo de escrita, nascem fechados para a oficina pendente — o lado seguro de errar.
 */
class EnsureWorkshopApproved
{
    /**
     * Rotas da web que a oficina em análise continua abrindo.
     *
     * @var list<string>
     */
    private const ALLOWED_WEB_ROUTES = [
        'workshop.profile.*',
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

        // isWorkshop() antes de tocar a relação: ler $user->workshop aqui custaria uma consulta em
        // TODA requisição da API, inclusive de proprietário e de lojista (há teste de contagem de
        // queries em /my-vehicles que pega isso).
        if ($user === null || ! $user->isWorkshop()) {
            return $next($request);
        }

        $workshop = $user->workshop;

        // Conta sem oficina cadastrada passa: não há o que conferir ainda, e sem a linha de
        // workshops ela não registra OS nem emite selo (o store devolve "Cadastre sua oficina" e o
        // MaintenanceVerificationStamper exige $actor->workshop). As telas de onboarding seguem.
        if ($workshop === null || $workshop->isApproved()) {
            return $next($request);
        }

        if ($mode === 'api' || $request->expectsJson()) {
            if (in_array($request->method(), self::READ_METHODS, true)) {
                return $next($request);
            }

            return ApiResponse::error('Your workshop is still under review. You cannot create or change records yet.', 403);
        }

        if ($request->routeIs(...self::ALLOWED_WEB_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('workshop.profile.show')
            ->with('info', 'Sua oficina ainda está em análise. Enquanto isso, você pode completar o cadastro aqui.');
    }
}
