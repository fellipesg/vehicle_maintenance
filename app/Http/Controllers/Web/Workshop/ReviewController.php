<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Enums\WorkshopReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Workshop\RejectMaintenanceReviewRequest;
use App\Models\Maintenance;
use App\Services\Maintenance\WorkshopReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Validações": manutenções que clientes declararam citando a oficina. A oficina confirma (o
 * serviço recebe o Selo da oficina e passa a ser dela) ou não reconhece (o vínculo sai).
 */
class ReviewController extends Controller
{
    public const RECENT_DAYS = 30;

    public function __construct(
        private readonly WorkshopReviewService $reviews,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return redirect()->route('workshop.profile.create')
                ->with('error', 'Cadastre sua oficina antes de validar serviços.');
        }

        $pending = $workshop->maintenancesAwaitingReview()
            ->with(['vehicle', 'user', 'invoices', 'photos'])
            ->withCount('items')
            ->withSum('items as items_total', 'total_price')
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $since = now()->subDays(self::RECENT_DAYS);
        $answeredBy = fn () => Maintenance::query()
            ->where('workshop_reviewed_by', $request->user()->id)
            ->where('workshop_reviewed_at', '>=', $since);

        return view('workshop.reviews.index', [
            'workshop' => $workshop,
            'pending' => $pending,
            'recentConfirmed' => $answeredBy()->where('workshop_review_status', WorkshopReviewStatus::Confirmed->value)->count(),
            'recentRejected' => $answeredBy()->where('workshop_review_status', WorkshopReviewStatus::Rejected->value)->count(),
        ]);
    }

    public function confirm(Request $request, Maintenance $maintenance): RedirectResponse
    {
        if ($redirect = $this->redirectWhenNotReviewable($maintenance)) {
            return $redirect;
        }

        $maintenance = $this->reviews->confirm($maintenance, $request->user());

        return redirect()->route('workshop.reviews.index')
            ->with('success', "Serviço confirmado. A OS recebeu o Selo da oficina ({$maintenance->verification_code}).");
    }

    public function reject(RejectMaintenanceReviewRequest $request, Maintenance $maintenance): RedirectResponse
    {
        if ($redirect = $this->redirectWhenNotReviewable($maintenance)) {
            return $redirect;
        }

        $this->reviews->reject($maintenance, $request->user(), $request->validated('note'));

        return redirect()->route('workshop.reviews.index')
            ->with('success', 'Serviço marcado como não reconhecido. O cliente foi avisado e o vínculo com a oficina saiu.');
    }

    /**
     * Já respondida (ou de outra oficina): volta para a fila com o aviso, em vez de um 403 seco.
     */
    private function redirectWhenNotReviewable(Maintenance $maintenance): ?RedirectResponse
    {
        if (Gate::allows('review', $maintenance)) {
            return null;
        }

        return redirect()->route('workshop.reviews.index')
            ->with('error', 'Esse serviço já foi respondido ou não cita a sua oficina.');
    }
}
