<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Services\Maintenance\MaintenanceInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Avisar o cliente" na OS de um carro sem proprietário. Só a oficina que fez a OS convida, e só
 * enquanto o veículo não tem dono atual nem decisão (MaintenanceInviteService::canInvite).
 */
class MaintenanceInviteController extends Controller
{
    public function __construct(private readonly MaintenanceInviteService $invites) {}

    /**
     * Guarda só a data e manda a oficina ao wa.me com a mensagem pronta; quem envia é a oficina,
     * do próprio WhatsApp. O telefone não é guardado.
     */
    public function whatsapp(Request $request, Maintenance $maintenance): RedirectResponse
    {
        if ($redirect = $this->denyUnlessInvitable($request, $maintenance)) {
            return $redirect;
        }

        $data = $request->validate(
            ['phone' => ['required', 'string', 'max:30']],
            ['phone.required' => 'Digite o telefone do cliente com DDD.'],
        );

        $result = $this->invites->whatsappUrl($maintenance, $request->user()->workshop, $data['phone']);

        if ($result === null) {
            return back()->withInput()->withErrors(['phone' => 'Telefone inválido. Digite o DDD e o número, por exemplo (11) 91234-5678.'], 'inviteWhatsapp');
        }

        return redirect()->away($result['url']);
    }

    public function email(Request $request, Maintenance $maintenance): RedirectResponse
    {
        if ($redirect = $this->denyUnlessInvitable($request, $maintenance)) {
            return $redirect;
        }

        $validator = validator($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => 'Digite o e-mail do cliente.',
            'email.email' => 'Digite um e-mail válido.',
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator, 'inviteEmail');
        }

        $result = $this->invites->sendEmail($maintenance, $request->user()->workshop, (string) $request->input('email'));

        return match ($result['status']) {
            MaintenanceInviteService::SENT => back()->with('success', 'Convite enviado por e-mail.'),
            MaintenanceInviteService::ALREADY_INVITED => back()->with('error', 'Esta OS já tem um convite por e-mail enviado. Cada OS aceita um só; use o WhatsApp para falar de novo com o cliente.'),
            MaintenanceInviteService::DAILY_LIMIT => back()->with('error', 'Sua oficina atingiu o limite de convites por e-mail nas últimas 24 horas. Use o WhatsApp ou tente de novo amanhã.'),
            default => back()->withInput()->withErrors(['email' => 'Não foi possível enviar para este e-mail.'], 'inviteEmail'),
        };
    }

    private function denyUnlessInvitable(Request $request, Maintenance $maintenance): ?RedirectResponse
    {
        Gate::authorize('view', $maintenance);

        $workshop = $request->user()->workshop;

        if ($workshop === null || (int) $maintenance->workshop_id !== (int) $workshop->id) {
            abort(403);
        }

        if (! $this->invites->canInvite($maintenance, $workshop)) {
            return redirect()->route('workshop.maintenances.show', $maintenance)
                ->with('error', 'Este registro não precisa mais de convite: o veículo já tem proprietário ou ele já decidiu.');
        }

        return null;
    }
}
