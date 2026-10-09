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
 * "Registros de oficinas": serviços que oficinas registraram nos veículos da conta antes dela chegar.
 * Nada é vinculado sozinho; o proprietário escolhe vincular, aceitar notas e fotos (só com a
 * propriedade verificada pelo CRLV-e) e ocultar da consulta pública.
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
            'hide_from_public' => ['nullable', 'boolean'],
        ]);

        try {
            $updated = $this->decisions->decide(
                $maintenance->loadMissing('vehicle'),
                $request->user(),
                (bool) $data['link'],
                (bool) ($data['attach_files'] ?? false),
                (bool) ($data['hide_from_public'] ?? false),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors(['decision' => (string) collect($exception->errors())->flatten()->first()]);
        }

        $message = $updated->owner_status === Maintenance::OWNER_LINKED
            ? 'Registro vinculado ao seu histórico.'
            : 'Registro mantido só no chassi do veículo, sem os detalhes.';

        return redirect()->route('user.workshop-records.index', $request->only('status'))->with('success', $message);
    }
}
