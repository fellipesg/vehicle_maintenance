<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleInspection;
use App\Services\Inspection\InspectionPublicVisibilityService;
use App\Support\VehiclePlateSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vistorias cautelares da plataforma inteira (sem escopo de tenant, como as outras listas do admin).
 *
 * É a única tela que mostra o laudo **suprimido**: a consulta aqui é direta, sem passar por
 * Vehicle::inspectionsVisibleTo nem pelo InspectionRedactor, porque quem suprimiu precisa continuar
 * vendo o que tirou de circulação para poder devolver. É o mesmo caminho das manutenções — a ficha
 * do veículo e a consulta pública seguem sem ele (.ai/rules/admin.md).
 *
 * Filtros na query string:
 * - q: código do laudo, placa (atual ou antiga, via VehiclePlateSearch), chassi, marca/modelo ou
 *   nome do emissor;
 * - contestadas=1: só o que o proprietário contestou (é para onde o cartão de Pendências aponta);
 * - suprimidas=1: só o que a equipe tirou de circulação.
 */
class VehicleInspectionController extends Controller
{
    public function __construct(private readonly InspectionPublicVisibilityService $visibility) {}

    public function index(Request $request): View
    {
        $search = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $onlyDisputed = $request->query('contestadas') === '1';
        $onlyHidden = $request->query('suprimidas') === '1';

        $inspections = VehicleInspection::query()
            ->with(['vehicle', 'workshop', 'disputedBy', 'hiddenBy', 'visibilityEvents.actor'])
            ->when($onlyDisputed, fn (Builder $query) => $query->whereNotNull('owner_disputed_at'))
            ->when($onlyHidden, fn (Builder $query) => $query->whereNotNull('hidden_from_public_at'))
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.inspections.index', [
            'inspections' => $inspections,
            'search' => $search,
            'onlyDisputed' => $onlyDisputed,
            'onlyHidden' => $onlyHidden,
            'disputedCount' => VehicleInspection::query()->whereNotNull('owner_disputed_at')->count(),
            'hiddenCount' => VehicleInspection::query()->whereNotNull('hidden_from_public_at')->count(),
        ]);
    }

    /**
     * Tira o laudo de circulação. É o fim da linha de uma contestação que a equipe confirmou
     * (chassi trocado, fraude) ou de um pedido de remoção que a empresa honre — e é só daqui que
     * isso sai: o proprietário contesta, não suprime.
     */
    public function hide(Request $request, VehicleInspection $inspection): RedirectResponse
    {
        $data = $request->validate(['hidden_reason' => ['nullable', 'string', 'max:500']]);

        $this->visibility->hide($inspection, $request->user(), $data['hidden_reason'] ?? null);

        return back()->with('success', 'Laudo suprimido: fora do histórico e da conferência pública.');
    }

    /**
     * Devolver também é ato com nome: o admin vai para a trilha
     * (App\Models\InspectionVisibilityEvent), que esta ação acrescenta sem apagar a supressão
     * anterior.
     */
    public function restore(Request $request, VehicleInspection $inspection): RedirectResponse
    {
        $this->visibility->restore($inspection, $request->user());

        return back()->with('success', 'Laudo de volta ao histórico e à conferência pública.');
    }

    /**
     * @param  Builder<VehicleInspection>  $query
     * @return Builder<VehicleInspection>
     */
    private function applySearch(Builder $query, string $search): Builder
    {
        $lookup = VehiclePlateSearch::findByIdentifier($search);

        if ($lookup !== null) {
            return $query->where('vehicle_inspections.vehicle_id', $lookup->vehicle->id);
        }

        $like = '%'.$search.'%';

        return $query->where(function (Builder $query) use ($like): void {
            $query->where('vehicle_inspections.verification_code', 'like', $like)
                ->orWhere('vehicle_inspections.workshop_name', 'like', $like)
                ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle
                    ->where('license_plate', 'like', $like)
                    ->orWhere('chassis', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('model', 'like', $like))
                ->orWhereHas('workshop', fn (Builder $workshop) => $workshop->where('name', 'like', $like));
        });
    }
}
