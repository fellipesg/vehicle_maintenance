<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Services\Maintenance\MaintenanceOwnerDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Registros de oficinas": serviços que oficinas registraram nos veículos da conta antes dela chegar
 * — ou, sem vínculo provado, num carro que já era dela. Nada é vinculado sozinho; o proprietário
 * escolhe vincular e aceitar notas e fotos (só com a propriedade verificada pelo CRLV-e). Ocultar
 * saiu: no lugar dele ele **contesta**, que marca o registro sem tirá-lo de circulação, e não pede
 * o documento.
 */
class WorkshopRecordController extends Controller
{
    public function __construct(private readonly MaintenanceOwnerDecisionService $decisions) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $showAll = $request->query('status') === 'all';

        $records = $this->decisions->recordsFor($user, onlyPending: ! $showAll)
            ->with(['vehicle', 'items', 'workshop', 'verifiedWorkshop', 'invoices', 'photos'])
            ->get();

        return view('user.workshop-records.index', [
            'records' => $records,
            'showAll' => $showAll,
            'pendingCount' => $this->decisions->pendingCountFor($user),
            'decisions' => $this->decisions,
        ]);
    }

    public function decide(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'link' => ['required', 'boolean'],
            'attach_files' => ['nullable', 'boolean'],
        ]);

        try {
            $updated = $this->decisions->decide(
                $maintenance->loadMissing('vehicle'),
                $request->user(),
                (bool) $data['link'],
                (bool) ($data['attach_files'] ?? false),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors(['decision' => (string) collect($exception->errors())->flatten()->first()]);
        }

        $message = $updated->owner_status === Maintenance::OWNER_LINKED
            ? 'Registro vinculado ao seu histórico.'
            : 'Registro mantido só no chassi do veículo, sem os detalhes.';

        return redirect()->route('user.workshop-records.index', $request->only('status'))->with('success', $message);
    }

    public function dispute(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->decisions->dispute(
                $maintenance->loadMissing('vehicle'),
                $request->user(),
                filled($data['reason'] ?? null) ? $data['reason'] : null,
            );
        } catch (ValidationException $exception) {
            return back()->withErrors(['decision' => (string) collect($exception->errors())->flatten()->first()]);
        }

        return back()->with('success', 'Registro contestado. Avisamos a oficina, ele continua no histórico com a sua contestação à vista, e a equipe vai analisar.');
    }

    public function withdrawDispute(Request $request, Maintenance $maintenance): RedirectResponse
    {
        $this->decisions->withdrawDispute($maintenance->loadMissing('vehicle'), $request->user());

        return back()->with('success', 'Contestação retirada.');
    }
}
