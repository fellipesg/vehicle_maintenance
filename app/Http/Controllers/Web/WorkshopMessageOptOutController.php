<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workshop;
use App\Support\Workshop\WorkshopMessageConsent;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Descadastro das mensagens de oficina, pelo link assinado do e-mail de follow-up. Página mínima e
 * noindex: diz só o nome da oficina. O POST é idempotente e fica fora do CSRF, para funcionar como
 * um clique (RFC 8058) no provedor de e-mail; a URL assinada é a proteção. Sem escolha, o clique
 * cala só a oficina que mandou a mensagem; a página também oferece calar todas.
 */
class WorkshopMessageOptOutController extends Controller
{
    public function __construct(private readonly WorkshopMessageConsent $consent) {}

    public function show(Request $request): View
    {
        ['workshop' => $workshop] = $this->resolve($request);

        return view('workshops.messages-unsubscribe', [
            'done' => false,
            'scope' => WorkshopMessageConsent::SCOPE_WORKSHOP,
            'workshopName' => $workshop?->name,
        ]);
    }

    public function unsubscribe(Request $request): View
    {
        ['user' => $user, 'workshop' => $workshop] = $this->resolve($request);

        $scope = $request->input('scope') === WorkshopMessageConsent::SCOPE_ALL
            ? WorkshopMessageConsent::SCOPE_ALL
            : WorkshopMessageConsent::SCOPE_WORKSHOP;

        if ($scope === WorkshopMessageConsent::SCOPE_ALL) {
            $this->consent->optOut($user->id, null);
        } elseif ($workshop !== null) {
            $this->consent->optOut($user->id, $workshop->id);
        }

        return view('workshops.messages-unsubscribe', [
            'done' => true,
            'scope' => $scope,
            'workshopName' => $workshop?->name,
        ]);
    }

    /**
     * A oficina pode já não existir (conta apagada): não há o que calar, e a página responde o
     * mesmo "pronto" em vez de quebrar o clique.
     *
     * @return array{user: User, workshop: ?Workshop}
     */
    private function resolve(Request $request): array
    {
        $raw = $request->query('payload');
        $payload = $this->consent->decodePayload(is_string($raw) ? $raw : null);

        abort_if($payload === null, 404);

        $user = User::query()->find($payload['user_id']);

        abort_if($user === null, 404);

        return [
            'user' => $user,
            'workshop' => $payload['workshop_id'] === null
                ? null
                : Workshop::query()->find($payload['workshop_id']),
        ];
    }
}
