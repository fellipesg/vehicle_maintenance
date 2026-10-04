<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Workshop\StoreMessageTemplateRequest;
use App\Http\Requests\Web\Workshop\UpdateMessageTemplateRequest;
use App\Models\WorkshopMessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modelos de mensagem automática da oficina: cadência pós-OS, lembretes de revisão, garantia
 * expirando e reativação. A lista mostra status (ativo/inativo), gatilho e quantas vezes disparou.
 * O plano da oficina limita quantos templates ativos podem existir ao mesmo tempo.
 */
class MessageTemplateController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create')
                ->with('error', 'Cadastre sua oficina antes de criar mensagens.');
        }

        $templates = $workshop->messageTemplates()
            ->withCount('dispatches')
            ->orderBy('trigger')
            ->orderByDesc('id')
            ->paginate(20);

        return view('workshop.message-templates.index', [
            'workshop' => $workshop,
            'templates' => $templates,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create');
        }

        return view('workshop.message-templates.create', [
            'workshop' => $workshop,
        ]);
    }

    public function store(StoreMessageTemplateRequest $request): RedirectResponse
    {
        $workshop = $request->user()->workshop;
        abort_if($workshop === null, 403);

        $workshop->messageTemplates()->create([
            'tenant_id' => $workshop->tenant_id,
            ...$request->validated(),
        ]);

        return redirect()->route('workshop.message-templates.index')
            ->with('success', 'Modelo de mensagem criado.');
    }

    public function edit(Request $request, WorkshopMessageTemplate $messageTemplate): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        abort_if($workshop === null || (int) $messageTemplate->workshop_id !== (int) $workshop->id, 403);

        return view('workshop.message-templates.edit', [
            'workshop' => $workshop,
            'template' => $messageTemplate,
        ]);
    }

    public function update(UpdateMessageTemplateRequest $request, WorkshopMessageTemplate $messageTemplate): RedirectResponse
    {
        $workshop = $request->user()->workshop;

        abort_if($workshop === null || (int) $messageTemplate->workshop_id !== (int) $workshop->id, 403);

        $messageTemplate->update($request->validated());

        return redirect()->route('workshop.message-templates.index')
            ->with('success', 'Modelo de mensagem atualizado.');
    }

    public function destroy(Request $request, WorkshopMessageTemplate $messageTemplate): RedirectResponse
    {
        $workshop = $request->user()->workshop;

        abort_if($workshop === null || (int) $messageTemplate->workshop_id !== (int) $workshop->id, 403);

        $messageTemplate->delete();

        return redirect()->route('workshop.message-templates.index')
            ->with('success', 'Modelo de mensagem excluído.');
    }
}
