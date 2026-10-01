<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\VehicleConsignment;
use App\Services\Vehicle\VehicleConsignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsignmentController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filtro', 'atencao');

        $consignments = VehicleConsignment::query()
            ->with(['vehicle', 'garageUser', 'ownerUser', 'reviewer'])
            ->when($filter === 'atencao', fn ($query) => $query->where(
                fn ($q) => $q->where('history_access_status', VehicleConsignment::HISTORY_PENDING)
                    ->orWhereNotNull('owner_disputed_at')
            ))
            ->when($filter === 'contestadas', fn ($query) => $query->whereNotNull('owner_disputed_at'))
            ->when($filter === 'ativas', fn ($query) => $query->where('status', VehicleConsignment::STATUS_ACTIVE))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.consignments.index', [
            'consignments' => $consignments,
            'filter' => $filter,
            'pendingCount' => VehicleConsignment::where('history_access_status', VehicleConsignment::HISTORY_PENDING)->count(),
            'disputedCount' => VehicleConsignment::whereNotNull('owner_disputed_at')->count(),
        ]);
    }

    public function approve(VehicleConsignment $consignment, VehicleConsignmentService $consignments): RedirectResponse
    {
        $consignments->approveHistoryAccess($consignment, 'staff', request()->user());

        return back()->with('success', 'Acesso ao histórico liberado para a garagem.');
    }

    public function reject(Request $request, VehicleConsignment $consignment, VehicleConsignmentService $consignments): RedirectResponse
    {
        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $consignments->rejectHistoryAccess($consignment, $request->user(), $data['review_notes'] ?? null);

        return back()->with('success', 'Pedido de acesso ao histórico recusado.');
    }

    public function revoke(VehicleConsignment $consignment, VehicleConsignmentService $consignments): RedirectResponse
    {
        try {
            $consignments->end($consignment, 'revoked');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['consignment' => $exception->getMessage()]);
        }

        return back()->with('success', 'Consignação revogada. A garagem perdeu o acesso ao veículo.');
    }

    public function clearDispute(VehicleConsignment $consignment, VehicleConsignmentService $consignments): RedirectResponse
    {
        $consignments->clearDispute($consignment);

        return back()->with('success', 'Contestação arquivada. A garagem voltou a registrar manutenções.');
    }

    public function downloadPowerOfAttorney(VehicleConsignment $consignment): StreamedResponse
    {
        abort_if($consignment->power_of_attorney_path === null, 404);

        return Storage::disk(\App\Support\AppStorage::diskName())
            ->download($consignment->power_of_attorney_path, 'procuracao-'.$consignment->id.'.pdf');
    }
}
