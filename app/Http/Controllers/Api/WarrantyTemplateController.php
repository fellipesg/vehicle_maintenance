<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesPagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWarrantyTemplateRequest;
use App\Http\Requests\Api\V1\UpdateWarrantyTemplateRequest;
use App\Http\Resources\Api\V1\WarrantyTemplateResource;
use App\Models\WarrantyTemplate;
use App\Models\Workshop;
use App\Support\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

#[Group('Warranty templates', weight: 22)]
class WarrantyTemplateController extends Controller
{
    use ResolvesPagination;

    public function index(Request $request, Workshop $workshop): JsonResponse
    {
        Gate::authorize('view', $workshop);

        $templates = $workshop->warrantyTemplates()
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return ApiResponse::paginated($templates, WarrantyTemplateResource::class);
    }

    public function store(StoreWarrantyTemplateRequest $request, Workshop $workshop): JsonResponse
    {
        $template = $workshop->warrantyTemplates()->create([
            'tenant_id' => $workshop->tenant_id,
            'name' => $request->validated('name'),
            'body' => $request->validated('body'),
            'duration_days' => (int) $request->validated('duration_days'),
            'scope' => $request->validated('scope'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ApiResponse::created(new WarrantyTemplateResource($template), 'Warranty template created successfully');
    }

    public function show(Workshop $workshop, WarrantyTemplate $warrantyTemplate): JsonResponse
    {
        Gate::authorize('view', $workshop);
        $this->ensureTemplateBelongsToWorkshop($workshop, $warrantyTemplate);

        return ApiResponse::success(new WarrantyTemplateResource($warrantyTemplate));
    }

    public function update(
        UpdateWarrantyTemplateRequest $request,
        Workshop $workshop,
        WarrantyTemplate $warrantyTemplate,
    ): JsonResponse {
        $this->ensureTemplateBelongsToWorkshop($workshop, $warrantyTemplate);

        $warrantyTemplate->update($request->validated());

        return ApiResponse::success(
            new WarrantyTemplateResource($warrantyTemplate->fresh()),
            'Warranty template updated successfully',
        );
    }

    public function destroy(Workshop $workshop, WarrantyTemplate $warrantyTemplate): JsonResponse
    {
        Gate::authorize('update', $workshop);
        $this->ensureTemplateBelongsToWorkshop($workshop, $warrantyTemplate);

        if ($warrantyTemplate->isReferenced()) {
            throw ValidationException::withMessages([
                'template' => 'Não é possível excluir um template que já foi utilizado em ordens de serviço.',
            ]);
        }

        $warrantyTemplate->delete();

        return ApiResponse::success(message: 'Warranty template deleted successfully');
    }

    private function ensureTemplateBelongsToWorkshop(Workshop $workshop, WarrantyTemplate $warrantyTemplate): void
    {
        if ($warrantyTemplate->workshop_id !== $workshop->id) {
            abort(404);
        }
    }
}
