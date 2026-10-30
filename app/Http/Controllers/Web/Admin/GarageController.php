<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\GarageStatus;
use App\Http\Controllers\Controller;
use App\Models\Garage;
use App\Services\Garage\GarageReview;
use App\Support\Maintenance\MaintenancePhotoAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fila de análise das lojas, no mesmo formato de admin.consignments: a aba padrão é a que pede
 * ação. A conferência do CNPJ é manual — a tela junta o que a loja declarou para o admin comparar
 * com o registro público antes de decidir.
 */
class GarageController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private GarageReview $review,
    ) {}

    public function index(Request $request): View
    {
        $filter = $request->query('filtro', GarageStatus::Pending->value);
        $status = GarageStatus::tryFrom(is_string($filter) ? $filter : '');

        $garages = Garage::query()
            ->with(['user', 'reviewer'])
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw('case when status = ? then 0 else 1 end', [GarageStatus::Pending->value])
            ->orderByDesc('updated_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        MaintenancePhotoAccess::preload($garages->getCollection());

        return view('admin.garages.index', [
            'garages' => $garages,
            'filter' => $status?->value ?? 'todas',
            'counts' => $this->counts(),
        ]);
    }

    public function approve(Request $request, Garage $garage): RedirectResponse
    {
        $this->review->approve($garage, $request->user());

        return back()->with('success', "Loja {$garage->name} aprovada.");
    }

    public function reject(Request $request, Garage $garage): RedirectResponse
    {
        $data = $request->validate(
            ['review_notes' => ['nullable', 'string', 'max:1000']],
        );

        $this->review->reject($garage, $request->user(), $data['review_notes'] ?? null);

        return back()->with('success', "Loja {$garage->name} recusada.");
    }

    /**
     * Quantidade por situação, para os contadores das abas.
     *
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = Garage::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            GarageStatus::Pending->value => (int) $counts->get(GarageStatus::Pending->value, 0),
            GarageStatus::Approved->value => (int) $counts->get(GarageStatus::Approved->value, 0),
            GarageStatus::Rejected->value => (int) $counts->get(GarageStatus::Rejected->value, 0),
        ];
    }
}
