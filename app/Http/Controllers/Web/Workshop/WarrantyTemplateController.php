<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Workshop\StoreWarrantyTemplateRequest;
use App\Http\Requests\Web\Workshop\UpdateWarrantyTemplateRequest;
use App\Models\WarrantyTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WarrantyTemplateController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create')
                ->with('error', 'Cadastre sua oficina antes de gerenciar templates de garantia.');
        }

        $templates = $workshop->warrantyTemplates()
            ->orderByDesc('id')
            ->paginate(15);

        return view('workshop.warranty-templates.index', [
            'workshop' => $workshop,
            'templates' => $templates,
            'templatesLocked' => $workshop->hasActiveWarranties(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create');
        }

        return view('workshop.warranty-templates.create', [
            'workshop' => $workshop,
            'templatesLocked' => $workshop->hasActiveWarranties(),
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
            ->with('success', 'Template de garantia criado com sucesso!');
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

    public function update(UpdateWarrantyTemplateRequest $request, WarrantyTemplate $warrantyTemplate): RedirectResponse
    {
        $warrantyTemplate->update($request->validated());

        return redirect()->route('workshop.warranty-templates.index')
            ->with('success', 'Template de garantia atualizado com sucesso!');
    }

    public function destroy(Request $request, WarrantyTemplate $warrantyTemplate): RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null || $warrantyTemplate->workshop_id !== $workshop->id) {
            abort(404);
        }

        if ($warrantyTemplate->isReferenced()) {
            throw ValidationException::withMessages([
                'template' => 'Não é possível excluir um template que já foi utilizado em ordens de serviço.',
            ]);
        }

        $warrantyTemplate->delete();

        return redirect()->route('workshop.warranty-templates.index')
            ->with('success', 'Template de garantia removido com sucesso!');
    }
}
