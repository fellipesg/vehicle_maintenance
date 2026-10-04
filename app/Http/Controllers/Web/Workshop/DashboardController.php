<?php

namespace App\Http\Controllers\Web\Workshop;

use App\Http\Controllers\Controller;
use App\Models\MaintenancePhoto;
use App\Models\Workshop;
use App\Support\Maintenance\WorkshopMaintenanceFilters;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Início da oficina como fila de trabalho: Nova OS pela placa, indicadores com totais reais e as
 * pendências que abrem a lista de OS já filtrada.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $workshop = $request->user()->workshop;

        if ($workshop === null) {
            return view('workshop.dashboard', [
                'workshop' => null,
                'stats' => null,
                'pendingItems' => [],
                'recentMaintenances' => collect(),
            ]);
        }

        $today = now()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();

        $stats = [
            'month' => $this->sealedOrders($workshop)
                ->whereDate('maintenance_date', '>=', $monthStart->toDateString())
                ->whereDate('maintenance_date', '<=', $today->toDateString())
                ->count(),
            'sealed' => $this->sealedOrders($workshop)->count(),
            'warranties' => $workshop->issuedWarranties()
                ->whereDate('maintenance_warranties.ends_at', '>=', $today->toDateString())
                ->count(),
            'expiring' => $workshop->issuedWarranties()
                ->whereDate('maintenance_warranties.ends_at', '>=', $today->toDateString())
                ->whereDate('maintenance_warranties.ends_at', '<=', $today->copy()->addDays(WorkshopMaintenanceFilters::EXPIRING_WITHIN_DAYS)->toDateString())
                ->count(),
            'month_start' => $monthStart->toDateString(),
            'today' => $today->toDateString(),
        ];

        $recentMaintenances = $workshop->maintenances()
            ->with(['vehicle', 'user', 'verifiedWorkshop', 'workshop'])
            ->withCount(['invoices', 'photos', 'items'])
            ->withSum('items as items_total', 'total_price')
            ->orderByDesc('maintenance_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('workshop.dashboard', [
            'workshop' => $workshop,
            'stats' => $stats,
            'pendingItems' => $this->pendingItems($workshop),
            'recentMaintenances' => $recentMaintenances,
        ]);
    }

    /**
     * OS com o Selo desta oficina (as que ela registrou).
     *
     * @return HasMany<\App\Models\Maintenance, Workshop>
     */
    private function sealedOrders(Workshop $workshop): HasMany
    {
        return $workshop->maintenances()
            ->whereNotNull('verified_at')
            ->where('verified_workshop_id', $workshop->id);
    }

    /**
     * Pendências com contagem, texto e destino. Só entram as que existem.
     *
     * @return list<array{key: string, count: int|null, label: string, description: string, url: string, action: string}>
     */
    private function pendingItems(Workshop $workshop): array
    {
        $items = [];

        $awaitingReview = $workshop->maintenancesAwaitingReview()->count();
        if ($awaitingReview > 0) {
            $items[] = [
                'key' => 'validacoes-pendentes',
                'count' => $awaitingReview,
                'label' => $awaitingReview === 1 ? '1 serviço aguardando validação' : "{$awaitingReview} serviços aguardando validação",
                'description' => 'Confirme os serviços que foram feitos na sua oficina para emitir o Selo.',
                'url' => route('workshop.reviews.index'),
                'action' => 'Ver validações',
            ];
        }

        $withoutInvoice = $this->sealedOrders($workshop)->doesntHave('invoices')->count();
        if ($withoutInvoice > 0) {
            $items[] = [
                'key' => 'sem-nfe',
                'count' => $withoutInvoice,
                'label' => $withoutInvoice === 1 ? '1 OS sem nota fiscal' : "{$withoutInvoice} OS sem nota fiscal",
                'description' => 'Anexar a NF-e reforça o Selo da oficina e importa as peças.',
                'url' => route('workshop.maintenances.index', ['verified' => '1', 'anexos' => 'sem-nfe']),
                'action' => 'Ver OS sem NF-e',
            ];
        }

        $withoutAfterPhotos = $this->sealedOrders($workshop)
            ->whereDoesntHave('photos', fn ($photos) => $photos->where('stage', MaintenancePhoto::STAGE_AFTER))
            ->count();
        if ($withoutAfterPhotos > 0) {
            $items[] = [
                'key' => 'sem-fotos-depois',
                'count' => $withoutAfterPhotos,
                'label' => $withoutAfterPhotos === 1 ? '1 OS sem fotos de depois' : "{$withoutAfterPhotos} OS sem fotos de depois",
                'description' => 'A foto do resultado é a prova que o cliente vê na ficha do veículo.',
                'url' => route('workshop.maintenances.index', ['verified' => '1', 'anexos' => 'sem-fotos-depois']),
                'action' => 'Ver OS sem fotos',
            ];
        }

        if ($workshop->logoUrl() === null) {
            $items[] = [
                'key' => 'sem-logo',
                'count' => null,
                'label' => 'Perfil sem logo',
                'description' => 'A logo aparece no Selo da oficina, no PDF do histórico e no diretório.',
                'url' => route('workshop.profile.edit'),
                'action' => 'Enviar logo',
            ];
        }

        if (! $workshop->warrantyTemplates()->where('is_active', true)->exists()) {
            $items[] = [
                'key' => 'sem-modelo-garantia',
                'count' => null,
                'label' => 'Nenhum modelo de garantia ativo',
                'description' => 'Com um modelo, a garantia entra na OS com a data de validade calculada.',
                'url' => route('workshop.warranty-templates.create'),
                'action' => 'Criar modelo',
            ];
        }

        return $items;
    }
}
