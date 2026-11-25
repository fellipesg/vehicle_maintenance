<?php

namespace App\Http\Middleware;

use App\Support\Account\SignupFlow;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarda as etapas do cadastro em etapas (/cadastro): quem cai numa etapa sem ter concluído a
 * anterior volta para o começo, com um aviso.
 *
 * É middleware, e não `authorize()` do FormRequest, por dois motivos. Um 403 numa etapa de cadastro
 * é resposta errada para quem só demorou e perdeu o fluxo de 30 minutos — o certo é recomeçar. E a
 * validação do FormRequest roda **antes** do controller: sem a guarda aqui, a etapa da senha
 * validaria pelo ramo errado (pedindo senha nova em vez da senha da identidade) quando o fluxo
 * tivesse expirado.
 *
 * `step` é a etapa exigida: 'started' (perfil e e-mail conferidos) ou 'password' (e a senha já
 * resolvida).
 */
class EnsureSignupStep
{
    public const EXPIRED_MESSAGE = 'Seu cadastro expirou antes de terminar. Comece de novo, é rápido.';

    public function handle(Request $request, Closure $next, string $step = 'started'): Response
    {
        $flow = SignupFlow::fromRequest($request);

        $satisfied = match ($step) {
            'password' => $flow->hasStarted() && $flow->passwordIsSettled(),
            default => $flow->hasStarted(),
        };

        if (! $satisfied) {
            $flow->clear();

            return redirect()
                ->route('signup.start')
                ->with('warning', self::EXPIRED_MESSAGE);
        }

        return $next($request);
    }
}
