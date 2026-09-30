<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Workshop\StoreWarrantyTemplateRequest;
use App\Http\Requests\Web\Workshop\UpdateWarrantyTemplateRequest;
use App\Models\WarrantyTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarrantyTemplateController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create')
                ->with('error', 'Cadastre sua oficina antes de criar modelos de garantia.');
        }

        // "Usado em N OS": OS distintas com garantia emitida a partir do modelo.
        $templates = $workshop->warrantyTemplates()
            ->withCount(['maintenanceWarranties as maintenances_using_count' => fn ($query) => $query->select(DB::raw('count(distinct maintenance_id)'))])
            ->orderByDesc('id')
            ->paginate(15);

        return view('workshop.warranty-templates.index', [
            'workshop' => $workshop,
            'templates' => $templates,
            'templatesLocked' => $workshop->hasActiveWarranties(),
        ]);
    }

    /**
     * Novo modelo. Com ?duplicar={id} (um modelo da oficina), o formulário já vem com o termo,
     * o prazo e o escopo dele: é o caminho para ajustar o texto enquanto os modelos estão
     * bloqueados.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create');
        }

        $duplicateId = $request->query('duplicar');
        $source = is_scalar($duplicateId) && ctype_digit((string) $duplicateId)
            ? $workshop->warrantyTemplates()->whereKey((int) $duplicateId)->first()
            : null;

        return view('workshop.warranty-templates.create', [
            'workshop' => $workshop,
            'templatesLocked' => $workshop->hasActiveWarranties(),
            'source' => $source,
        ]);
    }

    public function store(StoreWarrantyTemplateRequest $request): RedirectResponse
    {
        $workshop = $request->user()->workshop;
        abort_if($workshop === null, 403);

        $workshop->warrantyTemplates()->create([
            'tenant_id' => $workshop->tenant_id,
            'name' => $request->validated('name'),
            'body' => $request->validated('body'),
            'duration_days' => (int) $request->validated('duration_days'),
            'scope' => $request->validated('scope'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('workshop.warranty-templates.index')
            ->with('success', 'Modelo de garantia criado.');
    }

    public function edit(Request $request, WarrantyTemplate $warrantyTemplate): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null || $warrantyTemplate->workshop_id !== $workshop->id) {
            abort(404);
        }

        return view('workshop.warranty-templates.edit', [
            'workshop' => $workshop,
            'template' => $warrantyTemplate,
            'templatesLocked' => $workshop->hasActiveWarranties(),
        ]);
    }

    /**
     * Salva o formulário ou, vindo do liga/desliga da lista (só is_active), troca o status.
     */
    public function update(UpdateWarrantyTemplateRequest $request, WarrantyTemplate $warrantyTemplate): RedirectResponse
    {
        $warrantyTemplate->update($request->validated());

        $onlyStatus = $request->has('is_active')
            && ! $request->hasAny(['name', 'body', 'duration_days', 'scope']);

        if ($onlyStatus) {
            return redirect()->route('workshop.warranty-templates.index')
                ->with('success', $warrantyTemplate->is_active
                    ? 'Modelo "'.$warrantyTemplate->name.'" ativado: aparece ao registrar uma OS.'
                    : 'Modelo "'.$warrantyTemplate->name.'" desativado: não aparece mais em novas OS.');
        }

        return redirect()->route('workshop.warranty-templates.index')
            ->with('success', 'Modelo de garantia atualizado.');
    }

    public function destroy(Request $request, WarrantyTemplate $warrantyTemplate): RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null || $warrantyTemplate->workshop_id !== $workshop->id) {
            abort(404);
        }

        if ($warrantyTemplate->isReferenced()) {
            throw ValidationException::withMessages([
                'template' => 'Não é possível excluir um modelo de garantia que já foi usado em ordens de serviço.',
            ]);
        }

        $warrantyTemplate->delete();

        return redirect()->route('workshop.warranty-templates.index')
            ->with('success', 'Modelo de garantia excluído.');
    }
}
