<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkshopRequest;
use App\Http\Requests\Api\V1\UpdateWorkshopRequest;
use App\Http\Resources\Api\V1\WorkshopResource;
use App\Models\Workshop;
use App\Services\Workshop\WorkshopLogoService;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

#[Group('Workshops', weight: 20)]
class WorkshopController extends Controller
{
    use ResolvesPagination;

    public function __construct(
        private WorkshopLogoService $logos,
    ) {}

    #[QueryParameter('search', 'Filter by workshop name, city, or neighborhood.')]
    #[QueryParameter('page', 'Page number (default 1).', type: 'integer')]
    #[QueryParameter('per_page', 'Results per page (default 15, max 100).', type: 'integer')]
    #[Endpoint(title: 'List workshops')]
    public function index(Request $request): JsonResponse
    {
        $query = Workshop::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('neighborhood', 'like', "%{$search}%");
            });
        }

        $workshops = $query->orderBy('name')->paginate($this->perPage($request));

        return ApiResponse::paginated($workshops, WorkshopResource::class);
    }

    public function store(StoreWorkshopRequest $request): JsonResponse
    {
        $whatsapp = $request->whatsapp ?? $request->phone;

        $workshop = Workshop::create([
            'user_id' => $request->user()->id,
            'tenant_id' => $request->user()->tenant_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'whatsapp' => $whatsapp,
            'email' => $request->email,
            'facebook' => $request->facebook,
            'instagram' => $request->instagram,
            'cep' => preg_replace('/\D/', '', $request->cep),
            'street' => $request->street,
            'number' => $request->number,
            'complement' => $request->complement,
            'neighborhood' => $request->neighborhood,
            'city' => $request->city,
            'state' => strtoupper($request->state),
        ]);

        if ($request->hasFile('logo')) {
            $this->logos->store($workshop, $request->file('logo'));
            $workshop->refresh();
        }

        return ApiResponse::created(new WorkshopResource($workshop), 'Workshop created successfully');
    }

    public function show(string $id): JsonResponse
    {
        $workshop = Workshop::findOrFail($id);

        return ApiResponse::success(new WorkshopResource($workshop));
    }

    public function update(UpdateWorkshopRequest $request, string $id): JsonResponse
    {
        $workshop = Workshop::findOrFail($id);

        $data = $request->only([
            'name', 'phone', 'whatsapp', 'email', 'facebook', 'instagram',
            'cep', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
        ]);

        if (isset($data['cep'])) {
            $data['cep'] = preg_replace('/\D/', '', $data['cep']);
        }
        if (isset($data['state'])) {
            $data['state'] = strtoupper($data['state']);
        }

        if (! isset($data['whatsapp']) && isset($data['phone'])) {
            $data['whatsapp'] = $data['phone'];
        } elseif (! isset($data['whatsapp']) && ! isset($data['phone'])) {
            $data['whatsapp'] = $workshop->phone;
        }

        $workshop->update($data);

        if ($request->hasFile('logo')) {
            $this->logos->store($workshop, $request->file('logo'));
        }

        return ApiResponse::success(new WorkshopResource($workshop->fresh()), 'Workshop updated successfully');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $workshop = Workshop::findOrFail($id);

        Gate::authorize('delete', $workshop);

        if ($workshop->maintenances()->count() > 0) {
            return ApiResponse::error(
                'Cannot delete workshop because it has associated maintenances.',
                422,
            );
        }

        $workshop->delete();

        return ApiResponse::success(message: 'Workshop deleted successfully');
    }
}
