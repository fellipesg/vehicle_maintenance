<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VehicleResource;
use App\Models\Vehicle;
use App\Support\ApiResponse;
use App\Support\VehicleListIncludes;
use App\Support\VehiclePlateSearch;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Admin', weight: 5)]
class VehicleController extends Controller
{
    use ResolvesPagination;

    #[QueryParameter('search', 'Filter by chassis, plate, RENAVAM, brand, or model.')]
    #[QueryParameter('page', 'Page number (default 1).', type: 'integer')]
    #[QueryParameter('per_page', 'Results per page (default 15, max 100).', type: 'integer')]
    #[QueryParameter(
        'include',
        'Optional comma-separated relations: plates, provenance_strip.',
        type: 'string',
    )]
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            abort(403);
        }

        $search = trim((string) $request->query('search', ''));

        $vehicles = Vehicle::query()
            ->withCount([
                'maintenances',
                'maintenances as verified_maintenances_count' => fn ($q) => $q->whereNotNull('verified_at'),
            ])
            ->orderByDesc('vehicles.created_at');

        if ($search !== '') {
            $lookup = VehiclePlateSearch::findByIdentifier($search);
            if ($lookup !== null) {
                $vehicles->where('vehicles.id', $lookup->vehicle->id);
            } else {
                $vehicles->where(function ($query) use ($search) {
                    $like = '%'.$search.'%';
                    $query->where('chassis', 'like', $like)
                        ->orWhere('license_plate', 'like', $like)
                        ->orWhere('renavam', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('model', 'like', $like);
                });
            }
        }

        VehicleListIncludes::applyEagerLoads(
            $vehicles,
            VehicleListIncludes::parse($request->query('include')),
        );

        return ApiResponse::paginated(
            $vehicles->paginate($this->perPage($request)),
            VehicleResource::class,
        );
    }
}
