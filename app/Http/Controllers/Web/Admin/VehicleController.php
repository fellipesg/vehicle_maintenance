<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleOwnershipEndingService;
use App\Support\VehiclePlateSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleController extends Controller
{
    /**
     * Colunas ordenáveis da frota (?ordenar=) => coluna ou contagem no SQL.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'veiculo' => 'brand',
        'cadastro' => 'created_at',
        'manutencoes' => 'maintenances_count',
    ];

    /**
     * Frota da plataforma inteira (sem escopo de tenant): busca por placa (atual ou antiga), chassi,
     * RENAVAM, marca ou modelo; ordenação pelas colunas de SORTS (padrão: cadastro mais recente).
     */
    public function index(Request $request): View
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $sort = is_string($request->query('ordenar')) && array_key_exists($request->query('ordenar'), self::SORTS)
            ? $request->query('ordenar')
            : 'cadastro';
        $direction = in_array($request->query('direcao'), ['asc', 'desc'], true)
            ? $request->query('direcao')
            : ($sort === 'veiculo' ? 'asc' : 'desc');

        $vehiclesQuery = Vehicle::query()
            ->withCount('maintenances')
            ->with([
                'owners' => fn ($query) => $query->wherePivot('is_current_owner', true),
                'maintenances' => fn ($query) => $query
                    ->orderByDesc('maintenance_date')
                    ->orderByDesc('id')
                    ->limit(1)
                    ->with('workshop'),
            ])
            ->orderBy(self::SORTS[$sort], $direction)
            ->when($sort === 'veiculo', fn ($query) => $query->orderBy('model', $direction))
            ->orderByDesc('id');

        if ($search !== '') {
            $lookup = VehiclePlateSearch::findByIdentifier($search);
            if ($lookup !== null) {
                $vehiclesQuery->where('vehicles.id', $lookup->vehicle->id);
            } else {
                $vehiclesQuery->where(function ($query) use ($search) {
                    $like = '%'.$search.'%';
                    $query->where('chassis', 'like', $like)
                        ->orWhere('license_plate', 'like', $like)
                        ->orWhere('renavam', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('model', 'like', $like);
                });
            }
        }

        $vehicles = $vehiclesQuery->paginate(20)->withQueryString();

        return view('admin.vehicles.index', [
            'vehicles' => $vehicles,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Ficha do veículo no admin: o mesmo <x-vehicle.detail> dos portais (linha do tempo, histórico
     * na mesma ordem e documentos completos), só leitura, com o proprietário atual.
     */
    public function show(Vehicle $vehicle): View
    {
        $vehicle->load(['owners' => fn ($query) => $query->wherePivot('is_current_owner', true)]);

        return view('admin.vehicles.show', ['vehicle' => $vehicle]);
    }

    /**
     * Encerra o vínculo de um dono atual com o veículo (VehicleOwnershipEndingService). O motivo é
     * obrigatório: tirar um carro de uma conta precisa ter nome e razão, como ocultar uma OS.
     */
    public function endOwnership(Request $request, Vehicle $vehicle, User $user, VehicleOwnershipEndingService $ownershipEnding): RedirectResponse
    {
        $data = $request->validate(
            ['ownership_ended_reason' => ['required', 'string', 'max:500']],
            ['ownership_ended_reason.required' => 'Informe o motivo para encerrar o vínculo.'],
        );

        if (! $ownershipEnding->endByTeam($vehicle, $user, $request->user(), $data['ownership_ended_reason'])) {
            return back()->with('error', "{$user->name} já não é proprietário atual deste veículo.");
        }

        return back()->with('success', "Vínculo de {$user->name} com o veículo encerrado.");
    }
}
