<?php

namespace App\Http\Controllers\Web\User;

use App\Http\Controllers\Controller;
use App\Models\VehicleInspection;
use App\Services\Inspection\InspectionDisputeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * A contestação do proprietário sobre um laudo do carro dele, lançada da aba Vistorias da ficha —
 * que é onde ele encontra o laudo (.ai/rules/inspections.md).
 *
 * Não pede CRLV-e: é a ação que o dono atual tem sem o documento. Quem decide se ele é o dono atual
 * é o serviço, não esta classe nem a view.
 */
class InspectionDisputeController extends Controller
{
    public function __construct(private readonly InspectionDisputeService $disputes) {}

    public function store(Request $request, VehicleInspection $inspection): RedirectResponse
    {
        $data = $request->validate([
            // Mesmo teto do motivo de contestação da OS. O campo é texto livre do proprietário: a
            // dica da tela pede para não incluir dado pessoal, como nos outros campos abertos.
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->disputes->dispute(
                $inspection,
                $request->user(),
                filled($data['reason'] ?? null) ? $data['reason'] : null,
            );
        } catch (AuthorizationException $exception) {
            return back()->withErrors(['dispute' => $exception->getMessage()]);
        } catch (ValidationException $exception) {
            return back()->withErrors(['dispute' => (string) collect($exception->errors())->flatten()->first()]);
        }

        return back()->with(
            'success',
            'Laudo contestado. Avisamos a oficina que o emitiu; ele continua no histórico, agora com a sua contestação à vista.'
        );
    }

    public function destroy(Request $request, VehicleInspection $inspection): RedirectResponse
    {
        try {
            $this->disputes->withdrawDispute($inspection, $request->user());
        } catch (AuthorizationException $exception) {
            return back()->withErrors(['dispute' => $exception->getMessage()]);
        } catch (ValidationException $exception) {
            // Laudo suprimido pela equipe: não é contestável nem descontestável (ver o serviço).
            return back()->withErrors(['dispute' => (string) collect($exception->errors())->flatten()->first()]);
        }

        return back()->with('success', 'Contestação retirada.');
    }
}
